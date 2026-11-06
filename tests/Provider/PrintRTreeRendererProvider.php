<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Panel\Log\{PrintREntry, PrintRKind, PrintRNode};
use PHPForge\Debug\Tests\Panel\Log\PrintRTreeRendererTest;

/**
 * Data provider for {@see PrintRTreeRendererTest} test cases.
 *
 * Provides dump trees with the exact markup they render to.
 */
final class PrintRTreeRendererProvider
{
    /**
     * @return iterable<string, array{PrintRNode, string}>
     */
    public static function trees(): iterable
    {
        yield 'array with one entry' => [
            PrintRNode::block(
                PrintRKind::ARRAY,
                '',
                [new PrintREntry('id', PrintRNode::scalar('7'))],
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
            PrintRNode::block(
                PrintRKind::ARRAY,
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
            PrintRNode::block(
                PrintRKind::OBJECT,
                'App\<X>',
                [new PrintREntry('<k>', PrintRNode::scalar("<script>&\n\n  x"), 'private', 'App\<C>')],
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
        yield 'nested blocks' => [
            PrintRNode::block(
                PrintRKind::ARRAY,
                '',
                [
                    new PrintREntry(
                        'list',
                        PrintRNode::block(
                            PrintRKind::ARRAY,
                            '',
                            [new PrintREntry('0', PrintRNode::scalar('a')), new PrintREntry('1', PrintRNode::scalar('b'))],
                        ),
                    ),
                    new PrintREntry('none', PrintRNode::block(PrintRKind::ARRAY, '', [])),
                    new PrintREntry(
                        'suit',
                        PrintRNode::block(PrintRKind::ENUM, 'App\Suit', [new PrintREntry('name', PrintRNode::scalar('HEARTS'))]),
                    ),
                    new PrintREntry(
                        'user',
                        PrintRNode::block(PrintRKind::OBJECT, 'stdClass', [new PrintREntry('id', PrintRNode::scalar('7'))]),
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
            PrintRNode::block(
                PrintRKind::OBJECT,
                'App\Http\Request',
                [
                    new PrintREntry('method', PrintRNode::scalar('GET'), 'protected'),
                    new PrintREntry('body', PrintRNode::scalar(''), 'private', 'App\Http\Message'),
                    new PrintREntry('id', PrintRNode::scalar('7')),
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
            PrintRNode::block(
                PrintRKind::ARRAY,
                '',
                [
                    new PrintREntry('0', PrintRNode::recursion('')),
                    new PrintREntry('self', PrintRNode::recursion('App\Node'), 'private', 'App\Node'),
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
    }
}
