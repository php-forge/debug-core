<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Tests\Helper\PhpHighlighterTest;

/**
 * Data provider for {@see PhpHighlighterTest} test cases.
 */
final class PhpHighlighterProvider
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function code(): iterable
    {
        yield 'short array' => [
            "[\n    'id' => 7,\n    'tags' => [\n        0 => 'a',\n    ],\n]",
            <<<'HTML'
            <pre tabindex="0"><code style="color: #000000"><span style="color: #007700">[
                </span><span style="color: #DD0000">'id' </span><span style="color: #007700">=&gt; </span><span style="color: #0000BB">7</span><span style="color: #007700">,
                </span><span style="color: #DD0000">'tags' </span><span style="color: #007700">=&gt; [
                    </span><span style="color: #0000BB">0 </span><span style="color: #007700">=&gt; </span><span style="color: #DD0000">'a'</span><span style="color: #007700">,
                ],
            ]</span></code></pre>
            HTML,
        ];
        yield 'markup inside a string' => [
            "[\n    'x' => '</pre><script>alert(1)</script>',\n]",
            <<<'HTML'
            <pre tabindex="0"><code style="color: #000000"><span style="color: #007700">[
                </span><span style="color: #DD0000">'x' </span><span style="color: #007700">=&gt; </span><span style="color: #DD0000">'&lt;/pre&gt;&lt;script&gt;alert(1)&lt;/script&gt;'</span><span style="color: #007700">,
            ]</span></code></pre>
            HTML,
        ];
        yield 'scalar sharing the opening tag color' => [
            '42',
            <<<'HTML'
            <pre tabindex="0"><code style="color: #000000"><span style="color: #0000BB">42</span></code></pre>
            HTML,
        ];
    }
}
