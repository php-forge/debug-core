<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Event;

use PHPForge\Debug\Panel\Event\{EventInspection, EventRow};
use PHPForge\Debug\Storage\HydrationException;
use PHPForge\Debug\Tests\Provider\EventRowProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

/**
 * Unit tests for {@see EventRow} covering immutable enrichment, strict JSON hydration, and summary-strip aggregates.
 */
#[Group('panel')]
#[Group('event')]
final class EventRowTest extends TestCase
{
    #[DataProviderExternal(EventRowProvider::class, 'capturedProperties')]
    public function testCapturedPropertiesRemainReadonly(string $name): void
    {
        $reflection = new ReflectionClass(EventRow::class);

        self::assertTrue(
            $reflection->getProperty($name)->isReadOnly(),
            "Captured field '{$name}' must remain readonly.",
        );
    }

    public function testDistinctClassCountCountsUniqueClassNames(): void
    {
        $count = EventRow::distinctClassCount(
            [
                self::row(class: stdClass::class),
                self::row(class: stdClass::class),
                self::row(class: 'yii\\web\\Application'),
                self::row(class: ''),
            ],
        );

        self::assertSame(
            2,
            $count,
            'Duplicates and empty classes must not inflate the count.',
        );
    }

    public function testDistinctClassCountReturnsZeroForEmptyList(): void
    {
        self::assertSame(
            0,
            EventRow::distinctClassCount([]),
            'An empty capture has no distinct classes.',
        );
    }

    public function testFromArrayRoundTripsEveryField(): void
    {
        $row = self::row(time: 1_700_000_000.5, name: 'afterSave', class: stdClass::class, isStatic: '1');

        self::assertEquals(
            $row,
            EventRow::fromArray($row->jsonSerialize(), '$.panels.event.entries[0]'),
            'Round-trip must preserve every field.',
        );
    }

    public function testStaticCountCountsOnlyStaticallyTriggeredRows(): void
    {
        $count = EventRow::staticCount([self::row(isStatic: '1'), self::row(), self::row(isStatic: '1')]);

        self::assertSame(
            2,
            $count,
            "Only rows flagged '1' are static.",
        );
    }

    public function testThrowHydrationExceptionWhenTimeIsANumericString(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '$.panels.event.entries[0].time': expected a number.",
        );

        EventRow::fromArray(
            [
                'time' => '1.0',
                'name' => 'init',
                'class' => stdClass::class,
                'isStatic' => '0',
                'senderClass' => 'App',
            ],
            '$.panels.event.entries[0]',
        );
    }

    public function testWithInspectionPreservesCapturedFieldsAndOriginalRow(): void
    {
        $row = self::row(time: 1_700_000_000.5, name: 'afterSave', class: 'SaveEvent', senderClass: 'Record');

        $payload = $row->jsonSerialize();

        $inspection = (new EventInspection())
            ->withContext(['Action ID' => 'save'], 'captured');

        $copy = $row->withInspection($inspection);

        self::assertNotSame(
            $row,
            $copy,
            'Enrichment must return a new row.',
        );
        self::assertNull(
            $row->inspection(),
            'Enrichment must not assign diagnostics to the original row.',
        );
        self::assertSame(
            $payload,
            $row->jsonSerialize(),
            'The original serialized capture must remain unchanged.',
        );
        self::assertSame(
            $inspection,
            $copy->inspection(),
            'The copy must retain the supplied immutable diagnostics.',
        );
        self::assertSame(
            [...$payload, 'inspection' => $inspection->jsonSerialize()],
            $copy->jsonSerialize(),
            'Enrichment must preserve every captured field and add only the inspection payload.',
        );
    }

    public function testWithInspectionReplacesDiagnosticsWithoutMutatingEarlierCopies(): void
    {
        $firstInspection = (new EventInspection())
            ->withContext(['Action ID' => 'save'], 'captured');

        $row = self::row(isStatic: '1', senderClass: '')->withInspection($firstInspection);

        $payload = $row->jsonSerialize();

        $secondInspection = $firstInspection->withTrace(['/app/action.php:42'], 'captured');
        $replacement = $row->withInspection($secondInspection);
        $repeated = $replacement->withInspection($secondInspection);

        self::assertNotSame(
            $row,
            $replacement,
            'Replacing diagnostics must return a new row.',
        );
        self::assertNotSame(
            $replacement,
            $repeated,
            'Repeated enrichment must still return a new row.',
        );
        self::assertSame(
            $firstInspection,
            $row->inspection(),
            'Replacing diagnostics must preserve the earlier inspection.',
        );
        self::assertSame(
            $payload,
            $row->jsonSerialize(),
            'Replacing diagnostics must not alter an earlier serialized row.',
        );
        self::assertSame(
            $secondInspection,
            $replacement->inspection(),
            'The replacement must expose the new diagnostics.',
        );
        self::assertSame(
            [...$payload, 'inspection' => $secondInspection->jsonSerialize()],
            $replacement->jsonSerialize(),
            'Replacing diagnostics must preserve the captured fields, including static event metadata.',
        );
        self::assertSame(
            $replacement->jsonSerialize(),
            $repeated->jsonSerialize(),
            'Reapplying the same inspection must preserve the payload.',
        );
        self::assertEquals(
            $replacement,
            EventRow::fromArray($replacement->jsonSerialize(), '$.panels.event.entries[0]'),
            'Replaced diagnostics must round-trip through snapshot hydration.',
        );
    }

    private static function row(
        float $time = 1.0,
        string $name = 'init',
        string $class = stdClass::class,
        string $isStatic = '0',
        string $senderClass = 'App',
    ): EventRow {
        return new EventRow($time, $name, $class, $isStatic, $senderClass);
    }
}
