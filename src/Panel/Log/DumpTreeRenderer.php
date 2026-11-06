<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Log;

use PHPForge\Debug\Helper\{Fqcn, Text};
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Interactive\{Details, Summary};
use UIAwesome\Html\Phrasing\Span;

use function array_map;
use function count;
use function implode;
use function sprintf;
use function str_replace;
use function strlen;
use function substr_count;

/**
 * Renders a dump tree as collapsible `<details>` markup for the Log debug panel.
 *
 * The root block starts open unless the tree spans more than {@see self::MAX_OPEN_LINES} lines; all text is escaped
 * once.
 */
final class DumpTreeRenderer
{
    /**
     * Maximum number of lines of a tree whose root block starts open.
     */
    private const int MAX_OPEN_LINES = 200;

    /**
     * Renders a dump tree, or only the value markup of a scalar or enum case root.
     */
    public static function render(DumpNode $root): string
    {
        $head = self::head($root);

        if ($root->entries === [] && $root->kind !== DumpKind::ARRAY && $root->kind !== DumpKind::OBJECT) {
            return implode('', $head);
        }

        if ($root->text !== '') {
            $head = [self::span('yii-debug-log-dump-prefix', $root->text), ...$head];
        }

        return Details::tag()
            ->class('yii-debug-log-dump')
            ->html(self::summary($head), self::entries($root))
            ->open(self::lines($root) <= self::MAX_OPEN_LINES)
            ->render();
    }

    /**
     * Renders the entry list of a block.
     */
    private static function entries(DumpNode $block): Div
    {
        return Div::tag()
            ->class('yii-debug-log-dump-entries')
            ->html(...array_map(self::entry(...), $block->entries));
    }

    /**
     * Renders one entry as a flat row, or as a collapsed `<details>` for a block with entries.
     */
    private static function entry(DumpEntry $entry): Details|Div
    {
        $head = [
            self::text('yii-debug-log-dump-key', $entry->key),
            ...self::visibility($entry),
            ...self::head($entry->value),
        ];

        if ($entry->value->entries === []) {
            return self::row($head);
        }

        return Details::tag()
            ->class('yii-debug-log-dump-node')
            ->html(self::summary($head), self::entries($entry->value));
    }

    /**
     * Renders the description of a value: its scalar markup, or its class label, kind chip, and count or marker.
     *
     * @return list<Span|string> Parts in display order.
     */
    private static function head(DumpNode $node): array
    {
        $scalar = self::scalar($node);

        if ($scalar !== null) {
            return [$scalar];
        }

        $head = $node->label === '' ? [] : [Fqcn::renderLabel($node->label)];

        $head[] = self::span(
            'yii-debug-log-dump-kind',
            $node->kind === DumpKind::ENUM
                ? LogMessage::DUMP_KIND_ENUM->value
                : ($node->label === '' ? LogMessage::DUMP_KIND_ARRAY->value : LogMessage::DUMP_KIND_OBJECT->value),
        );

        $count = count($node->entries);

        $head[] = match (true) {
            $node->kind === DumpKind::RECURSION => self::span(
                'yii-debug-log-dump-recursion',
                LogMessage::DUMP_RECURSION->value,
                LogMessage::DUMP_RECURSION_TITLE->value,
            ),
            $node->kind === DumpKind::ENUM && $count === 0 => self::span(
                'yii-debug-log-dump-value',
                $node->text,
            ),
            default => self::span(
                'yii-debug-log-dump-count',
                $count === 1 ? LogMessage::DUMP_ENTRY->value : sprintf(LogMessage::DUMP_ENTRIES->value, $count),
            ),
        };

        return $head;
    }

    /**
     * Counts the line breaks of a text, `\r\n` and a lone `\r` as one each, as {@see Text} writes them.
     */
    private static function lineBreaks(string $text): int
    {
        return substr_count(str_replace(["\r\n", "\r"], "\n", $text), "\n");
    }

    /**
     * Counts the lines of a tree: one per value, the root included, plus each line break of its texts and keys.
     */
    private static function lines(DumpNode $node): int
    {
        $lines = 1 + self::lineBreaks($node->text);

        foreach ($node->entries as $entry) {
            $lines += self::lineBreaks($entry->key) + self::lines($entry->value);
        }

        return $lines;
    }

    /**
     * Renders a flat entry row.
     *
     * @param list<Span|string> $parts Parts in display order.
     */
    private static function row(array $parts): Div
    {
        return Div::tag()
            ->class('yii-debug-log-dump-row')
            ->html(...$parts);
    }

    /**
     * Renders a scalar value, or returns `null` for a block, an enum case reference, or a recursion marker.
     */
    private static function scalar(DumpNode $node): Span|null
    {
        return match ($node->kind) {
            DumpKind::SCALAR => $node->text === ''
                ? self::span('yii-debug-log-dump-empty', LogMessage::DUMP_EMPTY->value, LogMessage::DUMP_EMPTY_TITLE->value)
                : self::text('yii-debug-log-dump-value', $node->text),
            DumpKind::STRING => self::text('yii-debug-log-dump-value yii-debug-log-dump-value-string', "\"{$node->text}\"")
                ->title(
                    strlen($node->text) === 1
                        ? LogMessage::DUMP_STRING_BYTE->value
                        : sprintf(LogMessage::DUMP_STRING_BYTES->value, strlen($node->text)),
                ),
            DumpKind::FLOAT, DumpKind::INTEGER => self::text(
                'yii-debug-log-dump-value yii-debug-log-dump-value-number',
                $node->text,
            ),
            DumpKind::BOOLEAN => self::text('yii-debug-log-dump-value yii-debug-log-dump-value-bool', $node->text),
            DumpKind::NULL => self::text('yii-debug-log-dump-value yii-debug-log-dump-value-null', $node->text),
            default => null,
        };
    }

    /**
     * Renders a `<span>` with escaped content and a tooltip unless `$title` is `''`.
     */
    private static function span(string $class, string $content, string $title = ''): Span
    {
        $span = Span::tag()
            ->class($class)
            ->content($content);

        return $title === '' ? $span : $span->title($title);
    }

    /**
     * Renders the `<summary>` of a block.
     *
     * @param list<Span|string> $parts Parts in display order.
     */
    private static function summary(array $parts): Summary
    {
        return Summary::tag()
            ->class('yii-debug-log-dump-summary')
            ->html(...$parts);
    }

    /**
     * Renders a `<span>` with escaped content whose line breaks are written as `&#10;`.
     */
    private static function text(string $class, string $text): Span
    {
        return Span::tag()
            ->class($class)
            ->html(Text::preserveLineBreaks($text));
    }

    /**
     * Renders the visibility chip of a protected or private property.
     *
     * @return list<Span> Chip, or `[]` for a public property or an array key.
     */
    private static function visibility(DumpEntry $entry): array
    {
        return match ($entry->visibility) {
            'private' => [
                self::span(
                    'yii-debug-log-dump-visibility',
                    LogMessage::DUMP_PRIVATE->value,
                    sprintf(LogMessage::DUMP_PRIVATE_TITLE->value, $entry->declaringClass),
                ),
            ],
            'protected' => [
                self::span(
                    'yii-debug-log-dump-visibility',
                    LogMessage::DUMP_PROTECTED->value,
                    LogMessage::DUMP_PROTECTED_TITLE->value,
                ),
            ],
            default => [],
        };
    }
}
