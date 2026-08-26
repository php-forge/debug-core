<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\View\Sidebar;

use InvalidArgumentException;
use PHPForge\Debug\View\Sidebar\SidebarView;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see SidebarView} covering navigation-group label validation.
 */
#[Group('view')]
#[Group('sidebar')]
final class SidebarViewTest extends TestCase
{
    public function testRejectsEmptyNavigationGroupLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Sidebar navigation group labels must not be empty.',
        );

        new SidebarView(
            snapshot: null,
            navItems: [],
            navGroups: [
                'Extensions' => [],
                '' => [],
            ],
        );
    }

    public function testRejectsWhitespaceOnlyNavigationGroupLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Sidebar navigation group labels must not be empty.',
        );

        new SidebarView(
            snapshot: null,
            navItems: [],
            navGroups: [
                'Extensions' => [],
                " \t\n" => [],
            ],
        );
    }
}
