<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use Closure;
use PHPForge\Debug\Panel\Request\RequestDiagnosticValueRenderer;

use function rtrim;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtr;
use function substr;

/**
 * Renders captured argument-free source frames as configurable, escaped source links.
 */
final class Trace
{
    /**
     * Default template: an `ide://` deep link IDE extensions resolve into "open file at line".
     */
    public const string DEFAULT_TEMPLATE = '<a href="ide://open?url=file://{file}&amp;line={line}">{text}</a>';

    /**
     * @param (Closure(array<string, mixed>): mixed)|string|false $template Renderer applied to every frame.
     * @param array<string, string> $pathMappings Normalized source-path prefixes mapped to local prefixes.
     */
    private function __construct(private Closure|string|false $template, private array $pathMappings) {}

    /**
     * Creates a renderer emitting {@see DEFAULT_TEMPLATE} links without path rewriting.
     *
     * @return self Renderer using the default template and no path mappings.
     */
    public static function create(): self
    {
        return new self(
            self::DEFAULT_TEMPLATE,
            [],
        );
    }

    /**
     * Renders one source frame, or an escaped dump when `file` or `line` is missing.
     *
     * Internal PHP functions such as {@see call_user_func()} may produce frames without those keys, see
     * {@link https://www.php.net/manual/en/function.debug-backtrace.php#59713}.
     *
     * @param array<string, mixed> $frame Source frame; consumes `file`, `line`, and the optional `text` label.
     *
     * @return string Rendered source line.
     */
    public function render(array $frame): string
    {
        $file = Coerce::stringOrNull($frame['file'] ?? null);
        $line = Coerce::stringOrNull($frame['line'] ?? null);

        if ($file === null || $line === null) {
            return RequestDiagnosticValueRenderer::escape(Dump::asString($frame));
        }

        $text = "{$file}:{$line}";

        if (isset($frame['text'])) {
            $text = Coerce::stringOrNull($frame['text']) ?? Dump::asString($frame['text']);
        }

        $file = $this->mapPath(str_replace('\\', '/', $file));
        $template = $this->template;

        if ($template === false) {
            return RequestDiagnosticValueRenderer::escape($text);
        }

        if ($template instanceof Closure) {
            $frame['file'] = $file;
            $frame['line'] = $line;
            $frame['text'] = $text;

            $rendered = $template($frame);
            $template = Coerce::stringOrNull($rendered);

            if ($template === null) {
                return RequestDiagnosticValueRenderer::escape(Dump::asString($rendered));
            }
        }

        return strtr(
            $template,
            [
                '{file}' => RequestDiagnosticValueRenderer::escape($file),
                '{line}' => RequestDiagnosticValueRenderer::escape($line),
                '{text}' => RequestDiagnosticValueRenderer::escape($text),
            ],
        );
    }

    /**
     * Returns a copy rewriting containerized or remote source paths to their local counterparts.
     *
     * Entries whose value does not coerce to a string are dropped; both sides are normalized to forward slashes and a
     * single trailing slash, and only the first matching prefix is applied.
     *
     * @param array<array-key, mixed> $pathMappings Remote source-path prefixes mapped to local prefixes.
     *
     * @return self New instance rewriting frame paths through the normalized mappings.
     */
    public function withPathMappings(array $pathMappings): self
    {
        $normalized = [];

        foreach ($pathMappings as $remote => $local) {
            $target = Coerce::stringOrNull($local);

            if ($target === null) {
                continue;
            }

            $normalized[self::normalizePrefix((string) $remote)] = self::normalizePrefix($target);
        }

        return new self(
            template: $this->template,
            pathMappings: $normalized,
        );
    }

    /**
     * Returns a copy rendering every frame with the given template.
     *
     * @param (Closure(array<string, mixed>): mixed)|string|false $template Placeholder template resolving `{file}`,
     * `{line}`, and `{text}` against the escaped frame values, `false` to emit escaped plain text without a link, or a
     * closure receiving the frame with the normalized `file`, `line`, and `text` keys; a `string` returned by the
     * closure resolves the same placeholders, any other result renders as an escaped dump.
     *
     * @return self New instance rendering every frame with the given template.
     */
    public function withTemplate(Closure|string|false $template): self
    {
        return new self(
            template: $template,
            pathMappings: $this->pathMappings,
        );
    }

    /**
     * Rewrites a captured file path through the first matching source-path mapping.
     *
     * @param string $file Captured source path.
     *
     * @return string Locally reachable path, or the captured path when no mapping matches.
     */
    private function mapPath(string $file): string
    {
        foreach ($this->pathMappings as $remote => $local) {
            if (str_starts_with($file, $remote)) {
                return $local . substr($file, strlen($remote));
            }
        }

        return $file;
    }

    /**
     * Normalizes a path prefix to forward slashes and exactly one trailing separator.
     *
     * @param string $path Raw configured prefix.
     *
     * @return string Prefix using `/` separators and ending in a single `/`.
     */
    private static function normalizePrefix(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/') . '/';
    }
}
