<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Log;

use PHPForge\Debug\Panel\Log\{DumpNode, DumpTreeRenderer};
use PHPForge\Debug\Tests\Provider\DumpTreeRendererProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see DumpTreeRenderer} covering the collapsible dump tree markup: summary parts, visibility chips,
 * empty and recursion markers, nested blocks, typed values, enum case references, scalar roots, the root expansion
 * threshold, and escaping.
 *
 * {@see DumpTreeRendererProvider} for test case data providers.
 */
#[Group('panel')]
#[Group('log')]
final class DumpTreeRendererTest extends TestCase
{
    #[DataProviderExternal(DumpTreeRendererProvider::class, 'expansion')]
    public function testRenderCollapsesRootAboveExpansionThreshold(DumpNode $root, string $expected): void
    {
        self::assertSame(
            $expected,
            DumpTreeRenderer::render($root),
            'Root must start open up to 200 values, collapsed above.',
        );
    }

    #[DataProviderExternal(DumpTreeRendererProvider::class, 'trees')]
    public function testRenderEmitsTreeMarkup(DumpNode $root, string $expected): void
    {
        self::assertSame(
            $expected,
            DumpTreeRenderer::render($root),
            'Tree markup must match exactly, every text escaped once.',
        );
    }

    #[DataProviderExternal(DumpTreeRendererProvider::class, 'roots')]
    public function testRenderEmitsValueMarkupForLeafRoot(DumpNode $root, string $expected): void
    {
        self::assertSame(
            $expected,
            DumpTreeRenderer::render($root),
            'Leaf root must render as its value markup alone.',
        );
    }
}
