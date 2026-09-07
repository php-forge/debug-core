<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\View\History;

use PHPForge\Debug\Storage\RequestSummary;
use PHPForge\Debug\View\History\CaptureLabel;
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * Tests distinct capture labels and preservation of diagnostic input for output escaping.
 */
final class CaptureLabelTest extends TestCase
{
    public function testLongUrlDoesNotTruncateTag(): void
    {
        $summary = RequestSummary::create('unique-tag')
            ->withRequest(str_repeat('x', 100), 'GET', '', 0.0);

        self::assertSame(
            'time unavailable · GET · ' . str_repeat('x', 69) . '... · unique-tag',
            CaptureLabel::fromSummary($summary),
            'Only the URL may be truncated.',
        );
    }
    public function testSameSecondCapturesRemainDistinct(): void
    {
        $first = RequestSummary::create('6a9ec2295ddcd414251546')
            ->withRequest('/same', 'GET', '', 1000.1);
        $second = RequestSummary::create('6a9ec22932a3c954772352')
            ->withRequest('/same', 'GET', '', 1000.2);

        self::assertNotSame(
            CaptureLabel::fromSummary($first),
            CaptureLabel::fromSummary($second),
            'Full tags must distinguish same-second captures.',
        );
        self::assertStringEndsWith(
            '6a9ec2295ddcd414251546',
            CaptureLabel::fromSummary($first),
            'The unique identifier must not be shortened.',
        );
    }

    public function testUnavailableMetadataAndDiagnosticText(): void
    {
        $summary = RequestSummary::create('<old>')
            ->withRequest('/<script>&', '', '', 0.0);

        self::assertSame(
            'time unavailable · UNKNOWN · /<script>& · <old>',
            CaptureLabel::fromSummary($summary),
            'Output renderers must receive the original text for escaping.',
        );
    }
}
