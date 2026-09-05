<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Toolbar;

use PHPForge\Debug\Toolbar\{ToolbarItem, ToolbarPanel};
use PHPUnit\Framework\Attributes\{DataProvider, Group};
use PHPUnit\Framework\TestCase;

use function get_object_vars;

/**
 * Unit tests for fluent toolbar panel construction and immutable navigation and metric lists.
 */
#[Group('toolbar')]
final class ToolbarPanelTest extends TestCase
{
    /**
     * @return iterable<string, array{string|null}>
     */
    public static function nullableValues(): iterable
    {
        yield 'clear' => [null];
        yield 'empty' => [''];
        yield 'unchanged icon' => ['request'];
        yield 'unchanged url' => ['/debug'];
        yield 'zero' => ['0'];
    }

    public function testCreateMatchesConstructorDefaults(): void
    {
        self::assertEquals(
            new ToolbarPanel('request', 'Request'),
            ToolbarPanel::create('request', 'Request'),
            'Factory defaults must match the constructor.',
        );
    }

    public function testFluentConstructionMatchesLegacyPayload(): void
    {
        $items = [new ToolbarItem('0'), new ToolbarItem('')];

        $panel = ToolbarPanel::create('request', 'Request')
            ->withItems($items)
            ->withIcon('request')
            ->withUrl('/debug?tag=0&panel=request');

        self::assertSame(
            (new ToolbarPanel('request', 'Request', '/debug?tag=0&panel=request', 'request', $items))
                ->jsonSerialize(),
            $panel->jsonSerialize(),
            'Fluent construction must preserve serialized fields and metric order.',
        );
    }

    public function testWithItemsReplacesRatherThanAppendsAndAllowsClearing(): void
    {
        $first = new ToolbarItem('first');
        $second = new ToolbarItem('second');
        $original = new ToolbarPanel('request', 'Request', '/debug', 'request', [$first]);

        $items = [$second, $first];
        $modified = $original->withItems($items);
        $cleared = $modified->withItems([]);

        $items[] = new ToolbarItem('later');

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
            (new ToolbarPanel('request', 'Request', '/debug', 'request'))->jsonSerialize(),
            $cleared->jsonSerialize(),
            'Clearing metrics must retain navigation and serialize an empty list.',
        );
    }

    #[DataProvider('nullableValues')]
    public function testWithNavigationPreservesOriginalAndOtherFields(string|null $value): void
    {
        $original = new ToolbarPanel('request', 'Request', '/debug', 'request', [new ToolbarItem('0')]);

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
}
