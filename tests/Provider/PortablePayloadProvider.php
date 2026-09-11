<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Tests\Collector\PortablePayloadTest;

/**
 * Data provider for {@see PortablePayloadTest} test cases.
 */
final class PortablePayloadProvider
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'capture' => ['capture'];
        yield 'depth' => ['depth'];
        yield 'inf' => ['inf'];
        yield 'nan' => ['nan'];
        yield 'recursive' => ['recursive'];
        yield 'resource' => ['resource'];
        yield 'serialize' => ['serialize'];
        yield 'utf8' => ['utf8'];
    }
}
