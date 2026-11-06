<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Log;

use Closure;
use PHPForge\Debug\Helper\{CellMore, Disclosure, Format, Fqcn, LogLevel, PhpHighlighter, Text, Vocabulary};
use PHPForge\Debug\Panel\Db\SqlHighlighter;
use PHPForge\Debug\Theme\Css;
use UIAwesome\Html\Flow\{Div, Pre};
use UIAwesome\Html\Helper\Encode;
use UIAwesome\Html\List\{Dd, Dl, Dt, Li, Ul};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;

use function array_map;
use function count;
use function implode;
use function intdiv;
use function sprintf;
use function str_starts_with;
use function strpbrk;

/**
 * Renders the typed cells of the logs grid for the Log debug panel.
 */
final class LogCellRenderer
{
    /**
     * Class of the key/value list revealed by the context disclosure.
     */
    private const string CONTEXT_CLASS = 'yii-debug-log-context';
    /**
     * Classes of the block holding a highlighted PHP value export: the Request panel dump wrapper plus the log variant.
     */
    private const string EXPORT_CLASS = 'yii-debug-php-dump yii-debug-log-export';
    /**
     * @var array<int, string> Maps the severity levels highlighted in the grid to the row variant CSS modifier.
     */
    private const array LEVEL_VARIANTS = [
        LogLevel::ERROR => 'danger',
        LogLevel::WARNING => 'warning',
        LogLevel::INFO => 'info',
    ];
    /**
     * Category prefix of the DB command log entries whose message is a raw SQL statement.
     */
    private const string SQL_CATEGORY_PREFIX = 'yii\db\Command::';
    /**
     * Class of the block preserving the line breaks and indentation of a multi-line message.
     */
    private const string TEXT_CLASS = 'yii-debug-log-text';

    /**
     * Builds the GridView `rowOptions` array for the row: anchor id (`log-{N}`) and severity-driven CSS class.
     *
     * @return array<string, mixed> Attribute map with `id` and (optionally) `class` keys.
     */
    public static function buildRowOptions(LogRow $row): array
    {
        $variant = self::LEVEL_VARIANTS[$row->level] ?? null;

        $options = [
            'id' => "log-{$row->id}",
        ];

        if ($variant !== null) {
            $options['class'] = Css::row($variant);
        }

        return $options;
    }

    /**
     * Renders the category as the shared {@see Fqcn::renderLabel()} two-tone label.
     */
    public static function renderCategoryCell(LogRow $row): string
    {
        return Fqcn::renderLabel($row->category);
    }

    /**
     * Renders the level name as a vocabulary-tinted chip (`error`, `warning`, `info`, `trace`, `profile`).
     */
    public static function renderLevelCell(LogRow $row): string
    {
        return Span::tag()
            ->class('yii-debug-level-chip yii-debug-level-' . Vocabulary::logLevel($row->level))
            ->content(LogLevel::name($row->level))
            ->render();
    }

    /**
     * Renders the message cell, followed by the optional context disclosure and the optional trace list.
     *
     * SQL renders highlighted, a `print_r()` or `var_export()` dump as a {@see DumpTreeRenderer} tree, and any other
     * message as escaped text; the message is escaped once.
     *
     * @param Closure(array<string, mixed>): string $traceLine Renders one backtrace frame as a link line.
     */
    public static function renderMessageCell(LogRow $row, Closure $traceLine): string
    {
        $isSql = str_starts_with($row->category, self::SQL_CATEGORY_PREFIX)
            || SqlHighlighter::isStatement($row->message);

        $tree = $isSql ? null : self::parseTree($row->message);

        $body = $tree === null
            ? CellMore::clamp(self::renderMessageText($row->message, $isSql), $row->message)
            : DumpTreeRenderer::render($tree);

        if ($row->context !== []) {
            $body .= self::renderContext($row->context);
        }

        if ($row->trace === []) {
            return $body;
        }

        $items = array_map(
            static fn(array $frame): Li => Li::tag()->html(($traceLine)($frame)),
            $row->trace,
        );

        $trace = Ul::tag()->class(Css::TRACE)->html(...$items)->render();

        return "{$body}{$trace}";
    }

