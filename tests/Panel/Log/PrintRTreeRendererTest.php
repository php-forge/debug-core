<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Log;

use PHPForge\Debug\Panel\Log\{PrintRNode, PrintRTreeRenderer};
use PHPForge\Debug\Tests\Provider\PrintRTreeRendererProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see PrintRTreeRenderer} covering the collapsible dump tree markup: summary parts, visibility chips,
 * empty and recursion markers, nested blocks, and escaping.
 *
 * {@see PrintRTreeRendererProvider} for test case data providers.
 */
#[Group('panel')]
#[Group('log')]
final class PrintRTreeRendererTest extends TestCase
{
    #[DataProviderExternal(PrintRTreeRendererProvider::class, 'trees')]
    public function testRenderEmitsTreeMarkup(PrintRNode $root, string $expected): void
    {
        self::assertSame(
            $expected,
            PrintRTreeRenderer::render($root),
            'Tree markup must match exactly, every text escaped once.',
        );
    }
}
