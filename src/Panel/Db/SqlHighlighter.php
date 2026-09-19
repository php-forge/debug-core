<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Db;

use UIAwesome\Html\Helper\Encode;

use function array_reverse;
use function preg_match;
use function preg_match_all;
use function strlen;
use function substr;

/**
 * Recognizes SQL statements and highlights them as escape-safe HTML for the DB, Log, and Profiling panels.
 */
final class SqlHighlighter
{
    /**
     * Single-pass token pattern; alternation order gives comments and literals precedence over keywords.
     */
    private const string PATTERN = '~(?<comment>/\*.*?\*/|--[^\r\n]*)'
        . '|(?<str>\'(?:[^\'\\\\]|\\\\.|\'\')*\')'
        . '|(?<ident>"(?:[^"]|"")*"|`[^`]*`)'
        . '|(?<param>(?<!:):\w+|\?)'
        . '|(?<num>\b\d+(?:\.\d+)?\b)'
        . '|(?<kw>\b(?:SELECT|INSERT|UPDATE|DELETE|FROM|WHERE|JOIN|LEFT|RIGHT|INNER|OUTER|FULL|CROSS|NATURAL|ON'
        . '|USING|AS|AND|OR|NOT|IN|IS|NULL|TRUE|FALSE|LIKE|ILIKE|BETWEEN|EXISTS|CASE|WHEN|THEN|ELSE|END|GROUP|BY'
        . '|ORDER|HAVING|LIMIT|OFFSET|UNION|ALL|DISTINCT|INTO|VALUES|SET|CREATE|ALTER|DROP|TABLE|INDEX|VIEW|TRIGGER'
        . '|SEQUENCE|PRIMARY|FOREIGN|KEY|REFERENCES|CONSTRAINT|DEFAULT|CHECK|UNIQUE|ASC|DESC|WITH|RECURSIVE'
        . '|RETURNING|CAST|COALESCE|NULLIF|BEGIN|COMMIT|ROLLBACK|TRANSACTION|EXPLAIN|ANALYZE|SHOW|DESCRIBE)\b)~is';
    /**
     * Statement shapes that identify raw SQL: an opening verb followed by the clause that verb requires, so prose
     * opening with the same word ("Select me", "Update available") stays plain text.
     */
    private const string STATEMENT_PATTERN = '~^\s*(?:'
        . 'SELECT\b.{0,4096}?\bFROM\b'
        . '|INSERT\s+INTO\b'
        . '|UPDATE\b.{0,4096}?\bSET\b'
        . '|DELETE\s+FROM\b'
        . '|REPLACE\s+INTO\b'
        . '|WITH\b.{0,4096}?\bSELECT\b'
        . '|(?:CREATE|ALTER|DROP|TRUNCATE)\s+(?:TEMPORARY\s+|UNIQUE\s+)?'
        . '(?:TABLE|INDEX|VIEW|SCHEMA|DATABASE|SEQUENCE|TRIGGER)\b'
        . '|(?:PRAGMA|EXPLAIN|VACUUM|ANALYZE|SHOW)\s+\S'
        . '|(?:BEGIN|COMMIT|ROLLBACK)(?:\s+TRANSACTION)?\s*;?\s*$'
        . ')~is';
    /**
     * Maps a matched named group to its `<span>` class; an empty class emits the escaped token unwrapped.
     */
    private const array TOKEN_CLASSES = [
        'comment' => 'yii-debug-sql-comment',
        'str' => 'yii-debug-sql-str',
        'ident' => '',
        'param' => 'yii-debug-sql-param',
        'num' => 'yii-debug-sql-num',
        'kw' => 'yii-debug-sql-kw',
    ];

    /**
     * Returns the SQL statement as fully escaped HTML with `yii-debug-sql-*` token spans.
     *
     * @param string $sql Raw SQL statement to highlight.
     *
     * @return string Escaped HTML with `yii-debug-sql-*` token spans.
     */
    public static function highlight(string $sql): string
    {
        preg_match_all(self::PATTERN, $sql, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        // Named groups fill up to the last match, so the last present group is the one that matched.
        $tokenClasses = array_reverse(self::TOKEN_CLASSES, true);

        $html = '';
        $offset = 0;

        foreach ($matches as $match) {
            $position = $match[0][1];

            $html .= Encode::content(substr($sql, $offset, $position - $offset));
            $text = Encode::content($match[0][0]);

            $token = $text;

            foreach ($tokenClasses as $group => $class) {
                if (isset($match[$group])) {
                    $token = $class === '' ? $text : "<span class=\"{$class}\">{$text}</span>";

                    break;
                }
            }

            $html .= $token;

            $offset = $position + strlen($match[0][0]);
        }

        return $html . Encode::content(substr($sql, $offset));
    }

    /**
     * Returns whether the value reads as a raw SQL statement rather than as a log or profiling sentence.
     *
     * Panels whose rows mix both call this to decide when {@see highlight()} applies, so statements logged under a
     * category the panel does not know still render with the token spans of the queries grid.
     *
     * @param string $value Message or block description to inspect.
     *
     * @return bool `true` when the value opens a SQL statement; `false` otherwise.
     */
    public static function isStatement(string $value): bool
    {
        return preg_match(self::STATEMENT_PATTERN, $value) === 1;
    }
}
