<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\View\Grid;

use PHPForge\Debug\Panel\PanelTitle;
use PHPForge\Debug\View\Grid\PanelHeading;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see PanelHeading} covering the visually hidden page heading of a panel.
 */
#[Group('view')]
#[Group('grid')]
final class PanelHeadingTest extends TestCase
{
    public function testRenderHidesTheTitleVisuallyInALevelOneHeading(): void
    {
        self::assertSame(
            <<<HTML
            <h1 class="yii-debug-sr-only">
            Log Messages
            </h1>
            HTML,
            PanelHeading::render(PanelTitle::LOG_MESSAGES),
            "Heading must be an 'h1' with the screen-reader-only class.",
        );
    }
}
