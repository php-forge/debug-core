<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel;

use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Routing\DebugUrlGeneratorInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function http_build_query;

/**
 * Unit tests for {@see PanelRenderContext} delegating portable panel links to the active adapter.
 */
#[Group('panel')]
#[Group('routing')]
final class PanelRenderContextTest extends TestCase
{
    public function testBuildsPanelUrlWithCurrentContextByDefault(): void
    {
        $context = new PanelRenderContext(
            'request-1',
            'log',
            ['Log' => ['level' => 'error']],
            'dark',
            self::urlGenerator(),
        );

        self::assertSame(
            '/panel/request-1/log?Log%5Blevel%5D=error',
            $context->panelUrl(),
            'Panel links must receive the current tag, panel, and query parameters.',
        );
    }

    public function testBuildsPanelUrlWithExplicitTargetsAndParameters(): void
    {
        $context = new PanelRenderContext(
            'request-1',
            'log',
            ['page' => 2],
            'light',
            self::urlGenerator(),
        );

        self::assertSame(
            '/panel/request-1/db?Db%5Btype%5D=SELECT',
            $context->panelUrl('db', ['Db' => ['type' => 'SELECT']]),
            'Panel links must accept a different panel and query.',
        );
    }

    public function testReturnsCrossPanelPayloadWhenCaptured(): void
    {
        $context = new PanelRenderContext(
            'request-1',
            'timeline',
            [],
            'light',
            self::urlGenerator(),
            [
                'profiling' => ['time' => 0.125, 'entries' => []],
            ],
        );

        self::assertSame(
            ['time' => 0.125, 'entries' => []],
            $context->panelPayload('profiling'),
            'Captured sibling payloads must remain available to context-aware panels.',
        );
        self::assertNull(
            $context->panelPayload('missing'),
            'Missing sibling payloads must resolve to null.',
        );
    }

    private static function urlGenerator(): DebugUrlGeneratorInterface
    {
        return new class implements DebugUrlGeneratorInterface {
            public function panel(string $tag, string $panel, array $queryParams = []): string
            {
                $path = "/panel/{$tag}/{$panel}";

                return $queryParams === [] ? $path : $path . '?' . http_build_query($queryParams);
            }
        };
    }
}
