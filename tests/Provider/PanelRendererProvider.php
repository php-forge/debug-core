<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Helper\CellMore;
use PHPForge\Debug\Tests\Panel\PanelRendererTest;

/**
 * Data provider for {@see PanelRendererTest} test cases.
 */
final class PanelRendererProvider
{
    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function nonCollapsingTables(): iterable
    {
        yield 'above the threshold without opt-in' => [CellMore::ROW_THRESHOLD + 1, false];
        yield 'opted in at the threshold' => [CellMore::ROW_THRESHOLD, true];
    }
}
