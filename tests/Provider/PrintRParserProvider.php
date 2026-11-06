<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Panel\Log\{DumpEntry, DumpKind, DumpNode};
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
     * @return iterable<string, array{string, DumpNode}>
     */
    public static function dumps(): iterable
    {
        $suit = DumpNode::block(
            DumpKind::ENUM,
            PrintRSuit::class,
            [
                new DumpEntry('name', DumpNode::scalar('HEARTS')),
                new DumpEntry('value', DumpNode::scalar('H')),
            ],
        );

        yield 'array keys stay verbatim' => [
            print_r(['a:b' => 'x', 'k:protected' => 'y', 'p:C:private' => 'z', '' => 'blank', 'a]b' => 'bracket'], true),
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [
                    new DumpEntry('a:b', DumpNode::scalar('x')),
                    new DumpEntry('k:protected', DumpNode::scalar('y')),
                    new DumpEntry('p:C:private', DumpNode::scalar('z')),
                    new DumpEntry('', DumpNode::scalar('blank')),
                    new DumpEntry('a]b', DumpNode::scalar('bracket')),
                ],
            ),
        ];
        yield 'array recursion' => [
            (static function (): string {
                $value = [1];
                $value[] = &$value;

                return print_r($value, true);
            })(),
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [
                    new DumpEntry('0', DumpNode::scalar('1')),
                    new DumpEntry('1', DumpNode::recursion('')),
                ],
            ),
        ];
        yield 'empty root array' => [
            print_r([], true),
            DumpNode::block(DumpKind::ARRAY, '', []),
        ];
        yield 'empty values' => [
            print_r(['string' => '', 'null' => null, 'false' => false, 'true' => true, 'zero' => 0], true),
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [
                    new DumpEntry('string', DumpNode::scalar('')),
                    new DumpEntry('null', DumpNode::scalar('')),
                    new DumpEntry('false', DumpNode::scalar('')),
                    new DumpEntry('true', DumpNode::scalar('1')),
                    new DumpEntry('zero', DumpNode::scalar('0')),
                ],
            ),
        ];
        yield 'header text as scalar value' => [
            print_r(['cast' => 'Array', 'object' => 'App\User Object', 'enum' => 'App\Suit Enum:string', 'n' => 1], true),
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [
                    new DumpEntry('cast', DumpNode::scalar('Array')),
                    new DumpEntry('object', DumpNode::scalar('App\User Object')),
                    new DumpEntry('enum', DumpNode::scalar('App\Suit Enum:string')),
                    new DumpEntry('n', DumpNode::scalar('1')),
                ],
            ),
        ];
        yield 'int-backed enum' => [
            print_r(PrintRPriority::HIGH, true),
            DumpNode::block(
                DumpKind::ENUM,
                PrintRPriority::class,
                [
                    new DumpEntry('name', DumpNode::scalar('HIGH')),
                    new DumpEntry('value', DumpNode::scalar('1')),
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
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [
                    new DumpEntry('note', DumpNode::scalar("line one\n  [nested] => not an entry\n\nline four\n")),
                    new DumpEntry(
                        'list',
                        DumpNode::block(
                            DumpKind::ARRAY,
                            '',
                            [
                                new DumpEntry('first', DumpNode::scalar("a\nb")),
                                new DumpEntry('last', DumpNode::scalar("c\n)")),
                            ],
                        ),
                    ),
                    new DumpEntry('next', DumpNode::scalar('x')),
                ],
            ),
        ];
        yield 'nested header with free text stays scalar' => [
            "Array\n(\n    [k] => note Array\n        (\n        )\n\n)\n",
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [
                    new DumpEntry('k', DumpNode::scalar("note Array\n        (\n        )\n")),
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
            DumpNode::block(
                DumpKind::OBJECT,
                'stdClass',
                [
                    new DumpEntry('a:b', DumpNode::scalar('x')),
                    new DumpEntry('x:private', DumpNode::scalar('y')),
                    new DumpEntry('a:b:protected', DumpNode::scalar('z')),
                    new DumpEntry('x:protectedish', DumpNode::scalar('w')),
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
            DumpNode::block(
                DumpKind::OBJECT,
                'stdClass',
                [
                    new DumpEntry('status:protected', DumpNode::scalar('on')),
                    new DumpEntry(
                        'inner',
                        DumpNode::block(
                            DumpKind::OBJECT,
                            'stdClass',
                            [new DumpEntry('id:App\User:private', DumpNode::scalar('7'))],
                        ),
                    ),
                ],
            ),
        ];
        yield 'object with visibility, inheritance, enum, and recursion' => [
            PrintRFixture::child(),
            DumpNode::block(
                DumpKind::OBJECT,
                PrintRChild::class,
                [
                    new DumpEntry('role', DumpNode::scalar('admin'), 'protected'),
                    new DumpEntry('secret', DumpNode::scalar('parent'), 'private', PrintRParent::class),
                    new DumpEntry('empty', DumpNode::scalar('')),
                    new DumpEntry('flag', DumpNode::scalar('')),
                    new DumpEntry('name', DumpNode::scalar('Ada')),
                    new DumpEntry('self', DumpNode::recursion(PrintRChild::class)),
                    new DumpEntry('suit', $suit),
                    new DumpEntry(
                        'tags',
                        DumpNode::block(
                            DumpKind::ARRAY,
                            '',
                            [
                                new DumpEntry('0', DumpNode::scalar('a')),
                                new DumpEntry('1', DumpNode::scalar('b')),
                            ],
                        ),
                    ),
                    new DumpEntry('parent', DumpNode::scalar(''), 'protected'),
                    new DumpEntry('secret', DumpNode::scalar('child'), 'private', PrintRChild::class),
                ],
            ),
        ];
        yield 'prefix before the root header' => [
            'Request: ' . print_r(['id' => 7], true),
            DumpNode::block(DumpKind::ARRAY, '', [new DumpEntry('id', DumpNode::scalar('7'))], 'Request:'),
        ];
        yield 'prefix before an object header' => [
            'Loaded user App\User Object' . "\n(\n    [id] => 7\n)\n",
            DumpNode::block(
                DumpKind::OBJECT,
                'App\User',
                [new DumpEntry('id', DumpNode::scalar('7'))],
                'Loaded user',
            ),
        ];
        yield 'pure enum' => [
            print_r(PrintRToggle::ON, true),
            DumpNode::block(DumpKind::ENUM, PrintRToggle::class, [new DumpEntry('name', DumpNode::scalar('ON'))]),
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
            DumpNode::block(DumpKind::ARRAY, '', [new DumpEntry('id', DumpNode::scalar('7'))]),
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
    private static function serverRequest(): DumpNode
    {
        $request = 'HttpSoft\Message\ServerRequest';
        $uri = 'HttpSoft\Message\Uri';
        $stream = 'HttpSoft\Message\Stream';

        return DumpNode::block(
            DumpKind::OBJECT,
            $request,
            [
                new DumpEntry(
                    'attributes',
                    DumpNode::block(
                        DumpKind::ARRAY,
                        '',
                        [new DumpEntry('applicationStartTime', DumpNode::scalar('1793938706.0155'))],
                    ),
                    'private',
                    $request,
                ),
                new DumpEntry(
                    'cookieParams',
                    DumpNode::block(
                        DumpKind::ARRAY,
                        '',
                        [new DumpEntry('yii-debug-toolbar-theme', DumpNode::scalar('dark'))],
                    ),
                    'private',
                    $request,
                ),
                new DumpEntry('parsedBody', DumpNode::scalar(''), 'private', $request),
                new DumpEntry('queryParams', DumpNode::block(DumpKind::ARRAY, '', []), 'private', $request),
                new DumpEntry(
                    'serverParams',
                    DumpNode::block(
                        DumpKind::ARRAY,
                        '',
                        [
                            new DumpEntry('USER', DumpNode::scalar('www-data')),
                            new DumpEntry('HTTP_PRIORITY', DumpNode::scalar('u=0, i')),
                            new DumpEntry(
                                'HTTP_ACCEPT',
                                DumpNode::scalar('text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'),
                            ),
                            new DumpEntry(
                                'HTTP_USER_AGENT',
                                DumpNode::scalar(
                                    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:159.0) Gecko/20100101 Firefox/159.0',
                                ),
                            ),
                            new DumpEntry('REQUEST_METHOD', DumpNode::scalar('GET')),
                            new DumpEntry('CONTENT_LENGTH', DumpNode::scalar('')),
                            new DumpEntry('REQUEST_TIME_FLOAT', DumpNode::scalar('1793938706.0138')),
                        ],
                    ),
                    'private',
                    $request,
                ),
                new DumpEntry('uploadedFiles', DumpNode::block(DumpKind::ARRAY, '', []), 'private', $request),
                new DumpEntry('method', DumpNode::scalar('GET'), 'private', $request),
                new DumpEntry('requestTarget', DumpNode::scalar(''), 'private', $request),
                new DumpEntry(
                    'uri',
                    DumpNode::block(
                        DumpKind::OBJECT,
                        $uri,
                        [
                            new DumpEntry('scheme', DumpNode::scalar('http'), 'private', $uri),
                            new DumpEntry('userInfo', DumpNode::scalar(''), 'private', $uri),
                            new DumpEntry('host', DumpNode::scalar('ets-api.test'), 'private', $uri),
                            new DumpEntry('port', DumpNode::scalar('80'), 'private', $uri),
                            new DumpEntry('path', DumpNode::scalar('/'), 'private', $uri),
                            new DumpEntry('query', DumpNode::scalar(''), 'private', $uri),
                            new DumpEntry('fragment', DumpNode::scalar(''), 'private', $uri),
                            new DumpEntry('cache', DumpNode::scalar(''), 'private', $uri),
                        ],
                    ),
                    'private',
                    $request,
                ),
                new DumpEntry(
                    'headers',
                    DumpNode::block(
                        DumpKind::ARRAY,
                        '',
                        [
                            new DumpEntry(
                                'Host',
                                DumpNode::block(
                                    DumpKind::ARRAY,
                                    '',
                                    [new DumpEntry('0', DumpNode::scalar('ets-api.test'))],
                                ),
                            ),
                            new DumpEntry(
                                'Content-Length',
                                DumpNode::block(DumpKind::ARRAY, '', [new DumpEntry('0', DumpNode::scalar(''))]),
                            ),
                        ],
                    ),
                    'private',
                    $request,
                ),
                new DumpEntry('protocol', DumpNode::scalar('1.1'), 'private', $request),
                new DumpEntry(
                    'stream',
                    DumpNode::block(
                        DumpKind::OBJECT,
                        $stream,
                        [
                            new DumpEntry('resource', DumpNode::scalar('Resource id #6'), 'private', $stream),
                            new DumpEntry('size', DumpNode::scalar(''), 'private', $stream),
                            new DumpEntry('seekable', DumpNode::scalar('1'), 'private', $stream),
                        ],
                    ),
                    'private',
                    $request,
                ),
            ],
        );
    }
}
