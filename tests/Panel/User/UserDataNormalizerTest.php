<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\User;

use PHPForge\Debug\Panel\User\{UserAttribute, UserDataNormalizer};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Xepozz\InternalMocker\MockerState;

use function array_map;

/**
 * Unit tests for {@see UserDataNormalizer} covering the narrowing of captured identity data
 * into the typed view-model: hero composition (monogram + status variant), attribute bucketing (Identity / Security /
 * Timestamps / Other), VarDumper-quote stripping, sensitive-key detection and timestamp humanization.
 */
#[Group('panel')]
#[Group('user')]
final class UserDataNormalizerTest extends TestCase
{
    private const int NOW = 1_800_000_000;

    public function testFromIdentityBucketsAttributesIntoOtherSectionWhenNotSensitiveNotTimestamp(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            [
                'username' => "'admin'",
                'preferred_locale' => "'en'",
                'tier' => "'gold'",
            ],
            null,
        );

        $other = null;

        foreach ($view->sections as $section) {
            if ($section->label === 'Other attributes') {
                $other = $section;
            }
        }

        self::assertNotNull(
            $other,
            "Plain non-sensitive non-timestamp keys must surface under the 'Other attributes' section.",
        );

        $keys = array_map(static fn(UserAttribute $a): string => $a->key, $other->attributes);

