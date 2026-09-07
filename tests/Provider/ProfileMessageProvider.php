<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Panel\Profile\ProfileMessage;
use PHPForge\Debug\Tests\Panel\Profile\ProfileMessageTest;

/**
 * Provides the complete presentation-text catalog for {@see ProfileMessageTest}.
 */
final class ProfileMessageProvider
{
    /**
     * @return iterable<string, array{ProfileMessage, string}>
     */
    public static function messages(): iterable
    {
        yield 'empty_call_to_action' => [
            ProfileMessage::EMPTY_CALL_TO_ACTION,
            'To populate this view, wrap interesting sections of code with profile markers:',
        ];
        yield 'empty_db_note' => [
            ProfileMessage::EMPTY_DB_NOTE,
            'Database queries are profiled automatically when the DB collector is configured.',
        ];
        yield 'empty_example' => [
            ProfileMessage::EMPTY_EXAMPLE,
            "\$profiler->begin('my-token');\n// …work…\n\$profiler->end('my-token');",
        ];
        yield 'empty_headline' => [
            ProfileMessage::EMPTY_HEADLINE,
            'No profiling data captured',
        ];
        yield 'no_match_explanation' => [
            ProfileMessage::NO_MATCH_EXPLANATION,
            'Adjust or clear the filters to show the captured spans.',
        ];
        yield 'no_match_headline' => [
            ProfileMessage::NO_MATCH_HEADLINE,
            'No spans match the active filters',
        ];
        yield 'timeline_unavailable_details' => [
            ProfileMessage::TIMELINE_UNAVAILABLE_DETAILS,
            'The profiling details remain available below.',
        ];
        yield 'timeline_unavailable_explanation' => [
            ProfileMessage::TIMELINE_UNAVAILABLE_EXPLANATION,
            'This capture does not contain the valid request start, duration, and peak-memory values required to '
            . 'position the chart.',
        ];
        yield 'timeline_unavailable_headline' => [
            ProfileMessage::TIMELINE_UNAVAILABLE_HEADLINE,
            'Timeline unavailable',
        ];
    }
}
