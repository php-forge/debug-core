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
        yield 'category' => [
            LogMessage::CATEGORY,
            'Category',
        ];
        yield 'chip_aria' => [
            LogMessage::CHIP_ARIA,
            '%d %s; filter log messages by %s level',
        ];
        yield 'chip_title' => [
            LogMessage::CHIP_TITLE,
            'Show only %s log messages',
        ];
        yield 'context_summary' => [
            LogMessage::CONTEXT_SUMMARY,
            'Context · %d',
        ];
        yield 'delta' => [
            LogMessage::DELTA,
            'Delta',
        ];
        yield 'dump_empty' => [
            LogMessage::DUMP_EMPTY,
            'empty',
        ];
        yield 'dump_empty_title' => [
            LogMessage::DUMP_EMPTY_TITLE,
            'print_r() prints an empty string, null, and false the same way',
        ];
        yield 'dump_entries' => [
            LogMessage::DUMP_ENTRIES,
            '%d entries',
        ];
        yield 'dump_entry' => [
            LogMessage::DUMP_ENTRY,
            '1 entry',
        ];
        yield 'dump_kind_array' => [
            LogMessage::DUMP_KIND_ARRAY,
            'array',
        ];
        yield 'dump_kind_enum' => [
            LogMessage::DUMP_KIND_ENUM,
            'enum',
        ];
        yield 'dump_kind_object' => [
            LogMessage::DUMP_KIND_OBJECT,
            'object',
        ];
        yield 'dump_private' => [
            LogMessage::DUMP_PRIVATE,
            'private',
        ];
        yield 'dump_private_title' => [
            LogMessage::DUMP_PRIVATE_TITLE,
            'Private property declared in %s',
        ];
        yield 'dump_protected' => [
            LogMessage::DUMP_PROTECTED,
            'protected',
        ];
        yield 'dump_protected_title' => [
            LogMessage::DUMP_PROTECTED_TITLE,
            'Protected property; print_r() does not name the declaring class',
        ];
        yield 'dump_recursion' => [
            LogMessage::DUMP_RECURSION,
            'recursion',
        ];
        yield 'dump_recursion_title' => [
            LogMessage::DUMP_RECURSION_TITLE,
            'print_r() stopped here because the value contains itself',
        ];
        yield 'empty_explanation' => [
            LogMessage::EMPTY_EXPLANATION,
            'This request did not emit log messages through the debug log target.',
        ];
        yield 'empty_headline' => [
            LogMessage::EMPTY_HEADLINE,
            'No log messages captured',
        ];
        yield 'filter_error' => [
            LogMessage::FILTER_ERROR,
            'Error',
        ];
        yield 'filter_info' => [
            LogMessage::FILTER_INFO,
            'Info',
        ];
        yield 'filter_trace' => [
            LogMessage::FILTER_TRACE,
            'Trace',
        ];
        yield 'filter_warning' => [
            LogMessage::FILTER_WARNING,
            'Warning',
        ];
        yield 'level' => [
            LogMessage::LEVEL,
            'Level',
        ];
        yield 'level_error' => [
            LogMessage::LEVEL_ERROR,
            'error',
        ];
        yield 'level_errors' => [
            LogMessage::LEVEL_ERRORS,
            'errors',
        ];
        yield 'level_info' => [
            LogMessage::LEVEL_INFO,
            'info',
        ];
        yield 'level_trace' => [
            LogMessage::LEVEL_TRACE,
            'trace',
        ];
        yield 'level_warning' => [
            LogMessage::LEVEL_WARNING,
            'warning',
        ];
        yield 'level_warnings' => [
            LogMessage::LEVEL_WARNINGS,
            'warnings',
        ];
        yield 'message' => [
            LogMessage::MESSAGE,
            'Message',
        ];
        yield 'messages_suffix' => [
            LogMessage::MESSAGES_SUFFIX,
            ' messages',
        ];
        yield 'no_match_explanation' => [
            LogMessage::NO_MATCH_EXPLANATION,
            'Adjust or clear the filters to show the captured messages.',
        ];
        yield 'no_match_headline' => [
            LogMessage::NO_MATCH_HEADLINE,
            'No log messages match the active filters',
        ];
        yield 'number' => [
            LogMessage::NUMBER,
            '#',
        ];
        yield 'time' => [
            LogMessage::TIME,
            'Time',
        ];
        yield 'toolbar_errors' => [
            LogMessage::TOOLBAR_ERRORS,
            'Errors',
        ];
        yield 'toolbar_warnings' => [
            LogMessage::TOOLBAR_WARNINGS,
            'Warnings',
        ];
    }
}
