<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\View\Grid;

use PHPForge\Debug\View\Grid\FilterInput;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see FilterInput} covering the labelled dropdown and text controls of a grid filter row.
 */
#[Group('view')]
#[Group('grid')]
final class FilterInputTest extends TestCase
{
    public function testSelectListsAnEmptyChoiceThenEveryOptionAndMarksTheActiveOne(): void
    {
        self::assertSame(
            <<<HTML
            <select class="yii-debug-select" name="Log[level]" aria-label="Filter by Level">
            <option>
            </option>
            <option value="error" selected>
            Error
            </option>
            <option value="3">
            Info
            </option>
            </select>
            HTML,
            FilterInput::select('Log', 'level', 'Level', ['level' => 'error'], ['error' => 'Error', 3 => 'Info'])
                ->render(),
            'Dropdown must carry the group name, the label, and the active value.',
        );
    }

    public function testTextCarriesTheActiveValueAndTheRequestedClass(): void
    {
        self::assertSame(
            '<input class="yii-debug-input" name="Log[category]" type="text" value="app" '
            . 'aria-label="Filter by Category">',
            FilterInput::text('Log', 'category', 'Category', ['category' => 'app'])->render(),
            'Text input must default to the shared class and keep the value.',
        );
        self::assertSame(
            '<input class="yii-debug-input-sm" name="Log[category]" type="text" aria-label="Filter by Category">',
            FilterInput::text('Log', 'category', 'Category', [], 'yii-debug-input-sm')->render(),
            'An inactive filter must render no value.',
        );
    }
}
