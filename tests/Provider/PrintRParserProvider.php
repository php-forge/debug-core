<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Panel\Log\{PrintREntry, PrintRKind, PrintRNode};
use PHPForge\Debug\Tests\Panel\Log\PrintRParserTest;
use PHPForge\Debug\Tests\Support\PrintR\{PrintRChild, PrintRFixture, PrintRParent, PrintRPriority, PrintRSuit, PrintRToggle};
use stdClass;

use function print_r;

/**
 * Data provider for {@see PrintRParserTest} test cases.
 */
final class PrintRParserProvider
{
    /**
     * @return iterable<string, array{string, PrintRNode}>
     */
    public static function dumps(): iterable
    {
        $suit = PrintRNode::block(
            PrintRKind::ENUM,
            PrintRSuit::class,
            [
                new PrintREntry('name', PrintRNode::scalar('HEARTS')),
                new PrintREntry('value', PrintRNode::scalar('H')),
            ],
        );

        yield 'array keys stay verbatim' => [
            print_r(['a:b' => 'x', 'k:protected' => 'y', 'p:C:private' => 'z', '' => 'blank', 'a]b' => 'bracket'], true),
            PrintRNode::block(
                PrintRKind::ARRAY,
                '',
                [
                    new PrintREntry('a:b', PrintRNode::scalar('x')),
                    new PrintREntry('k:protected', PrintRNode::scalar('y')),
                    new PrintREntry('p:C:private', PrintRNode::scalar('z')),
                    new PrintREntry('', PrintRNode::scalar('blank')),
                    new PrintREntry('a]b', PrintRNode::scalar('bracket')),
                ],
            ),
        ];
        yield 'array recursion' => [
            (static function (): string {
                $value = [1];
                $value[] = &$value;

                return print_r($value, true);
            })(),
            PrintRNode::block(
                PrintRKind::ARRAY,
                '',
                [
                    new PrintREntry('0', PrintRNode::scalar('1')),
                    new PrintREntry('1', PrintRNode::recursion('')),
                ],
            ),
        ];
        yield 'empty root array' => [
            print_r([], true),
            PrintRNode::block(PrintRKind::ARRAY, '', []),
        ];
        yield 'empty values' => [
            print_r(['string' => '', 'null' => null, 'false' => false, 'true' => true, 'zero' => 0], true),
            PrintRNode::block(
                PrintRKind::ARRAY,
                '',
                [
                    new PrintREntry('string', PrintRNode::scalar('')),
                    new PrintREntry('null', PrintRNode::scalar('')),
                    new PrintREntry('false', PrintRNode::scalar('')),
                    new PrintREntry('true', PrintRNode::scalar('1')),
                    new PrintREntry('zero', PrintRNode::scalar('0')),
                ],
            ),
        ];
        yield 'header text as scalar value' => [
            print_r(['cast' => 'Array', 'object' => 'App\User Object', 'enum' => 'App\Suit Enum:string', 'n' => 1], true),
            PrintRNode::block(
                PrintRKind::ARRAY,
                '',
                [
                    new PrintREntry('cast', PrintRNode::scalar('Array')),
                    new PrintREntry('object', PrintRNode::scalar('App\User Object')),
                    new PrintREntry('enum', PrintRNode::scalar('App\Suit Enum:string')),
                    new PrintREntry('n', PrintRNode::scalar('1')),
                ],
            ),
        ];
        yield 'int-backed enum' => [
            print_r(PrintRPriority::HIGH, true),
            PrintRNode::block(
                PrintRKind::ENUM,
                PrintRPriority::class,
                [
                    new PrintREntry('name', PrintRNode::scalar('HIGH')),
                    new PrintREntry('value', PrintRNode::scalar('1')),
                ],
            ),
        ];
        yield 'multi-line strings' => [
            print_r(
                [
                    'note' => "line one\n  [nested] => not an entry\n\nline four\n",
                    'list' => ['first' => "a\nb", 'last' => "c\n)"],
                    'next' => 'x',
                ],
                true,
            ),
            PrintRNode::block(
                PrintRKind::ARRAY,
                '',
                [
                    new PrintREntry('note', PrintRNode::scalar("line one\n  [nested] => not an entry\n\nline four\n")),
                    new PrintREntry(
                        'list',
                        PrintRNode::block(
                            PrintRKind::ARRAY,
                            '',
                            [
                                new PrintREntry('first', PrintRNode::scalar("a\nb")),
                                new PrintREntry('last', PrintRNode::scalar("c\n)")),
                            ],
                        ),
                    ),
                    new PrintREntry('next', PrintRNode::scalar('x')),
                ],
            ),
        ];
        yield 'nested header with free text stays scalar' => [
            "Array\n(\n    [k] => note Array\n        (\n        )\n\n)\n",
            PrintRNode::block(
                PrintRKind::ARRAY,
                '',
                [
                    new PrintREntry('k', PrintRNode::scalar("note Array\n        (\n        )\n")),
                ],
            ),
        ];
        yield 'object dynamic keys stay verbatim' => [
            (static function (): string {
                $object = new stdClass();
                $object->{'a:b'} = 'x';
                $object->{'x:private'} = 'y';
                $object->{'a:b:protected'} = 'z';
                $object->{'x:protectedish'} = 'w';

                return print_r($object, true);
            })(),
            PrintRNode::block(
                PrintRKind::OBJECT,
                'stdClass',
                [
                    new PrintREntry('a:b', PrintRNode::scalar('x')),
                    new PrintREntry('x:private', PrintRNode::scalar('y')),
                    new PrintREntry('a:b:protected', PrintRNode::scalar('z')),
                    new PrintREntry('x:protectedish', PrintRNode::scalar('w')),
                ],
            ),
        ];
        yield 'stdClass keys shaped like a visibility stay verbatim' => [
            (static function (): string {
                $inner = new stdClass();
                $inner->{'id:App\User:private'} = 7;
                $object = new stdClass();
                $object->{'status:protected'} = 'on';
                $object->inner = $inner;

                return print_r($object, true);
            })(),
            PrintRNode::block(
                PrintRKind::OBJECT,
                'stdClass',
                [
                    new PrintREntry('status:protected', PrintRNode::scalar('on')),
                    new PrintREntry(
                        'inner',
                        PrintRNode::block(
                            PrintRKind::OBJECT,
                            'stdClass',
                            [new PrintREntry('id:App\User:private', PrintRNode::scalar('7'))],
                        ),
                    ),
                ],
            ),
        ];
        yield 'object with visibility, inheritance, enum, and recursion' => [
            PrintRFixture::child(),
            PrintRNode::block(
                PrintRKind::OBJECT,
                PrintRChild::class,
                [
                    new PrintREntry('role', PrintRNode::scalar('admin'), 'protected'),
                    new PrintREntry('secret', PrintRNode::scalar('parent'), 'private', PrintRParent::class),
                    new PrintREntry('empty', PrintRNode::scalar('')),
                    new PrintREntry('flag', PrintRNode::scalar('')),
                    new PrintREntry('name', PrintRNode::scalar('Ada')),
                    new PrintREntry('self', PrintRNode::recursion(PrintRChild::class)),
                    new PrintREntry('suit', $suit),
                    new PrintREntry(
                        'tags',
                        PrintRNode::block(
                            PrintRKind::ARRAY,
                            '',
                            [
                                new PrintREntry('0', PrintRNode::scalar('a')),
                                new PrintREntry('1', PrintRNode::scalar('b')),
                            ],
                        ),
                    ),
                    new PrintREntry('parent', PrintRNode::scalar(''), 'protected'),
                    new PrintREntry('secret', PrintRNode::scalar('child'), 'private', PrintRChild::class),
                ],
            ),
        ];
        yield 'prefix before the root header' => [
            'Request: ' . print_r(['id' => 7], true),
            PrintRNode::block(PrintRKind::ARRAY, '', [new PrintREntry('id', PrintRNode::scalar('7'))], 'Request:'),
        ];
        yield 'prefix before an object header' => [
            'Loaded user App\User Object' . "\n(\n    [id] => 7\n)\n",
            PrintRNode::block(
                PrintRKind::OBJECT,
                'App\User',
                [new PrintREntry('id', PrintRNode::scalar('7'))],
                'Loaded user',
            ),
        ];
        yield 'pure enum' => [
            print_r(PrintRToggle::ON, true),
            PrintRNode::block(PrintRKind::ENUM, PrintRToggle::class, [new PrintREntry('name', PrintRNode::scalar('ON'))]),
        ];
        yield 'server request' => [
            PrintRFixture::serverRequest(),
            self::serverRequest(),
        ];
        yield 'string-backed enum' => [
            print_r(PrintRSuit::HEARTS, true),
            $suit,
        ];
        yield 'trailing whitespace' => [
            print_r(['id' => 7], true) . " \t\n\n",
            PrintRNode::block(PrintRKind::ARRAY, '', [new PrintREntry('id', PrintRNode::scalar('7'))]),
        ];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalid(): iterable
    {
        yield 'empty message' => [''];
        yield 'plain text' => ['User 7 logged in'];
        yield 'header without block' => ['Array'];
        yield 'header glued to free text' => ["MyArray\n(\n)\n"];
        yield 'header followed by free text' => ["Array of users\n(\n)\n"];
        yield 'text line before the header' => ["Request:\nArray\n(\n)\n"];
        yield 'missing opening parenthesis' => ["Array\n    [a] => 1\n)\n"];
        yield 'indented opening parenthesis' => ["Array\n (\n)\n"];
        yield 'recursion marker at the root' => ["Array\n *RECURSION*\n"];
        yield 'entry without arrow' => ["Array\n(\n    [a] = 1\n)\n"];
        yield 'entry without brackets' => ["Array\n(\n    a => 1\n)\n"];
        yield 'entry at the wrong indentation' => ["Array\n(\n  [a] => 1\n)\n"];
        yield 'unterminated block' => ["Array\n(\n    [a] => 1\n"];
        yield 'unterminated nested block' => ["Array\n(\n    [a] => Array\n        (\n            [b] => 1\n)\n"];
        yield 'nested block without blank line' => ["Array\n(\n    [a] => Array\n        (\n        )\n    [b] => 1\n)\n"];
        yield 'nested block followed by text' => ["Array\n(\n    [a] => Array\n        (\n        )\nx\n)\n"];
        yield 'nested block at the end of the message' => ["Array\n(\n    [a] => Array\n        (\n        )"];
        yield 'malformed nested entry' => ["Array\n(\n    [a] => Array\n        (\n            b\n        )\n\n)\n"];
        yield 'text after the root block' => [print_r([1], true) . 'done'];
        yield 'second dump after the root block' => [print_r([1], true) . print_r([2], true)];
    }

    /**
     * Returns the tree of the server request dump fixture.
     */
    private static function serverRequest(): PrintRNode
    {
        $request = 'HttpSoft\Message\ServerRequest';
        $uri = 'HttpSoft\Message\Uri';
        $stream = 'HttpSoft\Message\Stream';

        return PrintRNode::block(
            PrintRKind::OBJECT,
            $request,
            [
                new PrintREntry(
                    'attributes',
                    PrintRNode::block(
                        PrintRKind::ARRAY,
                        '',
                        [new PrintREntry('applicationStartTime', PrintRNode::scalar('1793938706.0155'))],
                    ),
                    'private',
                    $request,
                ),
                new PrintREntry(
                    'cookieParams',
                    PrintRNode::block(
                        PrintRKind::ARRAY,
                        '',
                        [new PrintREntry('yii-debug-toolbar-theme', PrintRNode::scalar('dark'))],
                    ),
                    'private',
                    $request,
                ),
                new PrintREntry('parsedBody', PrintRNode::scalar(''), 'private', $request),
                new PrintREntry('queryParams', PrintRNode::block(PrintRKind::ARRAY, '', []), 'private', $request),
                new PrintREntry(
                    'serverParams',
                    PrintRNode::block(
                        PrintRKind::ARRAY,
                        '',
                        [
                            new PrintREntry('USER', PrintRNode::scalar('www-data')),
                            new PrintREntry('HTTP_PRIORITY', PrintRNode::scalar('u=0, i')),
                            new PrintREntry(
                                'HTTP_ACCEPT',
                                PrintRNode::scalar('text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'),
                            ),
                            new PrintREntry(
                                'HTTP_USER_AGENT',
                                PrintRNode::scalar(
                                    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:159.0) Gecko/20100101 Firefox/159.0',
                                ),
                            ),
                            new PrintREntry('REQUEST_METHOD', PrintRNode::scalar('GET')),
                            new PrintREntry('CONTENT_LENGTH', PrintRNode::scalar('')),
                            new PrintREntry('REQUEST_TIME_FLOAT', PrintRNode::scalar('1793938706.0138')),
                        ],
                    ),
                    'private',
                    $request,
                ),
                new PrintREntry('uploadedFiles', PrintRNode::block(PrintRKind::ARRAY, '', []), 'private', $request),
                new PrintREntry('method', PrintRNode::scalar('GET'), 'private', $request),
                new PrintREntry('requestTarget', PrintRNode::scalar(''), 'private', $request),
                new PrintREntry(
                    'uri',
                    PrintRNode::block(
                        PrintRKind::OBJECT,
                        $uri,
                        [
                            new PrintREntry('scheme', PrintRNode::scalar('http'), 'private', $uri),
                            new PrintREntry('userInfo', PrintRNode::scalar(''), 'private', $uri),
                            new PrintREntry('host', PrintRNode::scalar('ets-api.test'), 'private', $uri),
                            new PrintREntry('port', PrintRNode::scalar('80'), 'private', $uri),
                            new PrintREntry('path', PrintRNode::scalar('/'), 'private', $uri),
                            new PrintREntry('query', PrintRNode::scalar(''), 'private', $uri),
                            new PrintREntry('fragment', PrintRNode::scalar(''), 'private', $uri),
                            new PrintREntry('cache', PrintRNode::scalar(''), 'private', $uri),
                        ],
                    ),
                    'private',
                    $request,
                ),
                new PrintREntry(
                    'headers',
                    PrintRNode::block(
                        PrintRKind::ARRAY,
                        '',
                        [
                            new PrintREntry(
                                'Host',
                                PrintRNode::block(
                                    PrintRKind::ARRAY,
                                    '',
                                    [new PrintREntry('0', PrintRNode::scalar('ets-api.test'))],
                                ),
                            ),
                            new PrintREntry(
                                'Content-Length',
                                PrintRNode::block(PrintRKind::ARRAY, '', [new PrintREntry('0', PrintRNode::scalar(''))]),
                            ),
                        ],
                    ),
                    'private',
                    $request,
                ),
                new PrintREntry('protocol', PrintRNode::scalar('1.1'), 'private', $request),
                new PrintREntry(
                    'stream',
                    PrintRNode::block(
                        PrintRKind::OBJECT,
                        $stream,
                        [
                            new PrintREntry('resource', PrintRNode::scalar('Resource id #6'), 'private', $stream),
                            new PrintREntry('size', PrintRNode::scalar(''), 'private', $stream),
                            new PrintREntry('seekable', PrintRNode::scalar('1'), 'private', $stream),
                        ],
                    ),
                    'private',
                    $request,
                ),
            ],
        );
    }
}
