<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request;

use PHPForge\Debug\Helper\{CellMore, Dump};
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\List\{Li, Ul};
use UIAwesome\Html\Phrasing\Span;

use function array_is_list;
use function count;
use function htmlspecialchars;
use function implode;
use function is_array;
use function is_string;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Renders captured Request diagnostics without changing their stored values.
 */
final class RequestDiagnosticValueRenderer
{
    /**
     * Escapes a diagnostic label while substituting malformed UTF-8 bytes.
     */
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true);
    }

    /**
     * Renders a header value, preserving repeated header lines as distinct ordered values.
     */
    public static function header(mixed $value): string
    {
        if (self::isStringList($value)) {
            $items = [];

            foreach ($value as $item) {
                $items[] = Li::tag()->html(self::string($item));
            }

            $source = implode("\n", $value);
            $count = count($value);

            $body = Div::tag()
                ->class('yii-debug-diagnostic-values')
                ->html(
                    Span::tag()
                        ->class('yii-debug-diagnostic-value-count')
                        ->content($count . ($count === 1 ? ' value' : ' values')),
                    Ul::tag()
                        ->class('yii-debug-diagnostic-value-list')
                        ->html(...$items),
                )
                ->render();

            return CellMore::clamp($body, $source);
        }

        return self::value($value);
    }

    /**
     * Renders a captured scalar as readable text and falls back to the diagnostic dumper for structured values.
     */
    public static function value(mixed $value): string
    {
        $source = is_string($value) ? $value : Dump::asString($value);
        $body = is_string($value) ? self::string($value) : self::escape($source);

        return CellMore::clamp($body, $source);
    }

    /**
     * @phpstan-assert-if-true list<string> $value
     */
    private static function isStringList(mixed $value): bool
    {
        if (!is_array($value) || !array_is_list($value) || $value === []) {
            return false;
        }

        foreach ($value as $item) {
            if (!is_string($item)) {
                return false;
            }
        }

        return true;
    }

    private static function string(string $value): string
    {
        if ($value === '') {
            return Span::tag()
                ->class('yii-debug-diagnostic-empty-value')
                ->content('Empty value')
                ->render();
        }

        return self::escape($value);
    }
}
