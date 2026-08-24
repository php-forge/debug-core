<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Helper;

use PHPForge\Debug\Helper\CellMore;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * Unit tests for {@see CellMore} covering the collapsible clamp container, the verbatim body passthrough, and the
 * toggle wiring (delegation attribute, collapsed ARIA state, label).
 */
#[Group('helpers')]
#[Group('cell-more')]
final class CellMoreTest extends TestCase
{
    public function testClampUsesStrictSourceLengthThreshold(): void
    {
        self::assertSame(
            'content',
            CellMore::clamp('content', str_repeat('a', CellMore::THRESHOLD)),
            'Threshold-length source must remain unclamped.',
        );
        self::assertSame(
            <<<HTML
            <div class="yii-debug-cell-more">
            <div class="yii-debug-cell-more-body">
            content
            </div><button class="yii-debug-cell-more-toggle" type="button" aria-expanded="false" data-yii-debug-toggle="cell-more">Show more</button>
            </div>
            HTML,
            CellMore::clamp('content', str_repeat('a', CellMore::THRESHOLD + 1)),
            'Source beyond the threshold must be clamped.',
        );
    }

    public function testWrapEmitsCollapsedToggleWiredForDelegation(): void
    {
        $html = CellMore::wrap('body');

        self::assertSame(
            <<<HTML
            <div class="yii-debug-cell-more">
            <div class="yii-debug-cell-more-body">
            body
            </div><button class="yii-debug-cell-more-toggle" type="button" aria-expanded="false" data-yii-debug-toggle="cell-more">Show more</button>
            </div>
            HTML,
            $html,
            'Disclosure control must be a native button.',
        );
    }

    public function testWrapKeepsBodyContentVerbatimInsideClampContainer(): void
    {
        $html = CellMore::wrap('<div class="yii-debug-db-sql">SELECT 1</div>');

        self::assertSame(
            <<<HTML
            <div class="yii-debug-cell-more">
            <div class="yii-debug-cell-more-body">
            <div class="yii-debug-db-sql">SELECT 1</div>
            </div><button class="yii-debug-cell-more-toggle" type="button" aria-expanded="false" data-yii-debug-toggle="cell-more">Show more</button>
            </div>
            HTML,
            $html,
            'Body container class must be present.',
        );
    }
}
