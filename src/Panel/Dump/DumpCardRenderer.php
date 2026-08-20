<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Dump;

use Closure;
use PHPForge\Debug\Helper\Coerce;
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\List\{Li, Ul};
use UIAwesome\Html\Phrasing\Span;
use UIAwesome\Html\Root\Header;

use function array_map;
use function array_pop;
use function basename;
use function count;
use function date;
use function html_entity_decode;
use function htmlspecialchars;
use function implode;
use function in_array;
use function intval;
use function is_int;
use function ltrim;
use function preg_match;
use function preg_replace;
use function sprintf;
use function str_replace;
use function strip_tags;
use function strpos;
use function strtolower;
use function substr;

use const ENT_HTML5;
use const ENT_NOQUOTES;
use const ENT_SUBSTITUTE;

/**
 * Renders the typed dump cells of the dumps grid for the Dump debug panel.
 */
final class DumpCardRenderer
{
    /**
     * Renders the dump card combining the head (`#index`, type badge, time, trace label) and the body (highlighted
     * payload + optional trace list).
     *
     * @param DumpRow $row Typed dump record.
     * @param Closure(array<string, mixed>): string $traceLine Renders one backtrace frame as a link line.
     * @param int $index Zero-based row index assigned by GridView.
     */
    public static function renderMessageCell(DumpRow $row, Closure $traceLine, int $index): string
    {
        return Div::tag()
            ->class('yii-debug-dump')
            ->html(
                self::renderHead($row, $index),
                self::renderBody($row, $traceLine),
            )
            ->render();
    }

    /**
     * Extracts the first trace frame's `file` / `line` pair, narrowed to `string` / `int|null`.
     *
     * @param list<array<string, mixed>> $trace Captured backtrace frames.
     *
     * @return array{0: string, 1: int|null} `[file, line]`, with `''` and `null` when the frame is missing or
     * malformed.
     */
    private static function firstFrame(array $trace): array
    {
        $frame = $trace[0] ?? null;

        $file = Coerce::string($frame['file'] ?? null);

        $line = is_int($frame['line'] ?? null) ? $frame['line'] : null;

        return [$file, $line];
    }

    /**
     * Formats a Unix timestamp in seconds as `H:i:s.mmm`, falling back to `''` when no timestamp is set.
     */
    private static function formatTime(float $time): string
    {
        if ($time <= 0) {
            return '';
        }

        $millis = intval($time * 1000) % 1000;

        return date('H:i:s', (int) $time) . '.' . sprintf('%03d', $millis);
    }

    /**
     * Renders the dump card body: the highlighted payload followed by the optional trace list.
     *
     * @param Closure(array<string, mixed>): string $traceLine Renders one backtrace frame as a link line.
     */
    private static function renderBody(DumpRow $row, Closure $traceLine): Div
    {
        $body = Div::tag()
            ->class('yii-debug-dump-body');

        $message = self::sanitizeMessage($row->message);

        if ($row->trace === []) {
            return $body->html($message);
        }

        $items = array_map(
            static fn(array $frame): Li => Li::tag()->html(($traceLine)($frame)),
            $row->trace,
        );

        $trace = Ul::tag()
            ->class('yii-debug-trace')
            ->html(...$items)
            ->render();

        return $body->html("{$message}{$trace}");
    }

    /**
     * Renders the dump card head: the `#index` badge, the optional type badge, and the meta line with time and trace
     * label.
     */
    private static function renderHead(DumpRow $row, int $index): Header
    {
        [$typeKey, $typeLabel] = self::sniffType($row->message);

        $headChildren = [
            Span::tag()
                ->addAriaAttribute('hidden', 'true')
                ->class('yii-debug-dump-index')
                ->content('#' . ($index + 1)),
        ];

        if ($typeLabel !== '') {
            $headChildren[] = Span::tag()
                ->addDataAttribute('type', $typeKey)
                ->class('yii-debug-dump-type')
                ->content($typeLabel);
        }

        $headChildren[] = Span::tag()
            ->class('yii-debug-dump-meta')
            ->html(...self::renderMeta($row));

        return Header::tag()
            ->class('yii-debug-dump-card-head')
            ->html(...$headChildren);
    }

