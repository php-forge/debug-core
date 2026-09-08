<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Helper;

use PHPForge\Debug\Helper\ExtensionPill;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see ExtensionPill} covering the state modifier and the optional screen-reader detail.
 */
#[Group('extension-pill')]
#[Group('helpers')]
final class ExtensionPillTest extends TestCase
{
    public function testRenderAppendsTheScreenReaderSummaryForDisabledExtensions(): void
    {
        self::assertSame(
            <<<HTML
            <span class="yii-debug-ext-pill is-off"><span class="yii-debug-ext-pill-dot" aria-hidden="true"></span><span class="yii-debug-ext-pill-label">Zend OPcache</span><span class="yii-debug-ext-pill-state">8.5</span><span class="yii-debug-sr-only">Version: 8.5</span></span>
            HTML,
            ExtensionPill::render('Zend OPcache', '8.5', false, 'Version: 8.5')->render(),
            'Disabled pills must carry `is-off` and the extra detail span.',
        );
    }

    public function testRenderOmitsTheScreenReaderSummaryForEnabledExtensions(): void
    {
        self::assertSame(
            <<<HTML
            <span class="yii-debug-ext-pill is-on"><span class="yii-debug-ext-pill-dot" aria-hidden="true"></span><span class="yii-debug-ext-pill-label">curl</span><span class="yii-debug-ext-pill-state">on</span></span>
            HTML,
            ExtensionPill::render('curl', 'on', true)->render(),
            'Enabled pills must carry `is-on` and only three spans.',
        );
    }
}
