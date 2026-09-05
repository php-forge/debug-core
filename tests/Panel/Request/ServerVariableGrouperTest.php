<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Request;

use PHPForge\Debug\Panel\Request\ServerVariableGrouper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function array_keys;

/**
 * Unit tests for deterministic server-variable grouping.
 */
#[Group('panel')]
#[Group('request')]
final class ServerVariableGrouperTest extends TestCase
{
    public function testGroupClassifiesEveryEntryOnceAndPreservesOrderInsideGroups(): void
    {
        $groups = ServerVariableGrouper::group(
            [
                'APP_ENV' => 'debug',
                'REQUEST_METHOD' => 'GET',
                'REMOTE_ADDR' => '127.0.0.1',
                'SCRIPT_FILENAME' => '/app/index.php',
                'HTTP_HOST' => 'localhost',
                7 => ['legacy'],
                'SERVER_SOFTWARE' => 'PHP',
                'CONTENT_TYPE' => 'application/json',
                'PATH_INFO' => '/orders',
                'SSL_PROTOCOL' => 'TLSv1.3',
                'REDIRECT_HTTP_AUTHORIZATION' => '[redacted]',
                'FCGI_ROLE' => 'RESPONDER',
            ],
        );

        $entries = [];

        foreach ($groups as $group) {
            $entries[$group->id] = array_keys($group->entries);
        }

        self::assertSame(
            [
                'request-context',
                'network-transport',
                'runtime-paths',
                'header-mirrors',
                'environment-other',
            ],
            array_map(static fn($group): string => $group->id, $groups),
            'Groups must follow one stable reading order.',
        );
        self::assertSame(
            ['REQUEST_METHOD', 'PATH_INFO'],
            $entries['request-context'] ?? null,
            'Request context must preserve capture order.',
        );
        self::assertSame(
            ['REMOTE_ADDR', 'SSL_PROTOCOL'],
            $entries['network-transport'] ?? null,
            'Transport variables must preserve capture order.',
        );
        self::assertSame(
            ['SCRIPT_FILENAME', 'SERVER_SOFTWARE', 'FCGI_ROLE'],
            $entries['runtime-paths'] ?? null,
            'Runtime variables must not be swallowed by broader SERVER-like names.',
        );
        self::assertSame(
            ['HTTP_HOST', 'CONTENT_TYPE', 'REDIRECT_HTTP_AUTHORIZATION'],
            $entries['header-mirrors'] ?? null,
            'CGI header mirrors must share the collapsed group.',
        );
        self::assertTrue(
            in_array(true, array_map(static fn($group): bool => $group->collapsed, $groups), true),
            'Header mirrors must be collapsed by default.',
        );
        self::assertSame(
            ['APP_ENV', 7],
            $entries['environment-other'] ?? null,
            'Unknown and non-string keys must remain in the catch-all group.',
        );
        self::assertSame(
            12,
            array_sum(array_map(static fn($group): int => count($group->entries), $groups)),
            'Grouping must neither drop nor duplicate captured entries.',
        );
    }

    public function testGroupIsCaseInsensitiveForClassificationButPreservesOriginalKeys(): void
    {
        $groups = ServerVariableGrouper::group(
            [
                'request_method' => 'GET',
                'server_name' => 'localhost',
                'script_name' => '/index.php',
                'http_accept' => 'text/html',
            ],
        );

        $entries = [];

        foreach ($groups as $group) {
            $entries[$group->id] = array_keys($group->entries);
        }

        self::assertSame(
            ['request_method'],
            $entries['request-context'] ?? null,
            'Request key casing must survive.',
        );
        self::assertSame(
            ['server_name'],
            $entries['network-transport'] ?? null,
            'Network key casing must survive.',
        );
        self::assertSame(
            ['script_name'],
            $entries['runtime-paths'] ?? null,
            'Runtime key casing must survive.',
        );
        self::assertSame(
            ['http_accept'],
            $entries['header-mirrors'] ?? null,
            'Header key casing must survive.',
        );
    }

    public function testGroupOmitsEmptyCategories(): void
    {
        self::assertSame(
            [],
            ServerVariableGrouper::group([]),
            'An empty capture must not invent groups.',
        );
        self::assertSame(
            ['environment-other'],
            array_map(
                static fn($group): string => $group->id,
                ServerVariableGrouper::group(['CUSTOM_VALUE' => true]),
            ),
            'Only populated groups should be returned.',
        );
    }
}