    /**
     * Renders the capture time as `H:i:s.mmm`, derived from the row's millisecond timestamp.
     */
    public static function renderTimeCell(LogRow $row): string
    {
        return Format::timeOfDay((int) $row->time);
    }

    /**
     * Renders the time-since-previous cell with the prev/next anchor navigation buttons.
     */
    public static function renderTimeSincePreviousCell(LogRow $row): string
    {
        $diffMsTotal = (int) ($row->time - $row->timeOfPrevious);

        $diffSecondsTotal = intdiv($diffMsTotal, 1000);
        $diffMinutesTotal = intdiv($diffSecondsTotal, 60);
        $diffHours = intdiv($diffMinutesTotal, 60);

        $diffMs = $diffMsTotal % 1000;
        $diffSeconds = $diffSecondsTotal % 60;
        $diffMinutes = $diffMinutesTotal % 60;

        $parts = [];

        if ($diffHours > 0) {
            $parts[] = "{$diffHours}h";
        }

        if ($diffMinutes > 0) {
            $parts[] = "{$diffMinutes}m";
        }

        if ($diffSeconds > 0) {
            $parts[] = "{$diffSeconds}s";
        }

        $parts[] = "{$diffMs}ms";

        return Div::tag()
            ->class('yii-debug-since-previous')
            ->html(
                self::renderNavButton('<', $row->idOfPrevious),
                Span::tag()->html(implode("\u{00A0}", $parts)),
                self::renderNavButton('>', $row->idOfNext),
            )
            ->render();
    }

    /**
     * Parses a multi-line message as a `print_r()` dump, then as a PHP value export, or returns `null`.
     */
    private static function parseTree(string $message): DumpNode|null
    {
        if (strpbrk($message, "\r\n") === false) {
            return null;
        }

        $tree = PrintRParser::parse($message);

        if ($tree !== null) {
            return $tree;
        }

        return PhpExportParser::parse($message);
    }

    /**
     * Renders the context disclosure: a `<dl>` of keys and their {@see DumpTreeRenderer} values.
     *
     * @param non-empty-array<array-key, mixed> $context Normalized context of the row.
     */
    private static function renderContext(array $context): string
    {
        $items = [];

        foreach ($context as $key => $value) {
            $items[] = Dt::tag()->content((string) $key);
            $items[] = Dd::tag()->html(DumpTreeRenderer::render(DumpNode::fromValue($value)));
        }

        return Disclosure::render(
            sprintf(LogMessage::CONTEXT_SUMMARY->value, count($context)),
            Dl::tag()
                ->class(self::CONTEXT_CLASS)
                ->html(...$items)
                ->render(),
        );
    }

    /**
     * Renders the unclamped message body when no dump tree applies: highlighted SQL or PHP export, a `<pre>` block, or
     * escaped text.
     */
    private static function renderMessageText(string $message, bool $isSql): string
    {
        if ($isSql) {
            return Div::tag()
                ->class('yii-debug-db-sql')
                ->html(SqlHighlighter::highlight($message))
                ->render();
        }

        if (strpbrk($message, "\r\n") === false) {
            return Encode::content($message);
        }

        $export = PhpExportDetector::detect($message);

        if ($export !== null) {
            return Div::tag()
                ->class(self::EXPORT_CLASS)
                ->html(Text::preserveHtmlLineBreaks(PhpHighlighter::highlightCode($export)))
                ->render();
        }

        return Pre::tag()
            ->class(self::TEXT_CLASS)
            ->html(Text::preserveLineBreaks($message))
            ->render();
    }

    /**
     * Renders one nav arrow as an `<a>` to `#log-{targetId}`, or a disabled `<span>` when `$targetId` is `null`.
     */
    private static function renderNavButton(string $glyph, int|null $targetId): A|Span
    {
        $class = 'yii-debug-since-previous-btn';

        if ($targetId === null) {
            return Span::tag()
                ->class("{$class} is-disabled")
                ->content($glyph);
        }

        return A::tag()
            ->class($class)
            ->content($glyph)
            ->href("#log-{$targetId}");
    }
}
