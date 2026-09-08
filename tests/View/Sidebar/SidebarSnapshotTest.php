<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\View\Sidebar;

use PHPForge\Debug\View\Sidebar\SidebarSnapshot;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function get_object_vars;

/**
 * Unit tests for {@see SidebarSnapshot} fluent construction defaults.
 */
#[Group('sidebar')]
final class SidebarSnapshotTest extends TestCase
{
    public function testCreateFallsBackToTheTitleAsAccessibleName(): void
    {
        self::assertSame(
            'Current request',
            SidebarSnapshot::create('Current request')->ariaLabel,
            'An omitted accessible name must reuse the section heading.',
        );
        self::assertSame(
            'Current captured request',
            SidebarSnapshot::create('Current request', 'Current captured request')->ariaLabel,
            'An explicit accessible name must win.',
        );
    }

    public function testCreateStartsFromTheNotCapturedShape(): void
    {
        self::assertSame(
            [
                'title' => 'Newest request',
                'ariaLabel' => 'Newest captured request',
                'method' => '',
                'path' => '',
                'fullUrl' => '',
                'statusCode' => 0,
                'statusVariant' => 'muted',
                'time' => '',
                'isAjax' => false,
                'isCursor' => false,
                'cursorInitTag' => '',
                'newestUrl' => '',
                'oldestUrl' => '',
                'newerUrl' => '',
                'olderUrl' => '',
                'isNewest' => true,
                'isOldest' => true,
                'hasNewer' => false,
                'hasOlder' => false,
            ],
            get_object_vars(SidebarSnapshot::create('Newest request', 'Newest captured request')),
            'A fresh card must describe a single, not-captured request.',
        );
    }

    public function testDefaultArgumentsEnableTheCursorAndKeepTheRequestSynchronous(): void
    {
        $snapshot = SidebarSnapshot::create('Newest request')
            ->withRequest('GET', '/index.php', 'http://example.test/index.php')
            ->withCursor();

        self::assertTrue(
            $snapshot->isCursor,
            'An omitted cursor flag must enable grid navigation.',
        );
        self::assertSame(
            '',
            $snapshot->cursorInitTag,
            'An omitted landing tag must stay empty.',
        );
        self::assertSame(
            '',
            $snapshot->time,
            'An omitted request time must stay empty.',
        );
        self::assertFalse(
            $snapshot->isAjax,
            'An omitted AJAX flag must stay `false`.',
        );
    }
}
