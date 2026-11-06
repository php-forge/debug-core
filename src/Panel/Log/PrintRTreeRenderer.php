<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Log;

use PHPForge\Debug\Helper\{Fqcn, Text};
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Interactive\{Details, Summary};
use UIAwesome\Html\Phrasing\Span;

use function array_map;
use function count;
use function sprintf;

/**
 * Renders a parsed `print_r()` dump as a collapsible object tree for the Log debug panel message cell.
 *
 * The root block renders as an open native `<details>`; nested non-empty blocks render as collapsed `<details>`, and
 * scalars, recursion markers, and empty blocks as flat rows. Every key, value, class name, and text is escaped once.
 *
 * Usage example:
 * ```php
 * $node = PrintRParser::parse($row->message);
 *
 * $html = $node === null ? Encode::content($row->message) : PrintRTreeRenderer::render($node);
 * ```
 */
final class PrintRTreeRenderer
{
    /**
     * Renders the root block of a dump, preceded by the free text found before its header.
     *
     * @param PrintRNode $root Root block returned by {@see PrintRParser::parse()}.
     *
     * @return string Open `<details>` markup of the tree.
     */
    public static function render(PrintRNode $root): string
    {
        $head = self::head($root);

        if ($root->text !== '') {
            $head = [self::span('yii-debug-log-dump-prefix', $root->text), ...$head];
        }

        return Details::tag()
            ->class('yii-debug-log-dump')
            ->html(self::summary($head), self::entries($root))
            ->open(true)
            ->render();
    }

    /**
     * Renders the entry list of a block.
     */
    private static function entries(PrintRNode $block): Div
    {
        return Div::tag()
            ->class('yii-debug-log-dump-entries')
            ->html(...array_map(self::entry(...), $block->entries));
    }

    /**
     * Renders one entry: a flat row for a scalar, a recursion marker, or an empty block, and a collapsed `<details>`
     * for a block with entries.
     */
    private static function entry(PrintREntry $entry): Details|Div
    {
        $key = [self::span('yii-debug-log-dump-key', $entry->key), ...self::visibility($entry)];
        $value = $entry->value;

        if ($value->kind === PrintRKind::SCALAR) {
            return self::row([...$key, self::scalar($value->text)]);
        }

        $head = [...$key, ...self::head($value)];

        if ($value->entries === []) {
            return self::row($head);
        }

        return Details::tag()
            ->class('yii-debug-log-dump-node')
            ->html(self::summary($head), self::entries($value));
    }

    /**
     * Renders the description of a block or recursion marker: class label, kind chip, and either the entry count or
     * the recursion marker.
     *
     * @return list<Span|string> Rendered parts in display order.
     */
    private static function head(PrintRNode $node): array
    {
        $head = $node->label === '' ? [] : [Fqcn::renderLabel($node->label)];

        $head[] = self::span(
            'yii-debug-log-dump-kind',
            $node->kind === PrintRKind::ENUM
                ? LogMessage::DUMP_KIND_ENUM->value
                : ($node->label === '' ? LogMessage::DUMP_KIND_ARRAY->value : LogMessage::DUMP_KIND_OBJECT->value),
        );

        if ($node->kind === PrintRKind::RECURSION) {
            $head[] = self::span(
                'yii-debug-log-dump-recursion',
                LogMessage::DUMP_RECURSION->value,
                LogMessage::DUMP_RECURSION_TITLE->value,
            );

            return $head;
        }

        $count = count($node->entries);

        $head[] = self::span(
            'yii-debug-log-dump-count',
            $count === 1 ? LogMessage::DUMP_ENTRY->value : sprintf(LogMessage::DUMP_ENTRIES->value, $count),
        );

        return $head;
    }

    /**
     * Renders a flat entry row.
     *
     * @param list<Span|string> $parts Rendered parts in display order.
     */
    private static function row(array $parts): Div
    {
        return Div::tag()
            ->class('yii-debug-log-dump-row')
            ->html(...$parts);
    }

    /**
     * Renders a scalar value with its line breaks kept through {@see Text::preserveLineBreaks()}, or the empty marker
     * when `print_r()` printed nothing.
     */
    private static function scalar(string $text): Span
    {
        return $text === ''
            ? self::span('yii-debug-log-dump-empty', LogMessage::DUMP_EMPTY->value, LogMessage::DUMP_EMPTY_TITLE->value)
            : Span::tag()
                ->class('yii-debug-log-dump-value')
                ->html(Text::preserveLineBreaks($text));
    }

    /**
     * Renders a `<span>` of the tree vocabulary with escaped content and an optional tooltip.
     *
     * @param string $class Class of the `yii-debug-log-dump-*` vocabulary.
     * @param string $content Text content.
     * @param string $title Tooltip, or `''` to omit the attribute.
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
     * @param list<Span|string> $parts Rendered parts in display order.
     */
    private static function summary(array $parts): Summary
    {
        return Summary::tag()
            ->class('yii-debug-log-dump-summary')
            ->html(...$parts);
    }

    /**
     * Renders the visibility chip of a protected or private property, naming the declaring class of the latter.
     *
     * @return list<Span> Chip, or an empty list for a public property or an array key.
     */
    private static function visibility(PrintREntry $entry): array
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
