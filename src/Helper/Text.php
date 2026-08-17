<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use function mb_strtolower;
use function preg_replace;
use function str_replace;
use function trim;

/**
 * Provides small string conversions shared by the debugger panels.
 */
final class Text
{
    /**
     * Converts a CamelCase name into a lowercase id with `-` word separators, matching the Yii inflector semantics.
     *
     * Usage example:
     *
     * ```php
     * $id = \PHPForge\Debug\Helper\Text::camel2id('AppAssetBundle');
     * ```
     *
     * @param string $name CamelCase name to convert.
     *
     * @return string Lowercase kebab-case id.
     */
    public static function camel2id(string $name): string
    {
        if ($name === '') {
            return '';
        }

        $replaced = preg_replace('/(?<!\p{Lu})\p{Lu}/u', '-\0', $name) ?? $name;

        return mb_strtolower(trim(str_replace('_', '-', $replaced), '-'), 'UTF-8');
    }
}