    /**
     * Renders the meta-line span children: the formatted time and the truncated trace location, when present.
     *
     * @return list<Span> Meta children in render order; possibly empty when neither time nor trace location is known.
     */
    private static function renderMeta(DumpRow $row): array
    {
        $children = [];

        $timeStr = self::formatTime($row->time);

        if ($timeStr !== '') {
            $children[] = Span::tag()
                ->class('yii-debug-dump-time')
                ->content($timeStr);
        }

        [$file, $line] = self::firstFrame($row->trace);

        if ($file !== '') {
            $suffix = ($line !== null && $line > 0) ? ":{$line}" : '';

            $basename = basename($file);

            $children[] = Span::tag()
                ->class('yii-debug-dump-trace')
                ->content("{$basename}{$suffix}")
                ->title("{$file}{$suffix}");
        }

        return $children;
    }

    /**
     * Preserves the fixed markup emitted by PHP dump highlighters while escaping every other tag and attribute.
     *
     * Snapshot files and adapter callbacks are untrusted inputs at this rendering boundary. Encoding first and only
     * reconstructing the exact `pre`, `code`, and `span` forms used by `highlight_string()` prevents persisted or
     * callback-provided markup from becoming executable HTML.
     */
    private static function sanitizeMessage(string $message): string
    {
        $escaped = htmlspecialchars($message, ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

        $parts = [];
        $offset = 0;

        while (($start = strpos($escaped, '&lt;', $offset)) !== false) {
            $end = strpos($escaped, '&gt;', $start + 4);

            if ($end === false) {
                break;
            }

            if ($start > $offset) {
                $parts[] = substr($escaped, $offset, $start - $offset);
            }

            $parts[] = substr($escaped, $start, $end + 4 - $start);
            $offset = $end + 4;
        }

        $parts[] = substr($escaped, $offset);

        /** @var list<array{tag: string, index: int, html: string}> $openTags */
        $openTags = [];

        foreach ($parts as $index => $part) {
            $opening = match (true) {
                $part === '&lt;pre&gt;' => ['tag' => 'pre', 'html' => '<pre>'],
                preg_match('/^&lt;(code|span) style="color: (#[0-9A-Fa-f]{6})"&gt;$/', $part, $match) === 1 => [
                    'tag' => $match[1],
                    'html' => '<' . $match[1] . ' style="color: ' . $match[2] . '">',
                ],
                default => null,
            };

            if ($opening !== null) {
                $openTags[] = [...$opening, 'index' => $index];

                continue;
            }

            if (preg_match('/^&lt;\/(pre|code|span)&gt;$/', $part, $match) !== 1 || $openTags === []) {
                continue;
            }

            $last = $openTags[count($openTags) - 1];

            if ($last['tag'] !== $match[1]) {
                continue;
            }

            array_pop($openTags);

            $parts[$last['index']] = $last['html'];
            $parts[$index] = "</{$match[1]}>";
        }

        return str_replace('&amp;', '&', implode('', $parts));
    }

    /**
     * Sniffs the dump payload type from PHP's `highlight_string()` output.
     *
     * Decodes HTML entities so the first payload character (`[`, `'`, `"`, digit, identifier) classifies the dumped
     * value. A miss hides the badge without blocking the render.
     *
     * @return array{0: string, 1: string} `[typeKey, typeLabel]`, both `''` when the type cannot be determined.
     */
    private static function sniffType(string $message): array
    {
        $plain = html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $payload = ltrim((string) preg_replace('/^\s*<\?php\s*/', '', $plain));

        if ($payload === '') {
            return ['', ''];
        }

        $first = $payload[0];

        if ($first === '[') {
            return ['array', 'array'];
        }

        if ($first === "'" || $first === '"') {
            return ['string', 'string'];
        }

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*/', $payload, $m) === 1) {
            $name = $m[0];

            $lower = strtolower($name);

            if (in_array($lower, ['true', 'false'], true)) {
                return ['bool', 'bool'];
            }

            if ($lower === 'null') {
                return ['null', 'null'];
            }

            return ['object', $name];
        }

        if (preg_match('/^-?\d/', $payload) === 1) {
            return ['number', 'number'];
        }

        return ['', ''];
    }
}
