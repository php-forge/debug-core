<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Queue;

use PHPForge\Debug\Panel\Queue\QueueDriverDetector;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see QueueDriverDetector} covering the per-driver FQCN → `[label, isAsync]` mapping, including the
 * sync short-circuit, the `__` snake-case title-cased fallback for unknown drivers, the empty-FQCN fallback, the
 * single-segment FQCN fallback, and the per-FQCN cache.
 */
#[Group('queue')]
final class QueueDriverDetectorTest extends TestCase
{
    public function testDetectCachesResultByFqcnAcrossInvocations(): void
    {
        self::setDetectorCache(
            ['cached\queue' => ['Cached driver', false]],
        );

        self::assertSame(
            ['Cached driver', false],
            QueueDriverDetector::detect('cached\queue'),
            'A precomputed FQCN tuple must be returned from the cache verbatim.',
        );
    }

    public function testDetectClassifiesKnownAsyncDrivers(): void
    {
        self::setDetectorCache(
            [],
        );

        self::assertSame(
            ['Database', true],
            QueueDriverDetector::detect('yii\\queue\\db\\Queue'),
            "Database driver must use the 'Database' display label and 'async=true'.",
        );
        self::assertSame(
            ['Redis', true],
            QueueDriverDetector::detect('yii\\queue\\redis\\Queue'),
            'Redis driver must use the Redis display label.',
        );
        self::assertSame(
            ['AMQP', true],
            QueueDriverDetector::detect('yii\\queue\\amqp\\Queue'),
            'AMQP driver must use the AMQP display label.',
        );
        self::assertSame(
            ['AMQP', true],
            QueueDriverDetector::detect('yii\\queue\\amqp_interop\\Queue'),
            "'amqp_interop' driver must alias back to the AMQP display label.",
        );
    }

    public function testDetectClassifiesSyncDriverAsRunInProcess(): void
    {
        // Reset cache via reflection so prior runs do not mask the title-case path.
        self::setDetectorCache(
            [],
        );

        self::assertSame(
            ['Sync', false],
            QueueDriverDetector::detect('yii\\queue\\sync\\Queue'),
            'Sync driver must report `isAsync = false`.',
        );
    }

    public function testDetectFallsBackToLowercasedFqcnForSingleSegmentClass(): void
    {
        self::setDetectorCache(
            [],
        );

        self::assertSame(
            ['Customqueue', true],
            QueueDriverDetector::detect('CustomQueue'),
            'Single-segment FQCN must title-case the lowercased class itself as the driver label.',
        );
    }

    public function testDetectReturnsUnknownForEmptyFqcn(): void
    {
        self::setDetectorCache(
            [],
        );

        self::assertSame(
            ['Unknown', true],
            QueueDriverDetector::detect(''),
            "Empty FQCN must surface as the 'Unknown' label with 'async=true'.",
        );
    }

    public function testDetectReturnsUnknownWhenExtractedTokenIsEmpty(): void
    {
        self::setDetectorCache(
            [],
        );

        self::assertSame(
            ['Unknown', true],
            QueueDriverDetector::detect('\\Foo'),
            "Leading-backslash FQCNs produce an empty driver token; 'titleCase()' must fall back to 'Unknown'.",
        );
    }

    public function testDetectTitleCasesUnknownDriverTokensWithUnderscoreSeparator(): void
    {
        self::setDetectorCache(
            [],
        );

        self::assertSame(
            ['MyCustom', true],
            QueueDriverDetector::detect('app\\queue\\MY_CUSTOM\\Queue'),
            "Unknown snake_case driver tokens must be lowercased and title-cased into 'MyCustom'.",
        );
    }

    /**
     * Seeds the detector's private static cache for cross-invocation assertions.
     *
     * @param array<string, array{string, bool}> $cache Cache entries indexed by lowercased FQCN.
     */
    private static function setDetectorCache(array $cache): void
    {
        (new \ReflectionClass(QueueDriverDetector::class))->setStaticPropertyValue('cache', $cache);
    }
}
