<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Log;

use Closure;
use ErrorException;
use PHPForge\Debug\Panel\Log\PhpExportDetector;
use PHPForge\Debug\Tests\Provider\PhpExportDetectorProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

use function error_clear_last;
use function error_get_last;
use function restore_error_handler;
use function set_error_handler;
use function str_repeat;
use function strlen;

/**
 * Unit tests for {@see PhpExportDetector} covering the accepted export forms, the short array rewrite, rejected
 * messages, the byte limit, and the muted parser diagnostics.
 *
 * {@see PhpExportDetectorProvider} for test case data providers.
 */
#[Group('panel')]
#[Group('log')]
final class PhpExportDetectorTest extends TestCase
{
    public function testDetectAcceptsMessageAtByteLimit(): void
    {
        $message = "[\n    '" . str_repeat('a', 262133) . "',\n]";

        self::assertSame(
            262144,
            strlen($message),
            'Fixture must be exactly at the byte limit.',
        );
        self::assertSame(
            $message,
            PhpExportDetector::detect($message),
            'Message at the byte limit must be detected.',
        );
    }

    public function testDetectKeepsCompileWarningOutOfTheOutput(): void
    {
        $this->expectOutputString('');

        self::assertSame(
            "[\n    \"\\400\",\n]",
            PhpExportDetector::detect("[\n    \"\\400\",\n]"),
            'Octal overflow must still be detected as an export.',
        );
    }

    public function testDetectKeepsDeprecationAwayFromErrorHandler(): void
    {
        $handler = self::throwingHandler();

        set_error_handler($handler);
        error_clear_last();

        try {
            $code = PhpExportDetector::detect("[\n    (boolean) 1,\n]");

            $current = set_error_handler(null);

            restore_error_handler();
        } finally {
            restore_error_handler();
        }

        self::assertSame(
            "[\n    (boolean) 1,\n]",
            $code,
            'Deprecated cast must still be detected as an export.',
        );
        self::assertNull(
            error_get_last(),
            'Deprecation must be consumed, not passed to the built-in handler.',
        );
        self::assertSame(
            $handler,
            $current,
            'Application error handler must be restored.',
        );
    }

    public function testDetectRestoresErrorHandlerAfterParseError(): void
    {
        $handler = self::throwingHandler();

        set_error_handler($handler);

        try {
            $code = PhpExportDetector::detect("array (\n  'a' => ,\n)");

            $current = set_error_handler(null);

            restore_error_handler();
        } finally {
            restore_error_handler();
        }

        self::assertNull(
            $code,
            'Invalid PHP must not be detected.',
        );
        self::assertSame(
            $handler,
            $current,
            'Application error handler must be restored.',
        );
    }

    public function testDetectReturnsNullForMessageOverByteLimit(): void
    {
        $message = "[\n    '" . str_repeat('a', 262134) . "',\n]";

        self::assertSame(
            262145,
            strlen($message),
            'Fixture must be one byte over the limit.',
        );
        self::assertNull(
            PhpExportDetector::detect($message),
            'Message over the byte limit must not be detected.',
        );
    }

    #[DataProviderExternal(PhpExportDetectorProvider::class, 'rejected')]
    public function testDetectReturnsNullForMessageThatIsNotOneExport(string $message): void
    {
        self::assertNull(
            PhpExportDetector::detect($message),
            'Message must not be detected as an export.',
        );
    }

    #[DataProviderExternal(PhpExportDetectorProvider::class, 'exports')]
    public function testDetectRewritesExportInShortArraySyntax(string $message, string $expected): void
    {
        self::assertSame(
            $expected,
            PhpExportDetector::detect($message),
            'Short array source must match exactly.',
        );
    }

    /**
     * @return Closure(int, string): never
     */
    private static function throwingHandler(): Closure
    {
        return static function (int $level, string $message): never {
            throw new ErrorException(
                $message,
                0,
                $level,
            );
        };
    }
}
