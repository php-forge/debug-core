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
        yield 'apply' => [
            ProfileMessage::APPLY,
            'Apply',
        ];
        yield 'category' => [
            ProfileMessage::CATEGORY,
            'Category',
        ];
        yield 'category_placeholder' => [
            ProfileMessage::CATEGORY_PLACEHOLDER,
            'yii\\db\\Command::query',
        ];
        yield 'details' => [
            ProfileMessage::DETAILS,
            'Details',
        ];
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
        yield 'empty_no_spans' => [
            ProfileMessage::EMPTY_NO_SPANS,
            ' spans, so the Timeline and details are empty.',
        ];
        yield 'empty_produced' => [
            ProfileMessage::EMPTY_PRODUCED,
            'This request did not produce any ',
        ];
        yield 'empty_separator' => [
            ProfileMessage::EMPTY_SEPARATOR,
            ' / ',
        ];
        yield 'filters' => [
            ProfileMessage::FILTERS,
            'Profiling filters',
        ];
        yield 'info' => [
            ProfileMessage::INFO,
            'Info',
        ];
        yield 'info_placeholder' => [
            ProfileMessage::INFO_PLACEHOLDER,
            'SELECT',
        ];
        yield 'min_duration' => [
            ProfileMessage::MIN_DURATION,
            'Min duration (ms)',
        ];
        yield 'no_match_explanation' => [
            ProfileMessage::NO_MATCH_EXPLANATION,
            'Adjust or clear the filters to show the captured spans.',
        ];
        yield 'no_match_headline' => [
            ProfileMessage::NO_MATCH_HEADLINE,
            'No spans match the active filters',
        ];
        yield 'peak_suffix' => [
            ProfileMessage::PEAK_SUFFIX,
            ' peak',
        ];
        yield 'span_suffix' => [
            ProfileMessage::SPAN_SUFFIX,
            ' span',
        ];
        yield 'spans_suffix' => [
            ProfileMessage::SPANS_SUFFIX,
            ' spans',
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
        yield 'toolbar_memory' => [
            ProfileMessage::TOOLBAR_MEMORY,
            'Peak memory',
        ];
        yield 'toolbar_time' => [
            ProfileMessage::TOOLBAR_TIME,
            'Total processing time',
        ];
        yield 'total_suffix' => [
            ProfileMessage::TOTAL_SUFFIX,
            ' total',
        ];
    }
}
