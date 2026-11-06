<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Tests\Panel\Log\LogCellRendererTest;

use function print_r;
use function str_repeat;
use function var_export;

/**
 * Data provider for {@see LogCellRendererTest} test cases.
 */
final class LogCellRendererProvider
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function compileWarnings(): iterable
    {
        yield 'octal escape overflow' => [
            "[\n    \"\\400\",\n]",
            <<<'HTML'
            <div class="yii-debug-php-dump yii-debug-log-export">
            <pre tabindex="0"><code style="color: #000000"><span style="color: #007700">[&#10;    </span><span style="color: #DD0000">"\400"</span><span style="color: #007700">,&#10;]</span></code></pre>
            </div>
            HTML,
        ];
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function messageBodies(): iterable
    {
        $longText = str_repeat("segment of a long line\n\n", 26);
        $longTextHtml = str_repeat('segment of a long line&#10;&#10;', 26);
        $longValue = str_repeat('x', 700);

        yield 'multi-line text' => [
            "line one\n\n  indented <b>",
            'app',
            <<<'HTML'
            <pre class="yii-debug-log-text">
            line one&#10;&#10;  indented &lt;b&gt;
            </pre>
            HTML,
        ];
        yield 'text with a lone carriage return' => [
            "line one\rline two",
            'app',
            <<<'HTML'
            <pre class="yii-debug-log-text">
            line one&#10;line two
            </pre>
            HTML,
        ];
        yield 'multi-line text over the clamp threshold' => [
            $longText,
            'app',
            <<<HTML
            <div class="yii-debug-cell-more">
            <div class="yii-debug-cell-more-body">
            <pre class="yii-debug-log-text">
            {$longTextHtml}
            </pre>
            </div><button class="yii-debug-cell-more-toggle" type="button" aria-expanded="false" data-yii-debug-toggle="cell-more">Show more</button>
            </div>
            HTML,
        ];
        yield 'malformed dump' => [
            "Array\n(\n    [a] => 1",
            'app',
            <<<'HTML'
            <pre class="yii-debug-log-text">
            Array&#10;(&#10;    [a] =&gt; 1
            </pre>
            HTML,
        ];
        yield 'print_r dump' => [
            print_r(['id' => '<7>'], true),
            'app',
            <<<'HTML'
            <details class="yii-debug-log-dump" open>
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-count">1 entry</span>
            </summary><div class="yii-debug-log-dump-entries">
            <div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">id</span><span class="yii-debug-log-dump-value">&lt;7&gt;</span>
            </div>
            </div>
            </details>
            HTML,
        ];
        yield 'print_r dump over the clamp threshold' => [
            print_r(['k' => $longValue], true),
            'app',
            <<<HTML
            <details class="yii-debug-log-dump" open>
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-count">1 entry</span>
            </summary><div class="yii-debug-log-dump-entries">
            <div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">k</span><span class="yii-debug-log-dump-value">{$longValue}</span>
            </div>
            </div>
            </details>
            HTML,
        ];
        yield 'var_export array' => [
            var_export(['id' => 7], true),
            'app',
            <<<'HTML'
            <details class="yii-debug-log-dump" open>
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-count">1 entry</span>
            </summary><div class="yii-debug-log-dump-entries">
            <div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">id</span><span class="yii-debug-log-dump-value yii-debug-log-dump-value-number">7</span>
            </div>
            </div>
            </details>
            HTML,
        ];
        yield 'var_export markup injection' => [
            "array (\n  'x' => '</pre><script>alert(1)</script>',\n)",
            'app',
            <<<'HTML'
            <details class="yii-debug-log-dump" open>
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-count">1 entry</span>
            </summary><div class="yii-debug-log-dump-entries">
            <div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">x</span><span class="yii-debug-log-dump-value yii-debug-log-dump-value-string" title="31 bytes">"&lt;/pre&gt;&lt;script&gt;alert(1)&lt;/script&gt;"</span>
            </div>
            </div>
            </details>
            HTML,
        ];
        yield 'var_export string with a blank line' => [
            var_export(['t' => "a\n\nb"], true),
            'app',
            <<<'HTML'
            <details class="yii-debug-log-dump" open>
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-count">1 entry</span>
            </summary><div class="yii-debug-log-dump-entries">
            <div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">t</span><span class="yii-debug-log-dump-value yii-debug-log-dump-value-string" title="4 bytes">"a&#10;&#10;b"</span>
            </div>
            </div>
            </details>
            HTML,
        ];
        yield 'var_export string with Windows line breaks' => [
            var_export(['t' => "a\r\nb"], true),
            'app',
            <<<'HTML'
            <details class="yii-debug-log-dump" open>
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-count">1 entry</span>
            </summary><div class="yii-debug-log-dump-entries">
            <div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">t</span><span class="yii-debug-log-dump-value yii-debug-log-dump-value-string" title="4 bytes">"a&#10;b"</span>
            </div>
            </div>
            </details>
            HTML,
        ];
        yield 'export with carriage return line breaks' => [
            "array (\r  'id' => 7,\r)",
            'app',
            <<<'HTML'
            <details class="yii-debug-log-dump" open>
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-count">1 entry</span>
            </summary><div class="yii-debug-log-dump-entries">
            <div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">id</span><span class="yii-debug-log-dump-value yii-debug-log-dump-value-number">7</span>
            </div>
            </div>
            </details>
            HTML,
        ];
        yield 'VarDumper::export() keyless list' => [
            "[\n    'list' => [\n        1,\n        null,\n    ],\n]",
            'app',
            <<<'HTML'
            <details class="yii-debug-log-dump" open>
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-count">1 entry</span>
            </summary><div class="yii-debug-log-dump-entries">
            <details class="yii-debug-log-dump-node">
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-key">list</span><span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-count">2 entries</span>
            </summary><div class="yii-debug-log-dump-entries">
            <div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">0</span><span class="yii-debug-log-dump-value yii-debug-log-dump-value-number">1</span>
            </div><div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">1</span><span class="yii-debug-log-dump-value yii-debug-log-dump-value-null">null</span>
            </div>
            </div>
            </details>
            </div>
            </details>
            HTML,
        ];
        yield 'export with a double-quoted string' => [
            "[\n    \"a\",\n]",
            'app',
            <<<'HTML'
            <div class="yii-debug-php-dump yii-debug-log-export">
            <pre tabindex="0"><code style="color: #000000"><span style="color: #007700">[&#10;    </span><span style="color: #DD0000">"a"</span><span style="color: #007700">,&#10;]</span></code></pre>
            </div>
            HTML,
        ];
        yield 'VarDumper::export() object' => [
            "[\n    'o' => unserialize('O:8:\"stdClass\":0:{}'),\n]",
            'app',
            <<<'HTML'
            <div class="yii-debug-php-dump yii-debug-log-export">
            <pre tabindex="0"><code style="color: #000000"><span style="color: #007700">[&#10;    </span><span style="color: #DD0000">'o' </span><span style="color: #007700">=&gt; </span><span style="color: #0000BB">unserialize</span><span style="color: #007700">(</span><span style="color: #DD0000">'O:8:"stdClass":0:{}'</span><span style="color: #007700">),&#10;]</span></code></pre>
            </div>
            HTML,
        ];
        yield 'interpolated string before a bracket' => [
            "[\n    \"\$x]\",\n]",
            'app',
            <<<'HTML'
            <pre class="yii-debug-log-text">
            [&#10;    "$x]",&#10;]
            </pre>
            HTML,
        ];
        yield 'var_export over the clamp threshold' => [
            var_export(['k' => $longValue], true),
            'app',
            <<<HTML
            <details class="yii-debug-log-dump" open>
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-count">1 entry</span>
            </summary><div class="yii-debug-log-dump-entries">
            <div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">k</span><span class="yii-debug-log-dump-value yii-debug-log-dump-value-string" title="700 bytes">"{$longValue}"</span>
            </div>
            </div>
            </details>
            HTML,
        ];
        yield 'highlighted export over the clamp threshold' => [
            "[\n    \"{$longValue}\",\n]",
            'app',
            <<<HTML
            <div class="yii-debug-cell-more">
            <div class="yii-debug-cell-more-body">
            <div class="yii-debug-php-dump yii-debug-log-export">
            <pre tabindex="0"><code style="color: #000000"><span style="color: #007700">[&#10;    </span><span style="color: #DD0000">"{$longValue}"</span><span style="color: #007700">,&#10;]</span></code></pre>
            </div>
            </div><button class="yii-debug-cell-more-toggle" type="button" aria-expanded="false" data-yii-debug-toggle="cell-more">Show more</button>
            </div>
            HTML,
        ];
        yield 'invalid export' => [
            "array (\n  'a' => ,\n)",
            'app',
            <<<'HTML'
            <pre class="yii-debug-log-text">
            array (&#10;  'a' =&gt; ,&#10;)
            </pre>
            HTML,
        ];
        yield 'single-line export' => [
            "[1, '<b>']",
            'app',
            "[1, '&lt;b&gt;']",
        ];
        yield 'print_r dump in the DB command category' => [
            print_r(['id' => 7], true),
            'yii\db\Command::query',
            <<<'HTML'
            <div class="yii-debug-db-sql">
            Array
            (
                [id] =&gt; <span class="yii-debug-sql-num">7</span>
            )
            </div>
            HTML,
        ];
    }
}
