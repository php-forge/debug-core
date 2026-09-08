<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Tests\Toolbar\ToolbarPanelTest;

/**
 * Data provider for {@see ToolbarPanelTest} test cases.
 */
final class ToolbarPanelProvider
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
}
