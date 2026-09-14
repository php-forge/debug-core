<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\User;

use PHPForge\Debug\{ColumnStyle, Panel, PanelView, Tone};

use function count;
use function date;
use function is_array;
use function is_string;
use function sprintf;
use function strtolower;

/**
 * Presents the authenticated identity, its attributes grouped by section, and the RBAC roles and permissions.
 *
 * @phpstan-import-type BadgeInline from PanelView
 */
final class UserPanel extends Panel
{
    /**
     * @var string Icon key shared with the built-in User navigation entry.
     */
    protected const string ICON = UserMessage::ID->value;
    /**
     * @var string Stable identifier associating the panel with the captured identity payload.
     */
    protected const string ID = UserMessage::ID->value;
    /**
     * @var string Panel title used in the debugger navigation.
     */
    protected const string TITLE = UserMessage::TITLE->value;
    /**
     * @var array<string, Tone> Tone applied to each status variant the normalizer resolves.
     */
    private const array STATUS_TONES = [
        'success' => Tone::SUCCESS,
        'danger' => Tone::DANGER,
        'muted' => Tone::MUTED,
    ];

    /**
     * Builds the panel view from the decoded identity capture.
     *
     * @param array<string, mixed> $data Decoded panel payload with a `data` key holding the tagged identity.
     *
     * @return PanelView Identity overview, attribute sections, and the RBAC tables.
     */
    public function present(array $data): PanelView
    {
        $payload = UserSnapshot::fromArray($data, '$.user')->data();

        $identity = $payload['identity'] ?? null;

        if (is_array($identity) === false || $identity === []) {
            return PanelView::create()
                ->active(false)
                ->emptyState(
                    UserMessage::EMPTY_HEADLINE->value,
                    UserMessage::EMPTY_EXPLANATION->value,
                    [
                        UserMessage::EMPTY_SIGN_IN->value,
                        PanelView::code(UserMessage::EMPTY_IDENTITY->value),
                        UserMessage::EMPTY_RESOLVES->value,
                    ],
                );
        }

        $view = self::identity(self::strings($identity), $payload['attributes'] ?? null);
        $view = self::rbac($view, UserMessage::ROLES, $payload['roles'] ?? null);

        return self::rbac($view, UserMessage::PERMISSIONS, $payload['permissions'] ?? null);
    }

    /**
     * Renders one attribute row according to the kind the normalizer resolved.
     *
     * @param UserAttribute $attribute Typed attribute row.
     *
     * @return mixed Inline value describing the attribute.
     */
    private static function attribute(UserAttribute $attribute): mixed
    {
        return match ($attribute->kind) {
            UserAttribute::KIND_EMPTY => UserMessage::PLACEHOLDER->value,
            UserAttribute::KIND_SECURITY => PanelView::preview($attribute->displayValue),
            UserAttribute::KIND_TIMESTAMP => self::timestampLabel($attribute),
            default => $attribute->displayValue,
        };
    }

    /**
     * Builds the identity overview and one section per attribute bucket.
     *
     * @param array<string, string> $identity Captured identity attributes.
     * @param mixed $attributes Optional label map captured alongside the identity.
     *
     * @return PanelView View carrying the identity overview and its attribute sections.
     */
    private static function identity(array $identity, mixed $attributes): PanelView
    {
        $labels = is_array($attributes) ? self::labels($attributes) : null;

        $identityView = UserDataNormalizer::fromIdentity(
            $identity,
            $labels,
        );

        $hero = $identityView->hero;

        $view = PanelView::create()
            ->summary('', $hero->username)
            ->toolbar(UserMessage::TITLE->value, $hero->username)
            ->overview(
                [
                    UserMessage::TITLE->value => $hero->username,
                    UserMessage::EMAIL->value => $hero->email === ''
                        ? UserMessage::PLACEHOLDER->value
                        : $hero->email,
                    UserMessage::USER_ID->value => $hero->idValue === ''
                        ? UserMessage::PLACEHOLDER->value
                        : $hero->idValue,
                    UserMessage::STATUS->value => self::status($hero),
                ],
                true,
            );

        foreach ($identityView->sections as $section) {
            $fields = [];

            foreach ($section->attributes as $attribute) {
                $fields[$attribute->label] = self::attribute($attribute);
            }

            $view = $view
                ->heading($section->label, true)
                ->overview($fields, true);
        }

        return $view;
    }

