<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Support\PhpExport;

use PHPForge\Debug\Tests\Support\PrintR\PrintRSuit;

/**
 * Object exported by `var_export()` as a `__set_state()` call holding a nested array, an enum case, and an empty array.
 */
final class ExportedPoint
{
    /**
     * @var list<int>
     */
    public array $empty = [];
    public PrintRSuit $suit = PrintRSuit::HEARTS;
    /**
     * @var array<string, list<string>>
     */
    public array $tags = ['roles' => ['admin', 'editor']];
    public int $x = 1;
}
