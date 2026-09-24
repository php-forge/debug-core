<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\View\Grid;

use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Tests\Support\DebugUrlGeneratorFixture;
use PHPForge\Debug\View\Grid\{SortHeader, SortState};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function range;

/**
 * Unit tests for {@see SortState} and {@see SortHeader} covering query parsing, row ordering, and header links.
 */
#[Group('view')]
#[Group('grid')]
final class SortStateTest extends TestCase
{
    public function testApplyBreaksTiesWithTheDirectionIndependentComparator(): void
    {
        $rows = [['n' => 1, 'id' => 'b'], ['n' => 2, 'id' => 'c'], ['n' => 1, 'id' => 'a']];

        self::assertSame(
            [['n' => 2, 'id' => 'c'], ['n' => 1, 'id' => 'a'], ['n' => 1, 'id' => 'b']],
            SortState::fromQuery('-n', ['n'], 'n')->apply(
                $rows,
                static fn(array $left, array $right): int => $left['n'] <=> $right['n'],
                static fn(array $left, array $right): int => $left['id'] <=> $right['id'],
            ),
            'Descending order must not invert the tie-break.',
        );
    }

    public function testApplyKeepsTheInputOrderOfEqualRowsWithoutTieBreak(): void
    {
        // Seventeen rows exceed the insertion-sort threshold of `usort()`, where a non-zero tie result reorders them.
        $rows = [];

        foreach (range(0, 16) as $id) {
            $rows[] = ['n' => 1, 'id' => $id];
        }

        self::assertSame(
            $rows,
            SortState::fromQuery('-n', ['n'], 'n')->apply(
                $rows,
                static fn(array $left, array $right): int => $left['n'] <=> $right['n'],
            ),
            'Equal rows must keep their relative order.',
        );
    }

    public function testFromQueryFallsBackToTheDefaultForAnUnknownAttribute(): void
    {
        $state = SortState::fromQuery('-color', ['time', 'level'], 'time', 'desc');

        self::assertSame(
            'time',
            $state->attribute,
            'Unknown attribute must yield the default attribute.',
        );
        self::assertSame(
            'desc',
            $state->direction,
            'Default direction must be paired with the default attribute.',
        );
    }

    public function testFromQueryFallsBackToTheDefaultWhenTheValueIsMissing(): void
    {
        $state = SortState::fromQuery(null, ['time'], 'time');

        self::assertSame(
            'time',
            $state->attribute,
            'Missing value must yield the default attribute.',
        );
        self::assertSame(
            'asc',
            $state->direction,
            'Omitted default direction must be ascending.',
        );
    }

    public function testFromQueryReadsTheDescendingPrefix(): void
    {
        $state = SortState::fromQuery('-level', ['time', 'level'], 'time');

        self::assertSame(
            'level',
            $state->attribute,
            'Prefix must be stripped from the attribute.',
        );
        self::assertSame(
            'desc',
            $state->direction,
            'A `-` prefix must request a descending order.',
        );
    }

    public function testHeaderAnnouncesTheActiveOrderAndLinksToTheReverseOne(): void
    {
        $url = static fn(string $sort): string => "/panel?sort={$sort}";

        $descending = SortState::fromQuery('-time', ['time'], 'time')->header('time', 'Time', $url);
        $ascending = SortState::fromQuery('time', ['time'], 'time')->header('time', 'Time', $url);

        self::assertSame(
            '<a class="desc" href="/panel?sort=time">Time</a>',
            (string) $descending,
            'Active descending column must link to the ascending order.',
        );
        self::assertSame(
            ['aria-sort' => 'descending'],
            $descending->attributes,
            "Active descending column must announce 'descending'.",
        );
        self::assertSame(
            '<a class="asc" href="/panel?sort=-time">Time</a>',
            $ascending->link,
            'Active ascending column must link to the descending order.',
        );
        self::assertSame(
            ['aria-sort' => 'ascending'],
            $ascending->attributes,
            "Active ascending column must announce 'ascending'.",
        );
    }

    public function testHeaderOfAnInactiveColumnRequestsItsFirstOrderWithoutAnnouncingOne(): void
    {
        $state = SortState::fromQuery('time', ['time', 'level'], 'time');

        $url = static fn(string $sort): string => "/panel?sort={$sort}";

        $ascendingFirst = $state->header('level', 'Level', $url);
        $descendingFirst = $state->header('level', 'Level', $url, true);

        self::assertSame(
            '<a href="/panel?sort=level">Level</a>',
            (string) $ascendingFirst,
            'First click must request an ascending order by default.',
        );
        self::assertSame(
            [],
            $ascendingFirst->attributes,
            'Inactive column must announce no order.',
        );
        self::assertSame(
            '<a href="/panel?sort=-level">Level</a>',
            (string) $descendingFirst,
            'Descending-first column must request a descending order.',
        );
    }

    public function testPanelUrlMergesTheSortValueIntoTheVisibleQuery(): void
    {
        $context = new PanelRenderContext('request-1', 'log', ['page' => 3], 'light', new DebugUrlGeneratorFixture());

        $url = SortState::panelUrl($context, ['Log' => ['level' => 'error'], 'sort' => 'time']);

        self::assertSame(
            '/panel/request-1/log?Log%5Blevel%5D=error&sort=-time',
            $url('-time'),
            'Sort value must replace the one of the visible page.',
        );
    }
}
