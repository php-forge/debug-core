<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Request;

use PHPForge\Debug\Panel\Request\RequestDiagnosticLedger;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see RequestDiagnosticLedger} covering the filterable row shell and its optional modifier.
 */
#[Group('panel')]
#[Group('request')]
final class RequestDiagnosticLedgerTest extends TestCase
{
    public function testRenderWrapsRowsInTheSharedLedger(): void
    {
        self::assertSame(
            <<<HTML
            <dl class="yii-debug-diagnostic-ledger yii-debug-server-ledger">
            <div class="yii-debug-diagnostic-row" data-yii-debug-filter-row="true">
            <dt>
            Accept
            </dt><dd>
            text/html
            </dd>
            </div>
            </dl>
            HTML,
            RequestDiagnosticLedger::render(
                'yii-debug-server-ledger',
                RequestDiagnosticLedger::row('Accept', 'text/html'),
            ),
            'Ledger must carry the shared class plus the pane modifier.',
        );
    }

    public function testRowAppendsTheModifierAfterTheSharedRowClass(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-diagnostic-row yii-debug-header-raw-row" data-yii-debug-filter-row="true">
            <dt>
            Raw header line 0
            </dt><dd>
            HTTP/1.1 200 OK
            </dd>
            </div>
            HTML,
            RequestDiagnosticLedger::row('Raw header line 0', 'HTTP/1.1 200 OK', 'yii-debug-header-raw-row')->render(),
            'Modifier must follow the shared row class.',
        );
    }

    public function testRowStaysFilterableWithoutAModifier(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-diagnostic-row" data-yii-debug-filter-row="true">
            <dt>
            Accept
            </dt><dd>
            text/html
            </dd>
            </div>
            HTML,
            RequestDiagnosticLedger::row('Accept', 'text/html')->render(),
            'Row must carry the filter hook and the shared class only.',
        );
    }
}
