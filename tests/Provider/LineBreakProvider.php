<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Tests\Helper\TextTest;

/**
 * Data provider for {@see TextTest} test cases.
 */
final class LineBreakProvider
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function lineBreaks(): iterable
    {
        yield 'single line' => [
            'a <b> & "c"',
            'a &lt;b&gt; &amp; "c"',
        ];
        yield 'line feed' => [
            "a\n  b",
            'a&#10;  b',
        ];
        yield 'blank lines' => [
            "\na\n\n\nb\n",
            '&#10;a&#10;&#10;&#10;b&#10;',
        ];
        yield 'carriage return and line feed' => [
            "a\r\nb\r\n\r\nc",
            'a&#10;b&#10;&#10;c',
        ];
        yield 'lone carriage return' => [
            "a\rb",
            'a&#10;b',
        ];
    }
}
