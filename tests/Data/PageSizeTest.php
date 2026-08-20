<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Data;

use PHPForge\Debug\Data\PageSize;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see PageSize} covering the `per-page` resolution semantics and the shared selector markup.
 */
#[Group('data')]
#[Group('pagination')]
final class PageSizeTest extends TestCase
{
    public function testCurrentFallsBackToTheDefaultWhenNoValueIsSupplied(): void
    {
        self::assertSame(
            '50',
            PageSize::current(null),
            'Missing values must fall back to the default.',
        );
        self::assertSame(
            'all',
            PageSize::current('all'),
            'Raw values must pass through untouched.',
        );
        self::assertSame(
            'all',
            PageSize::current('ALL'),
            "The 'all' keyword must canonicalize to lowercase.",
        );
        self::assertSame(
            '25',
            PageSize::current(null, 25),
            'A custom default must surface as a string.',
        );
    }

    public function testResolveCapsNumericValuesAtTheMaximum(): void
    {
        self::assertSame(
            1000,
            PageSize::resolve('5000'),
            'Values above the cap must clamp to `1000`.',
        );
        self::assertSame(
            25,
            PageSize::resolve('25'),
            'Valid numeric values must pass through.',
        );
    }

    public function testResolveFallsBackToTheDefaultForMissingOrInvalidValues(): void
    {
        self::assertSame(
            50,
            PageSize::resolve(null),
            'Missing values must resolve to the default.',
        );
        self::assertSame(
            50,
            PageSize::resolve('abc'),
            'Non-numeric values must resolve to the default.',
        );
        self::assertSame(
            50,
            PageSize::resolve('-5'),
            'Non-positive values must resolve to the default.',
        );
        self::assertSame(
            20,
            PageSize::resolve(null, 20),
            'A custom default must be honored.',
        );
    }

    public function testResolveReturnsNullForTheAllKeyword(): void
    {
        self::assertNull(
            PageSize::resolve('all'),
            "The literal 'all' must disable pagination.",
        );
        self::assertNull(
            PageSize::resolve('ALL'),
            'The keyword must match case-insensitively.',
        );
    }

    public function testSelectorHtmlMarksTheCurrentOptionSelected(): void
    {
        self::assertSame(
            <<<HTML
            <label class="yii-debug-grid-pagesize"><span class="yii-debug-grid-pagesize-label">Rows</span><select class="yii-debug-grid-pagesize-select" name="per-page" data-yii-debug-pagesize="true">
            <option value="10">
            10
            </option>
            <option value="25" selected>
            25
            </option>
            <option value="50">
            50
            </option>
            <option value="100">
            100
            </option>
            <option value="all">
            All
            </option>
            </select></label>
            HTML,
            PageSize::selectorHtml('25'),
            'The selector must render the exact JS hook, field name, labels, and selected option.',
        );
    }

    public function testSelectorHtmlSelectsCanonicalizedAllKeyword(): void
    {
        self::assertSame(
            <<<HTML
            <label class="yii-debug-grid-pagesize"><span class="yii-debug-grid-pagesize-label">Rows</span><select class="yii-debug-grid-pagesize-select" name="per-page" data-yii-debug-pagesize="true">
            <option value="10">
            10
            </option>
            <option value="25">
            25
            </option>
            <option value="50">
            50
            </option>
            <option value="100">
            100
            </option>
            <option value="all" selected>
            All
            </option>
            </select></label>
            HTML,
            PageSize::selectorHtml(PageSize::current('ALL')),
            "The canonicalized 'all' keyword must render the exact selected selector.",
        );
    }
}
