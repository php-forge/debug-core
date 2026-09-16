<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use PHPForge\Debug\Theme\Css;
use UIAwesome\Html\Phrasing\{Span, Strong};

use function preg_replace;
use function sha1;
use function strrpos;
use function strtolower;
use function substr;
use function trim;

/**
 * Derives the presentation forms of a fully-qualified class name: short name, namespace prefix, two-tone label, and
 * fragment anchor.
 */
final class Fqcn
{
    /**
     * Returns a URL-safe fragment id for a fully-qualified class name, stable across spellings and free of collisions.
     *
     * The name is lowercased first because PHP resolves class names case-insensitively, so every spelling of one class
     * must share one anchor. The readable slug collapses each run of characters outside `a-z0-9` into a single `-`,
     * which on its own is not injective (`app\A_B` and `app\A__b` both flatten to `app-a-b`), so the first 8
     * hexadecimal characters of the `sha1` of the lowercased name are appended to keep distinct classes apart; that
     * digest identifies a DOM id and is never used as a security primitive. A name made only of separators leaves no
     * slug and returns the digest alone.
     *
     * @param string $fqcn Fully-qualified class name.
     *
     * @return string URL-safe fragment id.
     */
    public static function anchor(string $fqcn): string
    {
        $lowercased = strtolower($fqcn);
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $lowercased), '-');
        $digest = substr(sha1($lowercased), 0, 8);

        return $slug === '' ? $digest : "{$slug}-{$digest}";
    }

    /**
     * Returns the namespace prefix (everything before the last `\`, without trailing separator), or `''` when none is
     * present.
     *
     * @param string $fqcn Fully-qualified class name.
     *
     * @return string Namespace prefix or `''`.
     */
    public static function namespacePart(string $fqcn): string
    {
        $position = strrpos($fqcn, '\\');

        return $position === false ? '' : substr($fqcn, 0, $position);
    }

    /**
     * Renders the shared two-tone label: a muted namespace prefix followed by a bold short name, with the full value
     * preserved in the `title` attribute for hover inspection.
     *
     * Method-suffixed values such as `yii\db\Command::query` keep the `Class::method` pair inside the bold segment;
     * values without a namespace render the bold segment only, and `''` collapses to an em dash. A `<wbr>` between
     * the two segments marks the namespace boundary as the preferred line-break opportunity.
     *
     * @param string $value Fully-qualified class name, `FQCN::method` pair, or plain category string.
     *
     * @return string Two-tone label markup or an em dash for an empty value.
     */
    public static function renderLabel(string $value): string
    {
        if ($value === '') {
            return '—';
        }

        $namespace = self::namespacePart($value);

        return Span::tag()
            ->title($value)
            ->html(
                $namespace !== ''
                    ? Span::tag()
                        ->class(Css::MUTED)
                        ->content("{$namespace}\\")
                        ->render() . '<wbr>'
                    : '',
                Strong::tag()->content(self::shortName($value)),
            )
            ->render();
    }

    /**
     * Returns the segment after the last `\` separator, or the full `$fqcn` when no separator is present.
     *
     * @param string $fqcn Fully-qualified class name.
     *
     * @return string Short class name.
     */
    public static function shortName(string $fqcn): string
    {
        $position = strrpos($fqcn, '\\');

        return $position === false ? $fqcn : substr($fqcn, $position + 1);
    }
}
