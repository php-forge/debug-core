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
    /**
     * @var array<string, array{string, bool}> Heading and collapsed state of every group, in display order.
     */
    private const array DEFINITIONS = [
        'request-context' => [RequestMessage::REQUEST_CONTEXT->value, false],
        'network-transport' => [RequestMessage::NETWORK_TRANSPORT->value, false],
        'runtime-paths' => [RequestMessage::RUNTIME_PATHS->value, false],
        'header-mirrors' => [RequestMessage::HEADER_MIRRORS->value, true],
        'environment-other' => [RequestMessage::ENVIRONMENT_OTHER->value, false],
    ];
    /**
     * @var list<string> Variables mirroring a content header, grouped apart from the request context.
     */
    private const array HEADER_MIRRORS = [
        'CONTENT_TYPE',
        'CONTENT_LENGTH',
        'CONTENT_MD5',
    ];
    /**
     * @var list<string> Variables describing the connection, beyond the `REMOTE_` and `SSL_` prefixes.
     */
    private const array NETWORK_KEYS = [
        'SERVER_ADDR',
        'SERVER_NAME',
        'SERVER_PORT',
        'SERVER_PROTOCOL',
        'HTTPS',
        'GATEWAY_INTERFACE',
    ];
    /**
     * @var list<string> Variables describing the request, beyond the `REQUEST_` prefix.
     */
    private const array REQUEST_KEYS = [
        'QUERY_STRING',
        'PATH_INFO',
        'ORIG_PATH_INFO',
    ];
    /**
     * @var list<string> Variables describing the runtime and its paths, beyond the handled prefixes.
     */
    private const array RUNTIME_KEYS = [
        'SERVER_SOFTWARE',
        'DOCUMENT_ROOT',
        'PHP_SELF',
        'PATH_TRANSLATED',
    ];

    /**
     * Partitions the captured variables into the declared groups, dropping the groups that stay empty.
     *
     * @param array<int|string, mixed> $entries Captured server variables, keyed by variable name.
     *
     * @return list<ServerVariableGroup> Non-empty groups in display order.
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

    /**
     * Resolves the group a captured variable belongs to.
     *
     * @param int|string $key Captured variable name; a non-string key falls back to the catch-all group.
     *
     * @return string Identifier of the group claiming the variable.
     */
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
