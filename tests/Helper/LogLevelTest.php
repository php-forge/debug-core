<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Helper;

use PHPForge\Debug\Helper\LogLevel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see LogLevel} pinning the wire values shared with the Yii logger constants.
 *
 * @since 0.1
 */
#[Group('helpers')]
final class LogLevelTest extends TestCase
{
    public function testNameMapsWireValuesToTheYiiLoggerNames(): void
    {
        self::assertSame(
            'error',
            LogLevel::name(LogLevel::ERROR),
            'Error level must keep the logger name.',
        );
        self::assertSame(
            'warning',
            LogLevel::name(LogLevel::WARNING),
            'Warning level must keep the logger name.',
        );
        self::assertSame(
            'info',
            LogLevel::name(LogLevel::INFO),
            'Info level must keep the logger name.',
        );
        self::assertSame(
            'trace',
            LogLevel::name(LogLevel::TRACE),
            'Trace level must keep the logger name.',
        );
        self::assertSame(
            'profile',
            LogLevel::name(LogLevel::PROFILE),
            'Profile level must keep the logger name.',
        );
        self::assertSame(
            'profile begin',
            LogLevel::name(LogLevel::PROFILE_BEGIN),
            'Profile-begin marker must keep the logger name.',
        );
        self::assertSame(
            'profile end',
            LogLevel::name(LogLevel::PROFILE_END),
            'Profile-end marker must keep the logger name.',
        );
        self::assertSame('unknown', LogLevel::name(0x999), 'Unrecognized values must degrade to unknown.');
    }
    public function testWireValuesMatchTheYiiLoggerConstants(): void
    {
        self::assertSame(
            0x01,
            LogLevel::ERROR,
            'Error level must stay on the wire value.',
        );
        self::assertSame(
            0x02,
            LogLevel::WARNING,
            'Warning level must stay on the wire value.',
        );
        self::assertSame(
            0x04,
            LogLevel::INFO,
            'Info level must stay on the wire value.',
        );
        self::assertSame(
            0x08,
            LogLevel::TRACE,
            'Trace level must stay on the wire value.',
        );
        self::assertSame(
            0x40,
            LogLevel::PROFILE,
            'Profile level must stay on the wire value.',
        );
        self::assertSame(
            0x50,
            LogLevel::PROFILE_BEGIN,
            'Profile-begin marker must stay on the wire value.',
        );
        self::assertSame(
            0x60,
            LogLevel::PROFILE_END,
            'Profile-end marker must stay on the wire value.',
        );
    }
}
