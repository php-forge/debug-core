<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\View\Grid;

use PHPForge\Debug\View\Grid\RowClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see RowClass} covering the status-level to row-class mapping used by the debug grids.
 */
#[Group('view')]
#[Group('grid')]
final class RowClassTest extends TestCase
{
    public function testForAliasesErrorToDanger(): void
    {
        self::assertSame(
            ['class' => 'yii-debug-row-danger'],
            RowClass::for('error'),
            "The 'error' level must alias to the danger class.",
        );
    }

    public function testForMapsKnownLevelsToRowClasses(): void
    {
        self::assertSame(
            ['class' => 'yii-debug-row-success'],
            RowClass::for('success'),
            'Success must map.',
        );
        self::assertSame(
            ['class' => 'yii-debug-row-info'],
            RowClass::for('info'),
            'Info must map.');
        self::assertSame(
            ['class' => 'yii-debug-row-warning'],
            RowClass::for('warning'),
            'Warning must map.',
        );
        self::assertSame(
            ['class' => 'yii-debug-row-danger'],
            RowClass::for('danger'),
            'Danger must map.',
        );
    }

    public function testForReturnsEmptyArrayForUnknownOrNullLevels(): void
    {
        self::assertSame(
            [],
            RowClass::for(null), "'null' must yield no class.",
        );
        self::assertSame(
            [],
            RowClass::for(''),
            'Empty string must yield no class.',
        );
        self::assertSame(
            [],
            RowClass::for('primary'),
            'Unknown levels must yield no class.',
        );
    }
}
