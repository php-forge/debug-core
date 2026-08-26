<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Vite;

use PHPForge\Debug\Panel\Vite\{ViteComponent, ViteSummary};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Vite component-count and aggregate-mode presentation.
 */
#[Group('panel')]
#[Group('vite')]
final class ViteSummaryTest extends TestCase
{
    public function testDifferentModesReturnMixedLabel(): void
    {
        $summary = new ViteSummary([self::component('development'), self::component('production')]);

        self::assertSame('Mixed', $summary->modeLabel(), 'Different component modes must be summarized as mixed.');
    }
    public function testEmptySummaryReportsNoComponentsAndUnknownMode(): void
    {
        $summary = new ViteSummary([]);

        self::assertTrue($summary->isEmpty(), 'An empty component list must be reported as empty.');
        self::assertSame(0, $summary->count(), 'An empty component list must have count zero.');
        self::assertSame('Unknown', $summary->modeLabel(), 'An empty component list has no reliable runtime mode.');
    }

    public function testMatchingModesReturnTheirSharedLabel(): void
    {
        $development = new ViteSummary([self::component('development'), self::component('development')]);
        $production = new ViteSummary([self::component('production')]);
        $unknown = new ViteSummary([self::component('unknown')]);

        self::assertFalse($development->isEmpty(), 'Captured components must make the summary non-empty.');
        self::assertSame(2, $development->count(), 'Every captured component must contribute to the count.');
        self::assertSame('Development', $development->modeLabel(), 'Development components need their mode label.');
        self::assertSame('Production', $production->modeLabel(), 'Production components need their mode label.');
        self::assertSame('Unknown', $unknown->modeLabel(), 'Unknown component modes must remain explicit.');
    }

    private static function component(string $mode): ViteComponent
    {
        return new ViteComponent(
            id: 'vite',
            class: 'PHPForge\Vite\Vite',
            implementation: ViteComponent::IMPLEMENTATION_MODERN,
            inspectionAvailable: true,
            mode: $mode,
            entrypoints: [],
            baseUrl: '',
            devServerUrl: null,
            manifestPath: '',
            includeViteClient: null,
            modulePreload: null,
            chunks: [],
        );
    }
}
