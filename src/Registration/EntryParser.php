<?php

declare(strict_types=1);

namespace PHPForge\Debug\Registration;

use InvalidArgumentException;
use PHPForge\Debug\Exception\Message;

use function is_array;
use function is_bool;
use function is_string;

/**
 * Reads the collector and panel entries an application declares in its debug configuration.
 *
 * The three checks are separate calls, so each host keeps its own validation order and wraps every
 * {@see InvalidArgumentException} in the message its users already know.
 */
final class EntryParser
{
    /**
     * Rejects a string registration key that differs from the ID the registered object declares.
     *
     * An integer key comes from a list entry, which names no ID and is accepted as is.
     *
     * @param int|string $key Key the configuration registers the entry under.
     * @param string $id ID the registered collector or panel declares.
     * @param string $kind Entry kind named in the failure, such as `'collector'` or `'panel'`.
     *
     * @throws InvalidArgumentException when a string key differs from the declared ID.
     */
    public static function assertKeyMatchesId(int|string $key, string $id, string $kind): void
    {
        if (is_string($key) && $key !== $id) {
            throw new InvalidArgumentException(
                Message::REGISTRATION_ID_MISMATCH->getMessage($kind, $key, $id),
            );
        }
    }

    /**
     * Returns the effective `enabled` flag of an entry.
     *
     * A `null` value counts as absent, as it does for {@see PanelOverride::fromArray()}.
     *
     * @param array<array-key, mixed> $options Entry options, or a whole configuration array declaring `enabled`.
     * @param string $id Configuration ID naming the entry in a failure.
     *
     * @throws InvalidArgumentException when `enabled` is neither a `bool` nor `null`.
     *
     * @return bool Declared flag, or `true` when the entry omits it.
     */
    public static function enabled(array $options, string $id): bool
    {
        $enabled = $options['enabled'] ?? true;

        if (is_bool($enabled) === false) {
            throw new InvalidArgumentException(
                Message::REGISTRATION_ENABLED_INVALID->getMessage($id),
            );
        }

        return $enabled;
    }

    /**
     * Splits one entry into the class to resolve and the options declared beside it.
     *
     * The class is not checked for existence, so a disabled entry naming an uninstalled optional package never
     * reaches the autoloader.
     *
     * @param mixed $entry Class string, or an array declaring a `class` string plus options.
     * @param string $id Configuration ID naming the entry in a failure.
     *
     * @throws InvalidArgumentException when the entry declares no class string.
     *
     * @return ParsedEntry Class and remaining options, `enabled` included.
     */
    public static function parse(mixed $entry, string $id): ParsedEntry
    {
        if (is_string($entry)) {
            return new ParsedEntry($entry);
        }

        if (is_array($entry) === false || is_string($entry['class'] ?? null) === false) {
            throw new InvalidArgumentException(
                Message::REGISTRATION_ENTRY_INVALID->getMessage($id),
            );
        }

        /** @var string $class */
        $class = $entry['class'];

        unset($entry['class']);

        return new ParsedEntry($class, $entry);
    }
}
