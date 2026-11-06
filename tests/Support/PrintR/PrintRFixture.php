<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Support\PrintR;

use RuntimeException;

use function file_get_contents;
use function print_r;
use function range;
use function str_repeat;
use function strlen;

/**
 * Builds real `print_r()` dumps for the dump parser, tree renderer, and log message cell tests.
 */
final class PrintRFixture
{
    /**
     * Returns the dump of a {@see PrintRChild} whose `self` property references the object itself.
     *
     * @return string `print_r()` output.
     */
    public static function child(): string
    {
        $child = new PrintRChild();

        $child->self = $child;

        return print_r($child, true);
    }

    /**
     * Returns the dump of an array of `$count` integers, which holds `$count + 1` values with the root block.
     *
     * @param int<1, max> $count Number of entries.
     *
     * @return string `print_r()` output.
     */
    public static function list(int $count): string
    {
        return print_r(range(1, $count), true);
    }

    /**
     * Returns the dump of `$depth` nested arrays, the root array included, each nesting the next under the key `n`.
     *
     * @param int $depth Number of nested blocks.
     *
     * @return string `print_r()` output.
     */
    public static function nested(int $depth): string
    {
        $value = [];

        for ($level = 1; $level < $depth; $level++) {
            $value = ['n' => $value];
        }

        return print_r($value, true);
    }

    /**
     * Returns a verbatim `print_r()` dump of a PSR-7 server request, shaped like the one reported in yii3/debug#75.
     *
     * @return string `print_r()` output.
     */
    public static function serverRequest(): string
    {
        $dump = file_get_contents(__DIR__ . '/server-request.txt');

        if ($dump === false) {
            throw new RuntimeException('The server request dump fixture is not readable.');
        }

        return $dump;
    }

    /**
     * Returns the dump of an array holding one string under the key `k`, padded so the dump is exactly `$bytes` long.
     *
     * @param int $bytes Length of the dump, in bytes.
     *
     * @return string `print_r()` output.
     */
    public static function sized(int $bytes): string
    {
        return print_r(['k' => str_repeat('a', $bytes - strlen(print_r(['k' => ''], true)))], true);
    }
}
