<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel;

use PHPForge\Debug\Helper\Icon;
use PHPForge\Debug\Panel\PanelIcon;
use PHPUnit\Framework\TestCase;

use function count;

/**
 * Verifies stable built-in panel icon keys and their bundled SVG resources.
 */
final class PanelIconTest extends TestCase
{
    public function testIconKeysPreserveExistingSvgResources(): void
    {
        $keys = [
            'ASSETS' => 'asset',
            'CONFIGURATION' => 'config',
            'DATABASE' => 'db',
            'DUMP' => 'dump',
            'EVENTS' => 'events',
            'LOGS' => 'logs',
            'MAIL' => 'mail',
            'PROFILING' => 'profiling',
            'QUEUE' => 'queue',
            'REQUEST' => 'request',
            'ROUTER' => 'router',
            'TIMELINE' => 'timeline',
            'USER' => 'user',
        ];

        self::assertCount(
            count($keys),
            PanelIcon::cases(),
            'Every built-in icon must have a stable key assertion.',
        );

        foreach (PanelIcon::cases() as $icon) {
            self::assertSame(
                $keys[$icon->name],
                $icon->value,
                'Existing panel icon keys must remain unchanged.',
            );
            self::assertStringContainsString(
                '<svg',
                Icon::render($icon->value),
                'Every built-in panel icon must resolve to a bundled SVG.',
            );
        }
    }
}
