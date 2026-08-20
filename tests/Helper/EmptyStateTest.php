<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Helper;

use PHPForge\Debug\Helper\EmptyState;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use UIAwesome\Html\Flow\P;

/**
 * Unit tests for {@see EmptyState} covering the shared empty-state card: container class, headline encoding, trusted
 * body rendering, and body-element ordering.
 */
#[Group('helpers')]
#[Group('empty-state')]
final class EmptyStateTest extends TestCase
{
    public function testCardEncodesHeadlineText(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-empty-state">
            <h2>
            &lt;script&gt;alert(1)&lt;/script&gt;
            </h2>
            </div>
            HTML,
            EmptyState::card('<script>alert(1)</script>'),
            'Headline must be HTML-escaped.',
        );
    }

    public function testCardRendersBodyChildrenInOrder(): void
    {
        $card = EmptyState::card(
            'Nothing captured',
            P::tag()->content('first'),
            P::tag()->content('second'),
        );

        self::assertMatchesRegularExpression(
            '~Nothing captured.*first.*second~s',
            $card,
            'Order: headline, then body elements.',
        );
    }

    public function testCardRendersTrustedBodyMarkupVerbatim(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-empty-state">
            <h2>
            Nothing captured
            </h2><p>Trusted body</p>
            </div>
            HTML,
            EmptyState::card('Nothing captured', '<p>Trusted body</p>'),
            'Trusted body markup must be rendered verbatim.',
        );
    }

    public function testCardWrapsHeadlineInEmptyStateContainer(): void
    {
        $card = EmptyState::card('Nothing captured');

        self::assertSame(
            <<<HTML
            <div class="yii-debug-empty-state">
            <h2>
            Nothing captured
            </h2>
            </div>
            HTML,
            $card,
            'Headline must be wrapped in empty-state container.',
        );
    }
}
