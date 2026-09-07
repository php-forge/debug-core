<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Panel\Log\LogMessage;
use PHPForge\Debug\Tests\Panel\Log\LogMessageTest;

/**
 * Provides the complete presentation-text catalog for {@see LogMessageTest}.
 */
final class LogMessageProvider
{
    /**
     * @return iterable<string, array{LogMessage, string}>
     */
    public static function messages(): iterable
    {
        yield 'empty_explanation' => [
            LogMessage::EMPTY_EXPLANATION,
            'This request did not emit log messages through the debug log target.',
        ];
        yield 'empty_headline' => [
            LogMessage::EMPTY_HEADLINE,
            'No log messages captured',
        ];
        yield 'no_match_explanation' => [
            LogMessage::NO_MATCH_EXPLANATION,
            'Adjust or clear the filters to show the captured messages.',
        ];
        yield 'no_match_headline' => [
            LogMessage::NO_MATCH_HEADLINE,
            'No log messages match the active filters',
        ];
    }
}
