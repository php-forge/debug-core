<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\View;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function dirname;
use function ob_get_clean;
use function ob_start;

/**
 * Verifies focus-target semantics in the shared debugger shell view.
 */
#[Group('view')]
final class ShellViewTest extends TestCase
{
    public function testBareMainIsProgrammaticallyFocusable(): void
    {
        $html = self::renderShell(false);

        self::assertMatchesRegularExpression(
            '~<main\b(?=[^>]*\bid="yii-debug-main")(?=[^>]*\btabindex="-1")[^>]*>~',
            $html,
            'The bare skip-link target must accept programmatic focus.',
        );
    }

    public function testShellSkipLinkTargetsProgrammaticallyFocusableMain(): void
    {
        $html = self::renderShell(true);

        self::assertStringContainsString(
            'href="#yii-debug-main"',
            $html,
            'The skip link must retain the shared main target.',
        );
        self::assertMatchesRegularExpression(
            '~<main\b(?=[^>]*\bid="yii-debug-main")(?=[^>]*\btabindex="-1")[^>]*>~',
            $html,
            'The shell skip-link target must accept programmatic focus.',
        );
    }

    private static function renderShell(bool $useShell): string
    {
        $actionIcon = '';
        $actionLabel = 'Configuration';
        $actionTitle = 'Open configuration';
        $actionUrl = null;
        $content = '<p>Panel content</p>';
        $debugTheme = 'light';
        $historyUrl = '/debug';
        $mode = 'panel';
        $peakMemory = null;
        $phpIcon = '';
        $phpVersion = '8.3';
        $sidebar = '<nav>Panels</nav>';
        $themeIconMoon = '';
        $themeIconSun = '';
        $yiiIcon = '';
        $yiiVersion = '2.0';

        ob_start();
        require dirname(__DIR__, 2) . '/resources/views/_shell.php';

        return (string) ob_get_clean();
    }
}