    /**
     * Narrows the captured label map into the shape the normalizer accepts.
     *
     * @param array<array-key, mixed> $attributes Captured label map.
     *
     * @return list<array{attribute: string, label: string}> Label definitions in capture order.
     */
    private static function labels(array $attributes): array
    {
        $labels = [];

        foreach ($attributes as $entry) {
            if (is_array($entry) === false) {
                continue;
            }

            $attribute = $entry['attribute'] ?? null;
            $label = $entry['label'] ?? null;

            if (is_string($attribute) && is_string($label)) {
                $labels[] = ['attribute' => $attribute, 'label' => $label];
            }
        }

        return $labels;
    }

    /**
     * Appends one RBAC section, explaining the absence when the auth manager exposed no item.
     *
     * @param PanelView $view View to extend.
     * @param UserMessage $label Section label, either roles or permissions.
     * @param mixed $rows Captured RBAC rows, or `null` when the auth manager exposed none.
     *
     * @return PanelView View completed with the RBAC section.
     */
    private static function rbac(PanelView $view, UserMessage $label, mixed $rows): PanelView
    {
        $items = [];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $items[] = UserRbacRow::fromArray(is_array($row) ? $row : []);
            }
        }

        $view = $view->heading(
            sprintf(UserMessage::RBAC_HEADING->value, $label->value, count($items)),
            true,
        );

        if ($items === []) {
            return $view->paragraph(
                sprintf(UserMessage::RBAC_EMPTY->value, strtolower($label->value)),
            );
        }

        $table = [];

        foreach ($items as $index => $item) {
            $table[] = [
                $index + 1,
                $item->name === '' ? UserMessage::PLACEHOLDER->value : $item->name,
                $item->description === '' ? UserMessage::PLACEHOLDER->value : $item->description,
                $item->ruleName === '' ? UserMessage::PLACEHOLDER->value : $item->ruleName,
                $item->data === '' ? UserMessage::PLACEHOLDER->value : $item->data,
                self::timestamp($item->createdAt),
                self::timestamp($item->updatedAt),
            ];
        }

        return $view->table(
            [
                UserMessage::NUMBER->value,
                UserMessage::NAME->value,
                UserMessage::DESCRIPTION->value,
                UserMessage::RULE->value,
                UserMessage::DATA->value,
                UserMessage::CREATED->value,
                UserMessage::UPDATED->value,
            ],
            $table,
            true,
            [
                0 => ColumnStyle::NUMBER,
                1 => ColumnStyle::IDENTIFIER,
                3 => ColumnStyle::IDENTIFIER,
                4 => ColumnStyle::MONOSPACE,
                5 => ColumnStyle::IDENTIFIER,
                6 => ColumnStyle::IDENTIFIER,
            ],
        );
    }

    /**
     * Builds the badge describing the captured account status.
     *
     * The normalizer only resolves the three variants listed, so the fallback only satisfies the type checker.
     *
     * @param UserIdentityHero $hero Typed identity header.
     *
     * @return BadgeInline Badge carrying the status label and tone.
     */
    private static function status(UserIdentityHero $hero): array
    {
        $label = $hero->statusLabel === '' ? UserMessage::STATUS_UNKNOWN->value : $hero->statusLabel;

        return PanelView::badge($label, self::STATUS_TONES[$hero->statusVariant] ?? Tone::MUTED);
    }

    /**
     * Narrows the captured identity map to string values.
     *
     * @param array<array-key, mixed> $identity Captured identity attributes.
     *
     * @return array<string, string> Identity attributes narrowed to strings.
     */
    private static function strings(array $identity): array
    {
        $values = [];

        foreach ($identity as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    /**
     * Formats an RBAC timestamp, falling back to the placeholder when the item recorded none.
     *
     * @param int|null $timestamp Unix-epoch second, or `null` when not recorded.
     *
     * @return string Formatted timestamp, or the placeholder.
     */
    private static function timestamp(int|null $timestamp): string
    {
        return $timestamp === null
            ? UserMessage::PLACEHOLDER->value
            : date(UserMessage::DATE_FORMAT->value, $timestamp);
    }

    /**
     * Joins the absolute and relative forms of a captured timestamp.
     *
     * Past the relative scale the normalizer already falls back to the absolute form, so the two forms are joined
     * only while they differ.
     *
     * @param UserAttribute $attribute Typed timestamp attribute.
     *
     * @return string Absolute form, optionally followed by the relative one.
     */
    private static function timestampLabel(UserAttribute $attribute): string
    {
        $absolute = $attribute->timestampAbs;
        $relative = $attribute->timestampRel;

        return $relative === $absolute
            ? $absolute
            : sprintf(UserMessage::TIMESTAMP_JOIN->value, $absolute, $relative);
    }
}
