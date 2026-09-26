<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\View;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function dirname;
use function ob_get_clean;
use function ob_start;

/**
 * Unit tests for the JSON fallback of the shared `snapshot.php` panel view.
 */
#[Group('view')]
final class SnapshotViewTest extends TestCase
{
    public function testJsonFallbackPayloadIsKeyboardFocusable(): void
    {
        $failure = null;
        $method = 'GET';
        $panelContent = null;
        $panelLabel = 'router';
        $payload = '{&quot;routes&quot;: []}';
        $renderError = null;
        $url = 'https://example.test/';

        ob_start();
        require dirname(__DIR__, 2) . '/resources/views/snapshot.php';
        $html = (string) ob_get_clean();

        self::assertMatchesRegularExpression(
            '~<pre\b(?=[^>]*\bclass="yii-debug-panel-payload")(?=[^>]*\btabindex="0")[^>]*>~',
            $html,
            'The scrollable payload must be reachable by keyboard.',
        );
    }
}
