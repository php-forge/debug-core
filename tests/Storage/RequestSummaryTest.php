<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Storage;

use PHPForge\Debug\Storage\{HydrationException, RequestSummary};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see RequestSummary} covering strict metadata hydration without scalar coercion.
 */
#[Group('storage')]
final class RequestSummaryTest extends TestCase
{
    public function testCreateAndWithersBuildAnImmutableSummary(): void
    {
        $empty = RequestSummary::create('tag-1');

        $summary = $empty
            ->withRequest('https://example.test/', 'GET', '127.0.0.1', 1_700_000_000.0, true)
            ->withResponse(201)
            ->withDatabase(3, 1)
            ->withMail(1, ['message.eml'])
            ->withProfiling(0.125, 2_097_152);

        self::assertSame(
            '',
            $empty->url,
            'The source summary must remain unchanged.',
        );
        self::assertSame(
            [
                'tag' => 'tag-1',
                'url' => 'https://example.test/',
                'ajax' => true,
                'method' => 'GET',
                'ip' => '127.0.0.1',
                'time' => 1_700_000_000.0,
                'statusCode' => 201,
                'sqlCount' => 3,
                'excessiveCallersCount' => 1,
                'mailCount' => 1,
                'mailFiles' => ['message.eml'],
                'processingTime' => 0.125,
                'peakMemory' => 2_097_152,
            ],
            $summary->jsonSerialize(),
            'Fluent enrichment must preserve every request-summary field.',
        );
    }

    public function testJsonPayloadHydratesWithoutScalarCoercion(): void
    {
        $summary = RequestSummary::fromArray($this->payload());

        self::assertSame(
            200,
            $summary->statusCode,
            'Status code must remain an integer.',
        );
        self::assertSame(
            1_700_000_000.0,
            $summary->time,
            'Request timestamp must remain a float.',
        );
        self::assertFalse(
            $summary->ajax,
            'Synchronous request flag must remain `false`.',
        );
    }

    public function testThrowHydrationExceptionForNumericString(): void
    {
        $payload = $this->payload();

        $payload['statusCode'] = '200';

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            '$.summary.statusCode',
        );

        RequestSummary::fromArray($payload);
    }

    public function testThrowHydrationExceptionForUnknownField(): void
    {
        $payload = $this->payload();

        $payload['unexpected'] = true;

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            '$.summary.unexpected',
        );

        RequestSummary::fromArray($payload);
    }

    public function testThrowHydrationExceptionWhenAMailFileEntryIsNotAString(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '\$.summary.mailFiles[1]'",
        );

        RequestSummary::fromArray(
            [
                'tag' => 'tag-1',
                'url' => 'https://example.test/',
                'ajax' => false,
                'method' => 'GET',
                'ip' => '127.0.0.1',
                'time' => 1_700_000_000.0,
                'statusCode' => 200,
                'sqlCount' => 0,
                'excessiveCallersCount' => 0,
                'mailCount' => 2,
                'mailFiles' => ['a.eml', 42],
                'processingTime' => null,
                'peakMemory' => null,
            ],
        );
    }

    public function testWithProfilingReturnsAnEnrichedCopy(): void
    {
        $summary = RequestSummary::fromArray($this->payload());
        $profiled = $summary->withProfiling(0.125, 2_097_152);

        self::assertSame(
            [
                ...$summary->jsonSerialize(),
                'processingTime' => 0.125,
                'peakMemory' => 2_097_152,
            ],
            $profiled->jsonSerialize(),
            'Profiling metrics must replace only the optional timing fields.',
        );
    }

    /**
     * Returns representative decoded request metadata.
     *
     * @return array<string, mixed> Representative decoded request metadata.
     */
    private function payload(): array
    {
        return [
            'tag' => 'tag-1',
            'url' => 'https://example.test/',
            'ajax' => false,
            'method' => 'GET',
            'ip' => '127.0.0.1',
            'time' => 1_700_000_000.0,
            'statusCode' => 200,
            'sqlCount' => 0,
            'excessiveCallersCount' => 0,
            'mailCount' => 0,
            'mailFiles' => [],
            'processingTime' => null,
            'peakMemory' => null,
        ];
    }
}
