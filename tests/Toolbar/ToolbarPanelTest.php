<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Toolbar;

use PHPForge\Debug\Tests\Provider\ToolbarPanelProvider;
use PHPForge\Debug\Toolbar\{ToolbarItem, ToolbarPanel};
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

use function get_object_vars;

/**
 * Unit tests for fluent toolbar panel construction and immutable navigation and metric lists.
 *
 * {@see ToolbarPanelProvider} for test case data providers.
 */
#[Group('toolbar')]
final class ToolbarPanelTest extends TestCase
{
    public function testCreateLeavesNavigationAndMetricsUnset(): void
    {
        self::assertSame(
            [
                'id' => 'request',
                'title' => 'Request',
                'url' => null,
                'icon' => null,
                'items' => [],
            ],
            get_object_vars(ToolbarPanel::create('request', 'Request')),
            'Only the identity must be set; navigation and metrics must stay empty.',
        );
    }

    public function testFluentConstructionSerializesEveryField(): void
    {
        $panel = ToolbarPanel::create('request', 'Request')
            ->withItems([ToolbarItem::create('0'), ToolbarItem::create('')])
            ->withIcon('request')
            ->withUrl('/debug?tag=0&panel=request');

        self::assertSame(
            [
                'id' => 'request',
                'title' => 'Request',
                'url' => '/debug?tag=0&panel=request',
                'icon' => 'request',
                'items' => [
                    ['value' => '0', 'status' => 'default'],
                    ['value' => '', 'status' => 'default'],
                ],
            ],
            $panel->jsonSerialize(),
            'Serialized field order and metric order must be preserved.',
        );
    }

    public function testWithItemsReplacesRatherThanAppendsAndAllowsClearing(): void
    {
        $first = ToolbarItem::create('first');
        $second = ToolbarItem::create('second');

        $original = self::sample([$first]);

        $items = [$second, $first];

        $modified = $original->withItems($items);
        $cleared = $modified->withItems([]);

        $items[] = ToolbarItem::create('later');

        self::assertNotSame(
            $original,
            $modified,
            'Replacing metrics must return a copy.',
        );
        self::assertNotSame(
            $modified,
            $cleared,
            'Clearing metrics must return a copy.',
        );
        self::assertSame(
            [$first],
            $original->items,
            'Original metrics must remain intact.',
        );
        self::assertSame(
            [$second, $first],
            $modified->items,
            'The replacement list must retain its own order.',
        );
        self::assertSame(
            self::sample()->jsonSerialize(),
            $cleared->jsonSerialize(),
            'Clearing metrics must retain navigation and serialize an empty list.',
        );
    }

    #[DataProviderExternal(ToolbarPanelProvider::class, 'nullableValues')]
    public function testWithNavigationPreservesOriginalAndOtherFields(string|null $value): void
    {
        $original = self::sample([ToolbarItem::create('0')]);

        $before = get_object_vars($original);

        foreach (['url' => $original->withUrl($value), 'icon' => $original->withIcon($value)] as $field => $modified) {
            $expected = $before;
            $expected[$field] = $value;

            self::assertNotSame(
                $original,
                $modified,
                'Configuration must return a distinct panel.',
            );
            self::assertSame(
                $before,
                get_object_vars($original),
                'Configuration must leave the original unchanged.',
            );
            self::assertSame(
                $expected,
                get_object_vars($modified),
                'Configuration must preserve every other field.',
            );

            $payload = $modified->jsonSerialize();

            if ($value === null) {
                self::assertArrayNotHasKey(
                    $field,
                    $payload,
                    'Only null navigation fields must be omitted.',
                );
            } else {
                self::assertSame(
                    $value,
                    $payload[$field] ?? null,
                    'Empty and zero strings must remain present.',
                );
            }
        }
    }

    /**
     * Returns a navigable panel carrying the given metrics.
     *
     * @param list<ToolbarItem> $items Panel metrics.
     */
    private static function sample(array $items = []): ToolbarPanel
    {
        return ToolbarPanel::create('request', 'Request')
            ->withUrl('/debug')
            ->withIcon('request')
            ->withItems($items);
    }
}
