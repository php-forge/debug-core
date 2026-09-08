<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Helper;

use PHPForge\Debug\Helper\Table;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use UIAwesome\Html\Table\{Td, Tr};

/**
 * Unit tests for {@see Table} covering the header row, the header-less variant, and the custom shell classes.
 */
#[Group('helpers')]
#[Group('table')]
final class TableTest extends TestCase
{
    public function testBuildFallsBackToTheSharedTableClass(): void
    {
        self::assertSame(
            <<<HTML
            <table class="yii-debug-table">
            <tbody>
            </tbody>
            </table>
            HTML,
            Table::build([], [])->render(),
            'Default class must be the shared debugger table class.',
        );
    }

    public function testBuildKeepsTheSharedShellDecorableByTheCaller(): void
    {
        self::assertSame(
            <<<HTML
            <table class="yii-debug-table yii-debug-table-mono" style='table-layout: fixed;'>
            <thead>
            <tr>
            <th scope="col">
            Name
            </th>
            </tr>
            </thead><tbody>
            <tr>
            <td>
            1
            </td><td>
            home
            </td>
            </tr>
            </tbody>
            </table>
            HTML,
            Table::build(['Name'], self::rows(), 'yii-debug-table yii-debug-table-mono')
                ->style(['table-layout' => 'fixed'])
                ->render(),
            'Decorations must survive on the returned element.',
        );
    }

    public function testRenderAcceptsCustomShellClasses(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-table-wrap yii-debug-route-trace-wrap">
            <table class="yii-debug-table yii-debug-route-trace">
            <thead>
            <tr>
            <th scope="col">
            #
            </th>
            </tr>
            </thead><tbody>
            </tbody>
            </table>
            </div>
            HTML,
            Table::render(
                ['#'],
                [],
                'yii-debug-table yii-debug-route-trace',
                'yii-debug-table-wrap yii-debug-route-trace-wrap',
            ),
            'Both shell classes must reach the wrapper and the table.',
        );
    }

    public function testRenderOmitsTheHeaderRowWithoutLabels(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-table-wrap">
            <table class="yii-debug-table">
            <tbody>
            <tr>
            <td>
            1
            </td><td>
            home
            </td>
            </tr>
            </tbody>
            </table>
            </div>
            HTML,
            Table::render([], self::rows()),
            'An empty label list must skip the `thead` entirely.',
        );
    }

    public function testRenderWrapsTheHeaderAndBodyRows(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-table-wrap">
            <table class="yii-debug-table">
            <thead>
            <tr>
            <th scope="col">
            #
            </th><th scope="col">
            Route
            </th>
            </tr>
            </thead><tbody>
            <tr>
            <td>
            1
            </td><td>
            home
            </td>
            </tr>
            </tbody>
            </table>
            </div>
            HTML,
            Table::render(['#', 'Route'], self::rows()),
            'Column labels must render as scoped header cells.',
        );
    }

    /**
     * @return list<Tr>
     */
    private static function rows(): array
    {
        return [
            Tr::tag()
                ->html(
                    Td::tag()->content('1'),
                    Td::tag()->content('home'),
                ),
        ];
    }
}
