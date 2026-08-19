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
    public function testBuildsUrlsWithCurrentContextByDefault(): void
    {
        $context = new PanelRenderContext(
            'request-1',
            'log',
            ['Log' => ['level' => 'error']],
            'dark',
            self::urlGenerator(),
        );

        self::assertSame(
            '/history?Log%5Blevel%5D=error',
            $context->historyUrl(),
            'History links must receive the current query parameters.',
        );
        self::assertSame(
            '/panel/request-1/log?Log%5Blevel%5D=error',
            $context->panelUrl(),
            'Panel links must receive the current tag, panel, and query parameters.',
        );
        self::assertSame(
            '/action/download/request-1?Log%5Blevel%5D=error',
            $context->actionUrl('download'),
            'Action links must receive the current tag and query parameters.',
        );
    }

    public function testBuildsUrlsWithExplicitTargetsAndParameters(): void
    {
        $context = new PanelRenderContext(
            'request-1',
            'log',
            ['page' => 2],
            'light',
            self::urlGenerator(),
        );

        self::assertSame(
            '/history',
            $context->historyUrl([]),
            'An explicit empty query must clear history state.',
        );
        self::assertSame(
            '/panel/request-1/db?Db%5Btype%5D=SELECT',
            $context->panelUrl('db', ['Db' => ['type' => 'SELECT']]),
            'Panel links must accept a different panel and query.',
        );
        self::assertSame(
            '/action/explain/request-1?seq=3',
            $context->actionUrl('explain', ['seq' => 3]),
            'Action links must accept an explicit query.',
        );
    }

    private static function urlGenerator(): DebugUrlGeneratorInterface
    {
        return new class implements DebugUrlGeneratorInterface {
            public function action(string $action, string $tag, array $queryParams = []): string
            {
                return self::url("/action/{$action}/{$tag}", $queryParams);
            }

            public function history(array $queryParams = []): string
            {
                return self::url('/history', $queryParams);
            }

            public function panel(string $tag, string $panel, array $queryParams = []): string
            {
                return self::url("/panel/{$tag}/{$panel}", $queryParams);
            }

            /**
             * @param array<array-key, mixed> $queryParams
             */
            private static function url(string $path, array $queryParams): string
            {
                return $queryParams === [] ? $path : $path . '?' . http_build_query($queryParams);
            }
        };
    }
}
