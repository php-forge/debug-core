<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Helper;

use PHPForge\Debug\Helper\Fqcn;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see Fqcn} covering the fragment anchor and the shared two-tone label markup: namespace/short-name
 * splitting, method-suffix handling, the plain-value and empty-value fallbacks, and the `title` attribute.
 */
#[Group('helpers')]
#[Group('fqcn')]
final class FqcnTest extends TestCase
{
    public function testAnchorDistinguishesNamesThatShareASlug(): void
    {
        self::assertSame(
            'app-a-b-e8f13edb',
            Fqcn::anchor('app\\A_B'),
            'Each separator run must collapse to one dash.',
        );
        self::assertSame(
            'app-a-b-91495c5b',
            Fqcn::anchor('app\\A__b'),
            'A shared slug must still carry its own digest.',
        );
        self::assertNotSame(
            Fqcn::anchor('app\\A_B'),
            Fqcn::anchor('app\\A__b'),
            'Distinct classes must never share an anchor.',
        );
    }

    public function testAnchorDropsTheNamespaceForAGlobalClass(): void
    {
        self::assertSame(
            'globalasset-762af4c5',
            Fqcn::anchor('GlobalAsset'),
            'A class outside any namespace must slug alone.',
        );
    }

    public function testAnchorReturnsTheDigestAloneForASeparatorOnlyName(): void
    {
        self::assertSame(
            'ebf29a14',
            Fqcn::anchor('\\_\\'),
            'An empty slug must leave the digest unprefixed.',
        );
    }

    public function testAnchorSharesOneValueAcrossCaseVariantsOfOneClass(): void
    {
        self::assertSame(
            'app-service-mailer-c39fd4a3',
            Fqcn::anchor('App\\Service\\Mailer'),
            'The anchor must be lowercase end to end.',
        );
        self::assertSame(
            Fqcn::anchor('App\\Service\\Mailer'),
            Fqcn::anchor('app\\service\\mailer'),
            'Case variants of one class must share an anchor.',
        );
    }

    public function testAnchorSlugsANamespacedClassAndAppendsItsDigest(): void
    {
        self::assertSame(
            'app-assets-appasset-3c6a8113',
            Fqcn::anchor('app\\assets\\AppAsset'),
            'Separators must give way to a dash-joined slug.',
        );
    }

    public function testNamespacePartReturnsPrefixOrEmptyString(): void
    {
        self::assertSame(
            'App\\Service',
            Fqcn::namespacePart('App\\Service\\Mailer'),
            'Namespace prefix must be returned.',
        );
        self::assertSame(
            '',
            Fqcn::namespacePart('Mailer'),
            'Unqualified class name must have no namespace prefix.',
        );
    }

    public function testRenderLabelExposesFullValueInTitleAttribute(): void
    {
        self::assertSame(
            <<<HTML
            <span title="yii\base\Event"><span class="yii-debug-muted">yii\base\</span><wbr><strong>Event</strong></span>
            HTML,
            Fqcn::renderLabel('yii\\base\\Event'),
            'Full value must sit in the `title` attribute.',
        );
    }

    public function testRenderLabelKeepsMethodSuffixInsideStrongShortName(): void
    {
        $label = Fqcn::renderLabel('yii\\db\\Command::query');

        self::assertSame(
            <<<HTML
            <span title="yii\db\Command::query"><span class="yii-debug-muted">yii\db\</span><wbr><strong>Command::query</strong></span>
            HTML,
            $label,
            'Method pair must render bold as one segment.',
        );

    }

    public function testRenderLabelOmitsMutedPrefixForPlainValues(): void
    {
        $label = Fqcn::renderLabel('application');

        self::assertSame(
            <<<HTML
            <span title="application"><strong>application</strong></span>
            HTML,
            $label,
            'Plain value must render bold.',
        );

    }

    public function testRenderLabelRendersEmDashForEmptyValue(): void
    {
        self::assertSame(
            '—',
            Fqcn::renderLabel(''),
            'Empty value must collapse to an em dash.',
        );
    }

    public function testRenderLabelSplitsNamespacedValueIntoMutedNamespaceAndStrongShortName(): void
    {
        $label = Fqcn::renderLabel('yii\\base\\Event');

        self::assertSame(
            <<<HTML
            <span title="yii\base\Event"><span class="yii-debug-muted">yii\base\</span><wbr><strong>Event</strong></span>
            HTML,
            $label,
            'Namespaced labels must keep the namespace, break opportunity, and short name in display order.',
        );
        self::assertSame(
            <<<HTML
            <span title="yii\base\Event"><span class="yii-debug-muted">yii\base\</span><wbr><strong>Event</strong></span>
            HTML,
            $label,
            'Namespace prefix must render muted.',
        );

    }

    public function testShortNameReturnsFinalSegmentOrOriginalValue(): void
    {
        self::assertSame(
            'Mailer',
            Fqcn::shortName('App\\Service\\Mailer'),
            'Final class-name segment must be returned.',
        );
        self::assertSame(
            'Mailer',
            Fqcn::shortName('Mailer'),
            'Unqualified class name must be preserved.',
        );
    }
}
