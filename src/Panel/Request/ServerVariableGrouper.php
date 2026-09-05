<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request;

use function in_array;
use function is_string;
use function str_starts_with;
use function strtoupper;

/**
 * Partitions captured server variables into stable, framework-neutral diagnostic groups.
 */
final class ServerVariableGrouper
{
    private const array DEFINITIONS = [
        'request-context' => ['Request context', false],
        'network-transport' => ['Network & transport', false],
        'runtime-paths' => ['Runtime & paths', false],
        'header-mirrors' => ['Header mirrors', true],
        'environment-other' => ['Environment & other', false],
    ];

    private const array HEADER_MIRRORS = ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'];

    private const array NETWORK_KEYS = [
        'SERVER_ADDR',
        'SERVER_NAME',
        'SERVER_PORT',
        'SERVER_PROTOCOL',
        'HTTPS',
        'GATEWAY_INTERFACE',
    ];

    private const array REQUEST_KEYS = ['QUERY_STRING', 'PATH_INFO', 'ORIG_PATH_INFO'];

    private const array RUNTIME_KEYS = [
        'SERVER_SOFTWARE',
        'DOCUMENT_ROOT',
        'PHP_SELF',
        'PATH_TRANSLATED',
    ];

    /**
     * @param array<int|string, mixed> $entries
     *
     * @return list<ServerVariableGroup>
     */
    public static function group(array $entries): array
    {
        $grouped = [];

        foreach (self::DEFINITIONS as $id => $_definition) {
            $grouped[$id] = [];
        }

        foreach ($entries as $key => $value) {
            $grouped[self::classify($key)][$key] = $value;
        }

        $groups = [];

        foreach (self::DEFINITIONS as $id => [$label, $collapsed]) {
            if ($grouped[$id] === []) {
                continue;
            }

            $groups[] = new ServerVariableGroup($id, $label, $grouped[$id], $collapsed);
        }

        return $groups;
    }

    private static function classify(int|string $key): string
    {
        if (!is_string($key)) {
            return 'environment-other';
        }

        $key = strtoupper($key);

        if (str_starts_with($key, 'REQUEST_') || in_array($key, self::REQUEST_KEYS, true)) {
            return 'request-context';
        }

        if (
            str_starts_with($key, 'REMOTE_')
            || str_starts_with($key, 'SSL_')
            || in_array($key, self::NETWORK_KEYS, true)
        ) {
            return 'network-transport';
        }

        if (
            str_starts_with($key, 'CONTEXT_')
            || str_starts_with($key, 'SCRIPT_')
            || str_starts_with($key, 'FCGI_')
            || in_array($key, self::RUNTIME_KEYS, true)
        ) {
            return 'runtime-paths';
        }

        if (
            str_starts_with($key, 'HTTP_')
            || str_starts_with($key, 'REDIRECT_HTTP_')
            || in_array($key, self::HEADER_MIRRORS, true)
        ) {
            return 'header-mirrors';
        }

        return 'environment-other';
    }
}
