<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\View\Sidebar;

use PHPForge\Debug\Tests\Support\RequestSummaryFixture;
use PHPForge\Debug\View\Sidebar\{SidebarNavigation, SidebarSnapshot};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function get_object_vars;
use function mktime;

/**
 * Unit tests for {@see SidebarSnapshot} deriving the card from a capture summary and for the {@see SidebarNavigation}
 * defaults.
 */
#[Group('sidebar')]
final class SidebarSnapshotTest extends TestCase
{
    public function testFromSummaryDerivesTheRequestIdentity(): void
    {
        $navigation = new SidebarNavigation(isCursor: true, cursorInitTag: 'tag-2');

        $snapshot = SidebarSnapshot::fromSummary(
            RequestSummaryFixture::create(
                [
                    'url' => 'https://example.test/orders?page=2',
                    'ajax' => true,
                    'method' => 'POST',
                    'time' => (float) mktime(8, 5, 9, 9, 24, 2026) + 0.75,
                    'statusCode' => 404,
                ],
            ),
            $navigation,
            'Newest request',
            'Newest captured request',
        );

        self::assertSame(
            [
                'title' => 'Newest request',
                'ariaLabel' => 'Newest captured request',
                'method' => 'POST',
                'path' => '/orders?page=2',
                'fullUrl' => 'https://example.test/orders?page=2',
                'statusCode' => 404,
                'statusVariant' => '4xx',
                'time' => '08:05:09',
                'isAjax' => true,
                'navigation' => $navigation,
            ],
            get_object_vars($snapshot),
            'Card fields must mirror the summary and keep the navigator.',
        );
    }

    public function testFromSummaryFallsBackToTheTitleAsAccessibleName(): void
    {
        self::assertSame(
            'Current request',
            SidebarSnapshot::fromSummary(RequestSummaryFixture::create(), new SidebarNavigation(), 'Current request')
                ->ariaLabel,
            'An omitted accessible name must reuse the section heading.',
        );
    }

    public function testFromSummaryLeavesTimeEmptyBelowOneSecond(): void
    {
        $snapshot = SidebarSnapshot::fromSummary(
            RequestSummaryFixture::create(['time' => 0.5, 'statusCode' => 0]),
            new SidebarNavigation(),
            'Current request',
        );

        self::assertSame(
            '',
            $snapshot->time,
            'A timestamp truncating to `0` must render no time.',
        );
        self::assertSame(
            'none',
            $snapshot->statusVariant,
            'An uncaptured status must use the neutral pill.',
        );
    }

    public function testNavigationDefaultsDescribeASingleCaptureWithNowhereToMove(): void
    {
        self::assertSame(
            [
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
            get_object_vars(new SidebarNavigation()),
            'Defaults must disable every navigator button.',
        );
    }
}