        self::assertContains(
            'preferred_locale',
            $keys,
            "'preferred_locale' must land in the Other bucket.",
        );
        self::assertContains(
            'tier',
            $keys,
            "'tier' must land in the Other bucket.",
        );
    }

    public function testFromIdentityBucketsSensitiveAttributesIntoSecuritySection(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            [
                'id' => "'1'",
                'username' => "'admin'",
                'auth_key' => "'abc'",
                'password_hash' => "'def'",
                'verification_token' => "'tok'",
            ],
            null,
        );

        $captions = [];

        foreach ($view->sections as $section) {
            $captions[] = $section->label;
        }

        self::assertContains(
            'Security',
            $captions,
            'Sensitive keys must surface in the Security section.',
        );

        foreach ($view->sections as $candidate) {
            if ($candidate->label === 'Security') {
                $keys = array_map(static fn(UserAttribute $a): string => $a->key, $candidate->attributes);

                self::assertSame(
                    ['auth_key', 'password_hash', 'verification_token'],
                    $keys,
                    'Security bucket must hold every sensitive attribute.',
                );

                foreach ($candidate->attributes as $attr) {
                    self::assertSame(
                        UserAttribute::KIND_SECURITY,
                        $attr->kind,
                        'Security rows must carry the security kind.',
                    );
                }
            }
        }
    }

    public function testFromIdentityBuildsAvatarMonogramFromUsername(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            ['username' => "'admin'"],
            null,
        );

        self::assertSame(
            'A',
            $view->hero->monogram,
            'Monogram must come from the first letter of the username.',
        );
        self::assertSame(
            'admin',
            $view->hero->username,
            'Username must surface with VarDumper quotes stripped.',
        );
    }

    public function testFromIdentityBuildsDefaultLabelsFromDotsAndUnderscores(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            ['username' => "'admin'", 'preferred.locale_key' => "'en'"],
            null,
        );

        $other = $view->sections[1] ?? null;

        self::assertNotNull($other, 'Other attributes section must be present.');
        self::assertSame(
            ['Preferred Locale Key'],
            array_map(static fn(UserAttribute $attribute): string => $attribute->label, $other->attributes),
            'Default labels must replace dots and underscores before title-casing.',
        );
    }

    public function testFromIdentityClassifiesCaseInsensitiveAndNumericTimestampCandidates(): void
    {
        self::freezeTime();

        $view = UserDataNormalizer::fromIdentity(
            [
                'username' => "'admin'",
                'PASSWORD' => "'secret'",
                'CREATED_ON' => "'not-an-epoch'",
                'numeric_timestamp' => "'1700000000'",
                'nine_digits' => "'123456789'",
                'eleven_digits' => "'12345678901'",
                'ten_characters' => "'123456789x'",
            ],
            null,
        );

        $sectionKeys = [];

        foreach ($view->sections as $section) {
            $sectionKeys[$section->label] = array_map(
                static fn(UserAttribute $attribute): string => $attribute->key,
                $section->attributes,
            );
        }

        self::assertSame(['PASSWORD'], $sectionKeys['Security'] ?? null, 'Sensitive matching must ignore key case.');
        self::assertSame(
            ['CREATED_ON', 'numeric_timestamp'],
            $sectionKeys['Timestamps'] ?? null,
            'Timestamp matching must ignore key case and accept quoted ten-digit epochs.',
        );
        self::assertSame(
            ['nine_digits', 'eleven_digits', 'ten_characters'],
            $sectionKeys['Other attributes'] ?? null,
            'Numeric fallback must reject the wrong length and non-digit values.',
        );
    }

    public function testFromIdentityDoesNotOfferRevealControlsForRedactedValues(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            ['auth_key' => "'[redacted]'"],
            null,
        );
        $attribute = $view->sections[0]->attributes[0] ?? self::fail('Expected the redacted security attribute.');

        self::assertSame(
            UserAttribute::KIND_PLAIN,
            $attribute->kind,
            'An irreversible placeholder must render as plain text instead of offering a reveal control.',
        );
        self::assertSame('[redacted]', $attribute->displayValue, 'The placeholder must remain visible.');
    }

    public function testFromIdentityFallsBackMonogramToEmailWhenUsernameMissing(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            ['email' => "'someone@example.com'"],
            null,
        );

        self::assertSame(
            'S',
            $view->hero->monogram,
            'Monogram must fall back to the email first letter.',
        );
        self::assertSame(
            'Unknown user',
            $view->hero->username,
            'Missing username must yield the `Unknown user` placeholder.',
        );
    }

    public function testFromIdentityHumanizesTimestampsAcrossEveryRelativeBucket(): void
    {
        self::freezeTime();

        $view = UserDataNormalizer::fromIdentity(
            [
                'second_59_at' => "'" . (self::NOW - 59) . "'",
                'second_60_at' => "'" . (self::NOW - 60) . "'",
                'second_61_at' => "'" . (self::NOW - 61) . "'",
                'second_3599_at' => "'" . (self::NOW - 3599) . "'",
                'second_3600_at' => "'" . (self::NOW - 3600) . "'",
                'second_86399_at' => "'" . (self::NOW - 86399) . "'",
                'second_86400_at' => "'" . (self::NOW - 86400) . "'",
                'second_2591999_at' => "'" . (self::NOW - 2591999) . "'",
                'second_2592000_at' => "'" . (self::NOW - 2592000) . "'",
                'old_at' => "'0'",
            ],
            null,
        );

        $relatives = [];

        foreach ($view->sections as $section) {
            if ($section->label !== 'Timestamps') {
                continue;
            }

            foreach ($section->attributes as $attr) {
                $relatives[$attr->key] = $attr->timestampRel;
            }
        }

        self::assertSame('just now', $relatives['second_59_at'] ?? null, '59 seconds must remain just now.');
        self::assertSame('1 min ago', $relatives['second_60_at'] ?? null, '60 seconds must become one minute.');
        self::assertSame('1 min ago', $relatives['second_61_at'] ?? null, '61 seconds must round down to one minute.');
        self::assertSame('59 min ago', $relatives['second_3599_at'] ?? null, '3599 seconds must round down.');
        self::assertSame('1 h ago', $relatives['second_3600_at'] ?? null, '3600 seconds must become one hour.');
        self::assertSame('23 h ago', $relatives['second_86399_at'] ?? null, '86399 seconds must round down.');
        self::assertSame('1 d ago', $relatives['second_86400_at'] ?? null, '86400 seconds must become one day.');
        self::assertSame('29 d ago', $relatives['second_2591999_at'] ?? null, 'The last sub-month second must round down.');
        self::assertSame(
            date('M j, Y · H:i', self::NOW - 2592000),
            $relatives['second_2592000_at'] ?? null,
            'Exactly thirty days must use the absolute timestamp.',
        );
        self::assertSame(
            '—',
            $relatives['old_at'] ?? null,
            'Zero / invalid timestamps must surface as the em-dash sentinel.',
        );
    }

    public function testFromIdentityMapsActiveStatusToSuccessVariant(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            ['username' => "'a'", 'status' => '10'],
            null,
        );

        self::assertSame(
            'Active',
            $view->hero->statusLabel,
            "'10' must map to the 'Active' label.",
        );
        self::assertSame(
            'success',
            $view->hero->statusVariant,
            "'10' must map to the 'success' CSS variant.",
        );
    }

    public function testFromIdentityMapsBannedStatusToDangerVariant(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            ['username' => "'a'", 'status' => '9'],
            null,
        );

        self::assertSame(
            'Banned',
            $view->hero->statusLabel,
            "'9' must map to the 'Banned' label.",
        );
        self::assertSame(
            'danger',
            $view->hero->statusVariant,
            "'9' must map to the 'danger' CSS variant.",
        );
    }

    public function testFromIdentityMapsTimestampsIntoTimestampsSection(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            [
                'id' => "'1'",
                'created_at' => '1640000000',
                'updated_at' => '1740000000',
            ],
            null,
        );

        $timestampsSection = null;

        foreach ($view->sections as $section) {
            if ($section->label === 'Timestamps') {
                $timestampsSection = $section;
            }
        }

        self::assertNotNull(
            $timestampsSection,
            "Timestamps section must surface when '_at' keys exist.",
        );
        self::assertCount(
            2,
            $timestampsSection->attributes,
            'Both timestamp rows must land in the bucket.',
        );

        foreach ($timestampsSection->attributes as $attr) {
            self::assertSame(
                UserAttribute::KIND_TIMESTAMP,
                $attr->kind,
                'Timestamp rows must carry the timestamp kind.',
            );
            self::assertNotSame(
                '',
                $attr->timestampAbs,
                'Absolute timestamp must be populated.',
            );
        }
    }

    public function testFromIdentityMapsUnknownStatusToRawValue(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            ['username' => "'a'", 'status' => "'pending'"],
            null,
        );

        self::assertSame(
            'pending',
            $view->hero->statusLabel,
            'Unknown status must show the raw value verbatim.',
        );
        self::assertSame(
            'muted',
            $view->hero->statusVariant,
            'Unknown status must fall back to the muted variant.',
        );
    }

    public function testFromIdentityPrefersUsernameAndBuildsUnicodeMonogram(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            ['username' => "'éclair'", 'name' => "'Fallback name'"],
            null,
        );

        self::assertSame(
            'éclair',
            $view->hero->username,
            'Username must take precedence when both username and name are present.',
        );
        self::assertSame(
            'É',
            $view->hero->monogram,
            'Monogram extraction and uppercasing must preserve Unicode characters.',
        );
    }

    public function testFromIdentityPreservesPartiallyQuotedAndUnquotedValues(): void
    {
        foreach (["'leading" => "'leading", "trailing'" => "trailing'", 'plain' => 'plain'] as $value => $expected) {
            $view = UserDataNormalizer::fromIdentity(['username' => $value], null);

            self::assertSame($expected, $view->hero->username, 'Only matching quote wrappers may be stripped.');
        }
    }

    public function testFromIdentityResolvesAttributeLabelsFromTheLabelMap(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            [
                'id' => "'1'",
                'username' => "'admin'",
            ],
            [
                ['attribute' => 'id', 'label' => 'User ID'],
                ['attribute' => 'username', 'label' => 'Login'],
            ],
        );

        $identitySection = $view->sections[0] ?? null;

        self::assertNotNull(
            $identitySection,
            'Identity section must be present.',
        );

        $labels = array_map(static fn(UserAttribute $a): string => $a->label, $identitySection->attributes);

        self::assertContains(
            'User ID',
            $labels,
            "Custom label map must override the default 'Id' title-case label.",
        );
        self::assertContains(
            'Login',
            $labels,
            "Custom label map must override the default 'Username' title-case label.",
        );
    }

    public function testFromIdentitySkipsEmptyValuesAsEmptyKind(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            [
                'username' => "'admin'",
                'password_reset_token' => '',
                'secret' => 'null',
                'auth_key' => "'abc'",
            ],
            null,
        );

        $securityAttributes = [];

        foreach ($view->sections as $section) {
            if ($section->label === 'Security' && $section->attributes !== []) {
                $securityAttributes = $section->attributes;
            }
        }

        self::assertSame(
            [
                ['password_reset_token', UserAttribute::KIND_EMPTY, ''],
                ['secret', UserAttribute::KIND_EMPTY, ''],
                ['auth_key', UserAttribute::KIND_SECURITY, 'abc'],
            ],
            array_map(
                static fn(UserAttribute $attribute): array => [
                    $attribute->key,
                    $attribute->kind,
                    $attribute->displayValue,
                ],
                $securityAttributes,
            ),
            'Empty values must collapse without stopping later security attributes from rendering.',
        );
    }

    public function testFromIdentityStripsSingleQuotesFromDisplayValue(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            ['username' => "'admin'"],
            null,
        );

        self::assertSame(
            'admin',
            $view->hero->username,
            'VarDumper single-quote wrapping must be stripped.',
        );
    }

    private static function freezeTime(): void
    {
        MockerState::addCondition(
            'PHPForge\\Debug\\Panel\\User',
            'time',
            [],
            self::NOW,
            true,
        );
    }
}
