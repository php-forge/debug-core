<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Panel\Log\{DumpEntry, DumpKind, DumpNode};
use PHPForge\Debug\Tests\Panel\Log\DumpTreeRendererTest;

use function array_fill;
use function str_repeat;

/**
 * Data provider for {@see DumpTreeRendererTest} test cases.
 *
 * Provides dump trees with the exact markup they render to: `print_r()` blocks, typed values, enum case references,
 * scalar roots, and the line threshold of the root block.
 */
final class DumpTreeRendererProvider
{
    /**
     * @return iterable<string, array{DumpNode, string}>
     */
    public static function expansion(): iterable
    {
        $row = <<<'HTML'
        <div class="yii-debug-log-dump-row">
        <span class="yii-debug-log-dump-key">k</span><span class="yii-debug-log-dump-value yii-debug-log-dump-value-number">1</span>
        </div>
        HTML;
        $rows = str_repeat($row, 199);

        yield 'root open at 200 values' => [
            DumpNode::block(DumpKind::ARRAY, '', array_fill(0, 199, new DumpEntry('k', DumpNode::integer('1')))),
            <<<HTML
            <details class="yii-debug-log-dump" open>
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-count">199 entries</span>
            </summary><div class="yii-debug-log-dump-entries">
            {$rows}
            </div>
            </details>
            HTML,
        ];
        yield 'root collapsed at 201 values' => [
            DumpNode::block(DumpKind::ARRAY, '', array_fill(0, 200, new DumpEntry('k', DumpNode::integer('1')))),
            <<<HTML
            <details class="yii-debug-log-dump">
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-count">200 entries</span>
            </summary><div class="yii-debug-log-dump-entries">
            {$rows}{$row}
            </div>
            </details>
            HTML,
        ];
        yield 'root collapsed at 201 values counting nested ones' => [
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [
                    new DumpEntry(
                        'n',
                        DumpNode::block(DumpKind::ARRAY, '', array_fill(0, 199, new DumpEntry('k', DumpNode::integer('1')))),
                    ),
                ],
            ),
            <<<HTML
            <details class="yii-debug-log-dump">
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-count">1 entry</span>
            </summary><div class="yii-debug-log-dump-entries">
            <details class="yii-debug-log-dump-node">
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-key">n</span><span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-count">199 entries</span>
            </summary><div class="yii-debug-log-dump-entries">
            {$rows}
            </div>
            </details>
            </div>
            </details>
            HTML,
        ];

        $breaks = str_repeat('x&#10;', 197);

