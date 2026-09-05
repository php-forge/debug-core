<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Tests\Toolbar\ToolbarItemTest;

/**
 * Provides nullable metric options and status values for {@see ToolbarItemTest}.
 */
final class ToolbarItemProvider
{
    /**
     * @return iterable<string, array{string|null}>
     */
    public static function nullableValues(): iterable
    {
        yield 'clear' => [null];
        yield 'empty' => [''];
        yield 'raw' => ['<raw>&value'];
        yield 'zero' => ['0'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function statusValues(): iterable
    {
        yield 'empty' => [''];
        yield 'unchanged' => ['success'];
        yield 'zero' => ['0'];
    }
}
