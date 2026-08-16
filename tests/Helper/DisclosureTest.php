<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Helper;

use PHPForge\Debug\Helper\Disclosure;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see Disclosure} rendering shared collapsible sections.
 *
 * @since 0.1
 */
#[Group('helpers')]
#[Group('disclosure')]
final class DisclosureTest extends TestCase
{
    public function testHintRendersBothStateLabelsOutsideTheAccessibilityTree(): void
    {
        $hint = Disclosure::hint()->render();

        self::assertStringContainsString(
            'aria-hidden="true"',
            $hint,
            'Hint must stay outside the accessibility tree.',
        );
        self::assertStringContainsString(
            'data-yii-debug-hint="collapsed">click to expand',
            $hint,
            'Collapsed state must invite expansion.',
        );
        self::assertStringContainsString(
            'data-yii-debug-hint="expanded">click to collapse',
            $hint,
            'Expanded state must invite collapse.',
        );
    }

    public function testRenderEncodesTitleAndKeepsBodyMarkup(): void
    {
        $html = Disclosure::render('<Payload>', '<pre>raw</pre>');

        self::assertSame(
            <<<HTML
            <details class="yii-debug-disclosure">
            <summary class="yii-debug-disclosure-summary">
            <span class="yii-debug-disclosure-title">&lt;Payload&gt;</span><span class="yii-debug-disclosure-hint" aria-hidden="true"><span data-yii-debug-hint="collapsed">click to expand</span><span data-yii-debug-hint="expanded">click to collapse</span></span>
            </summary><div class="yii-debug-disclosure-body">
            <pre>raw</pre>
            </div>
            </details>
            HTML,
            $html,
            'Disclosure must not render empty body.',
        );
    }
}
