<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Panel\Db\DbMessage;

/**
 * Explicit wording cases for the Database message catalog.
 */
final class DbMessageProvider
{
    /**
     * @return array<string, array{DbMessage, string}>
     */
    public static function messages(): array
    {
        return [
            'DUPLICATE' => [
                DbMessage::DUPLICATE,
                'Dup',
            ],
            'DUPLICATE_SUFFIX' => [
                DbMessage::DUPLICATE_SUFFIX,
                ' duplicated',
            ],
            'DURATION' => [
                DbMessage::DURATION,
                'Duration',
            ],
            'EMPTY_EXPLANATION' => [
                DbMessage::EMPTY_EXPLANATION,
                'This request completed without executing SQL through an instrumented DB connection.',
            ],
            'EMPTY_HEADLINE' => [
                DbMessage::EMPTY_HEADLINE,
                'No database queries in this request',
            ],
            'EXPLAIN_ALL' => [
                DbMessage::EXPLAIN_ALL,
                'Explain all',
            ],
            'EXPLAIN_UNAVAILABLE' => [
                DbMessage::EXPLAIN_UNAVAILABLE,
                'EXPLAIN is not available for this query or connection.',
            ],
            'NO_MATCH_EXPLANATION' => [
                DbMessage::NO_MATCH_EXPLANATION,
                'Remove an active filter or clear all filters to see the captured queries.',
            ],
            'NO_MATCH_HEADLINE' => [
                DbMessage::NO_MATCH_HEADLINE,
                'No database queries match these filters',
            ],
            'PAGE_SCOPE' => [
                DbMessage::PAGE_SCOPE,
                'on this page',
            ],
            'QUERY' => [
                DbMessage::QUERY,
                'Query',
            ],
            'QUERY_COUNT_SUFFIX' => [
                DbMessage::QUERY_COUNT_SUFFIX,
                ' queries',
            ],
            'ROWS' => [
                DbMessage::ROWS,
                'Rows',
            ],
            'TIME' => [
                DbMessage::TIME,
                'Time',
            ],
            'TOOLBAR_CALLERS_MANY' => [
                DbMessage::TOOLBAR_CALLERS_MANY,
                '%d callers are making too many calls.',
            ],
            'TOOLBAR_CALLERS_ONE' => [
                DbMessage::TOOLBAR_CALLERS_ONE,
                '%d caller is making too many calls.',
            ],
            'TOOLBAR_CRITICAL' => [
                DbMessage::TOOLBAR_CRITICAL,
                'Too many queries, allowed count is %d.',
            ],
            'TOOLBAR_EXECUTED' => [
                DbMessage::TOOLBAR_EXECUTED,
                'Executed %d database queries.',
            ],
            'TOTAL_SUFFIX' => [
                DbMessage::TOTAL_SUFFIX,
                ' ms total',
            ],
            'TOTAL_TIME' => [
                DbMessage::TOTAL_TIME,
                'Total query time',
            ],
            'TRACE' => [
                DbMessage::TRACE,
                'Trace',
            ],
            'TYPE' => [
                DbMessage::TYPE,
                'Type',
            ],
        ];
    }
}