        yield 'root open at 200 lines counting line breaks' => [
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [new DumpEntry("k\rk", DumpNode::string(str_repeat("x\r\n", 99) . str_repeat("x\n", 98)))],
            ),
            <<<HTML
            <details class="yii-debug-log-dump" open>
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-count">1 entry</span>
            </summary><div class="yii-debug-log-dump-entries">
            <div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">k&#10;k</span><span class="yii-debug-log-dump-value yii-debug-log-dump-value-string" title="493 bytes">"{$breaks}"</span>
            </div>
            </div>
            </details>
            HTML,
        ];
        yield 'root collapsed at 201 lines counting line breaks' => [
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [new DumpEntry("k\rk", DumpNode::string(str_repeat("x\r\n", 99) . str_repeat("x\n", 99)))],
            ),
            <<<HTML
            <details class="yii-debug-log-dump">
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-count">1 entry</span>
            </summary><div class="yii-debug-log-dump-entries">
            <div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">k&#10;k</span><span class="yii-debug-log-dump-value yii-debug-log-dump-value-string" title="495 bytes">"{$breaks}x&#10;"</span>
            </div>
            </div>
            </details>
            HTML,
        ];
    }

    /**
     * @return iterable<string, array{DumpNode, string}>
     */
    public static function roots(): iterable
    {
        yield 'string' => [
            DumpNode::string("a\"<b>\n"),
            <<<'HTML'
            <span class="yii-debug-log-dump-value yii-debug-log-dump-value-string" title="6 bytes">"a"&lt;b&gt;&#10;"</span>
            HTML,
        ];
        yield 'integer' => [
            DumpNode::integer('7'),
            <<<'HTML'
            <span class="yii-debug-log-dump-value yii-debug-log-dump-value-number">7</span>
            HTML,
        ];
        yield 'float' => [
            DumpNode::float('-INF'),
            <<<'HTML'
            <span class="yii-debug-log-dump-value yii-debug-log-dump-value-number">-INF</span>
            HTML,
        ];
        yield 'boolean' => [
            DumpNode::boolean('false'),
            <<<'HTML'
            <span class="yii-debug-log-dump-value yii-debug-log-dump-value-bool">false</span>
            HTML,
        ];
        yield 'null' => [
            DumpNode::null('NULL'),
            <<<'HTML'
            <span class="yii-debug-log-dump-value yii-debug-log-dump-value-null">NULL</span>
            HTML,
        ];
        yield 'untyped scalar' => [
            DumpNode::scalar('<7>'),
            <<<'HTML'
            <span class="yii-debug-log-dump-value">&lt;7&gt;</span>
            HTML,
        ];
        yield 'empty untyped scalar' => [
            DumpNode::scalar(''),
            <<<'HTML'
            <span class="yii-debug-log-dump-empty" title="print_r() prints an empty string, null, and false the same way">empty</span>
            HTML,
        ];
        yield 'enum case' => [
            DumpNode::enumCase('App\Suit', 'Hearts'),
            <<<'HTML'
            <span title="App\Suit"><span class="yii-debug-muted">App\</span><wbr><strong>Suit</strong></span><span class="yii-debug-log-dump-kind">enum</span><span class="yii-debug-log-dump-value">Hearts</span>
            HTML,
        ];
        yield 'recursion marker' => [
            DumpNode::recursion(''),
            <<<'HTML'
            <span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-recursion" title="print_r() stopped here because the value contains itself">recursion</span>
            HTML,
        ];
    }

    /**
     * @return iterable<string, array{DumpNode, string}>
     */
    public static function trees(): iterable
    {
        yield 'array with one entry' => [
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [new DumpEntry('id', DumpNode::scalar('7'))],
            ),
            <<<'HTML'
            <details class="yii-debug-log-dump" open>
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-count">1 entry</span>
            </summary><div class="yii-debug-log-dump-entries">
            <div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">id</span><span class="yii-debug-log-dump-value">7</span>
            </div>
            </div>
            </details>
            HTML,
        ];
        yield 'empty root array' => [
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [],
            ),
            <<<'HTML'
            <details class="yii-debug-log-dump" open>
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-count">0 entries</span>
            </summary><div class="yii-debug-log-dump-entries">
            </div>
            </details>
            HTML,
        ];
        yield 'escaped texts' => [
            DumpNode::block(
                DumpKind::OBJECT,
                'App\<X>',
                [new DumpEntry('<k>', DumpNode::scalar("<script>&\n\n  x"), 'private', 'App\<C>')],
                '<b>',
            ),
            <<<'HTML'
            <details class="yii-debug-log-dump" open>
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-prefix">&lt;b&gt;</span><span title="App\&lt;X&gt;"><span class="yii-debug-muted">App\</span><wbr><strong>&lt;X&gt;</strong></span><span class="yii-debug-log-dump-kind">object</span><span class="yii-debug-log-dump-count">1 entry</span>
            </summary><div class="yii-debug-log-dump-entries">
            <div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">&lt;k&gt;</span><span class="yii-debug-log-dump-visibility" title="Private property declared in App\&lt;C&gt;">private</span><span class="yii-debug-log-dump-value">&lt;script&gt;&amp;&#10;&#10;  x</span>
            </div>
            </div>
            </details>
            HTML,
        ];
        yield 'key with consecutive line breaks' => [
            DumpNode::block(DumpKind::ARRAY, '', [new DumpEntry("<a>\n\n\r\nb", DumpNode::integer('1'))]),
            <<<'HTML'
            <details class="yii-debug-log-dump" open>
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-count">1 entry</span>
            </summary><div class="yii-debug-log-dump-entries">
            <div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">&lt;a&gt;&#10;&#10;&#10;b</span><span class="yii-debug-log-dump-value yii-debug-log-dump-value-number">1</span>
            </div>
            </div>
            </details>
            HTML,
        ];
        yield 'nested blocks' => [
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [
                    new DumpEntry(
                        'list',
                        DumpNode::block(
                            DumpKind::ARRAY,
                            '',
                            [new DumpEntry('0', DumpNode::scalar('a')), new DumpEntry('1', DumpNode::scalar('b'))],
                        ),
                    ),
                    new DumpEntry('none', DumpNode::block(DumpKind::ARRAY, '', [])),
                    new DumpEntry(
                        'suit',
                        DumpNode::block(DumpKind::ENUM, 'App\Suit', [new DumpEntry('name', DumpNode::scalar('HEARTS'))]),
                    ),
                    new DumpEntry(
                        'user',
                        DumpNode::block(DumpKind::OBJECT, 'stdClass', [new DumpEntry('id', DumpNode::scalar('7'))]),
                    ),
                ],
            ),
            <<<'HTML'
            <details class="yii-debug-log-dump" open>
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-count">4 entries</span>
            </summary><div class="yii-debug-log-dump-entries">
            <details class="yii-debug-log-dump-node">
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-key">list</span><span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-count">2 entries</span>
            </summary><div class="yii-debug-log-dump-entries">
            <div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">0</span><span class="yii-debug-log-dump-value">a</span>
            </div><div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">1</span><span class="yii-debug-log-dump-value">b</span>
            </div>
            </div>
            </details><div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">none</span><span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-count">0 entries</span>
            </div><details class="yii-debug-log-dump-node">
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-key">suit</span><span title="App\Suit"><span class="yii-debug-muted">App\</span><wbr><strong>Suit</strong></span><span class="yii-debug-log-dump-kind">enum</span><span class="yii-debug-log-dump-count">1 entry</span>
            </summary><div class="yii-debug-log-dump-entries">
            <div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">name</span><span class="yii-debug-log-dump-value">HEARTS</span>
            </div>
            </div>
            </details><details class="yii-debug-log-dump-node">
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-key">user</span><span title="stdClass"><strong>stdClass</strong></span><span class="yii-debug-log-dump-kind">object</span><span class="yii-debug-log-dump-count">1 entry</span>
            </summary><div class="yii-debug-log-dump-entries">
            <div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">id</span><span class="yii-debug-log-dump-value">7</span>
            </div>
            </div>
            </details>
            </div>
            </details>
            HTML,
        ];
        yield 'prefixed object with visibility chips and empty value' => [
            DumpNode::block(
                DumpKind::OBJECT,
                'App\Http\Request',
                [
                    new DumpEntry('method', DumpNode::scalar('GET'), 'protected'),
                    new DumpEntry('body', DumpNode::scalar(''), 'private', 'App\Http\Message'),
                    new DumpEntry('id', DumpNode::scalar('7')),
                ],
                'Request:',
            ),
            <<<'HTML'
            <details class="yii-debug-log-dump" open>
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-prefix">Request:</span><span title="App\Http\Request"><span class="yii-debug-muted">App\Http\</span><wbr><strong>Request</strong></span><span class="yii-debug-log-dump-kind">object</span><span class="yii-debug-log-dump-count">3 entries</span>
            </summary><div class="yii-debug-log-dump-entries">
            <div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">method</span><span class="yii-debug-log-dump-visibility" title="Protected property; print_r() does not name the declaring class">protected</span><span class="yii-debug-log-dump-value">GET</span>
            </div><div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">body</span><span class="yii-debug-log-dump-visibility" title="Private property declared in App\Http\Message">private</span><span class="yii-debug-log-dump-empty" title="print_r() prints an empty string, null, and false the same way">empty</span>
            </div><div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">id</span><span class="yii-debug-log-dump-value">7</span>
            </div>
            </div>
            </details>
            HTML,
        ];
        yield 'recursion markers' => [
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [
                    new DumpEntry('0', DumpNode::recursion('')),
                    new DumpEntry('self', DumpNode::recursion('App\Node'), 'private', 'App\Node'),
                ],
            ),
            <<<'HTML'
            <details class="yii-debug-log-dump" open>
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-count">2 entries</span>
            </summary><div class="yii-debug-log-dump-entries">
            <div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">0</span><span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-recursion" title="print_r() stopped here because the value contains itself">recursion</span>
            </div><div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">self</span><span class="yii-debug-log-dump-visibility" title="Private property declared in App\Node">private</span><span title="App\Node"><span class="yii-debug-muted">App\</span><wbr><strong>Node</strong></span><span class="yii-debug-log-dump-kind">object</span><span class="yii-debug-log-dump-recursion" title="print_r() stopped here because the value contains itself">recursion</span>
            </div>
            </div>
            </details>
            HTML,
        ];
        yield 'typed values' => [
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [
                    new DumpEntry('name', DumpNode::string('admin')),
                    new DumpEntry('initial', DumpNode::string('a')),
                    new DumpEntry('accent', DumpNode::string('é')),
                    new DumpEntry('blank', DumpNode::string('')),
                    new DumpEntry('note', DumpNode::string("say \"hi\"\r\n\n<b>")),
                    new DumpEntry('id', DumpNode::integer('-9223372036854775807-1')),
                    new DumpEntry('ratio', DumpNode::float('1.0E+25')),
                    new DumpEntry('active', DumpNode::boolean('true')),
                    new DumpEntry('parent', DumpNode::null('null')),
                    new DumpEntry('suit', DumpNode::enumCase('App\Suit', 'Hearts')),
                ],
            ),
            <<<'HTML'
            <details class="yii-debug-log-dump" open>
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-kind">array</span><span class="yii-debug-log-dump-count">10 entries</span>
            </summary><div class="yii-debug-log-dump-entries">
            <div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">name</span><span class="yii-debug-log-dump-value yii-debug-log-dump-value-string" title="5 bytes">"admin"</span>
            </div><div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">initial</span><span class="yii-debug-log-dump-value yii-debug-log-dump-value-string" title="1 byte">"a"</span>
            </div><div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">accent</span><span class="yii-debug-log-dump-value yii-debug-log-dump-value-string" title="2 bytes">"é"</span>
            </div><div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">blank</span><span class="yii-debug-log-dump-value yii-debug-log-dump-value-string" title="0 bytes">""</span>
            </div><div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">note</span><span class="yii-debug-log-dump-value yii-debug-log-dump-value-string" title="14 bytes">"say "hi"&#10;&#10;&lt;b&gt;"</span>
            </div><div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">id</span><span class="yii-debug-log-dump-value yii-debug-log-dump-value-number">-9223372036854775807-1</span>
            </div><div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">ratio</span><span class="yii-debug-log-dump-value yii-debug-log-dump-value-number">1.0E+25</span>
            </div><div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">active</span><span class="yii-debug-log-dump-value yii-debug-log-dump-value-bool">true</span>
            </div><div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">parent</span><span class="yii-debug-log-dump-value yii-debug-log-dump-value-null">null</span>
            </div><div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">suit</span><span title="App\Suit"><span class="yii-debug-muted">App\</span><wbr><strong>Suit</strong></span><span class="yii-debug-log-dump-kind">enum</span><span class="yii-debug-log-dump-value">Hearts</span>
            </div>
            </div>
            </details>
            HTML,
        ];
        yield 'prefixed enum block' => [
            DumpNode::block(DumpKind::ENUM, 'App\Suit', [new DumpEntry('name', DumpNode::scalar('HEARTS'))], 'Suit:'),
            <<<'HTML'
            <details class="yii-debug-log-dump" open>
            <summary class="yii-debug-log-dump-summary">
            <span class="yii-debug-log-dump-prefix">Suit:</span><span title="App\Suit"><span class="yii-debug-muted">App\</span><wbr><strong>Suit</strong></span><span class="yii-debug-log-dump-kind">enum</span><span class="yii-debug-log-dump-count">1 entry</span>
            </summary><div class="yii-debug-log-dump-entries">
            <div class="yii-debug-log-dump-row">
            <span class="yii-debug-log-dump-key">name</span><span class="yii-debug-log-dump-value">HEARTS</span>
            </div>
            </div>
            </details>
            HTML,
        ];
        yield 'empty root object' => [
            DumpNode::block(DumpKind::OBJECT, 'stdClass', []),
            <<<'HTML'
            <details class="yii-debug-log-dump" open>
            <summary class="yii-debug-log-dump-summary">
            <span title="stdClass"><strong>stdClass</strong></span><span class="yii-debug-log-dump-kind">object</span><span class="yii-debug-log-dump-count">0 entries</span>
            </summary><div class="yii-debug-log-dump-entries">
            </div>
            </details>
            HTML,
        ];
    }
}
