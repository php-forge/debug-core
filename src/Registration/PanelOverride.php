<?php

declare(strict_types=1);

namespace PHPForge\Debug\Registration;

use InvalidArgumentException;
use PHPForge\Debug\Exception\Message;
use PHPForge\Debug\Helper\Icon;

use function array_keys;
use function implode;
use function in_array;
use function is_bool;
use function is_int;
use function is_string;
use function preg_match;

/**
 * Represents the panel metadata an application overrides in its debug configuration.
 *
 * Every property stays `null` when the corresponding key is absent, so the provider default wins.
 */
final readonly class PanelOverride
{
    /**
     * Configuration keys accepted by a panel entry, in alphabetical order.
     */
    public const array KEYS = ['enabled', 'icon', 'position', 'title'];

    /**
     * @param string|null $title Display title replacing the provider default, or `null` to keep it.
     * @param string|null $icon Icon key replacing the provider default, or `null` to keep it.
     * @param bool|null $enabled `false` to remove the panel from the host, or `null` to keep it registered.
     * @param int|null $position Sort weight among extensions, or `null` to order the entry by title.
     */
    public function __construct(
        public string|null $title = null,
        public string|null $icon = null,
        public bool|null $enabled = null,
        public int|null $position = null,
    ) {}

    /**
     * Creates an override from one panel entry of the application configuration.
     *
     * Rejects unknown keys and wrong value types by key name. An empty array yields an override that changes nothing.
     *
     * @param array<array-key, mixed> $config Panel entry keyed by {@see PanelOverride::KEYS}.
     *
     * @throws InvalidArgumentException When a key is unknown, a value has the wrong type, or a value is invalid.
     *
     * @return self Override carrying the configured values.
     */
    public static function fromArray(array $config): self
    {
        foreach (array_keys($config) as $key) {
            if (in_array($key, self::KEYS, true) === false) {
                throw new InvalidArgumentException(
                    Message::PANEL_OPTION_UNKNOWN->getMessage($key, implode(', ', self::KEYS)),
                );
            }
        }

        return new self(
            self::titleValue($config['title'] ?? null),
            self::iconValue($config['icon'] ?? null),
            self::enabledValue($config['enabled'] ?? null),
            self::positionValue($config['position'] ?? null),
        );
    }

    /**
     * Validates the configured `enabled` flag.
     *
     * @param mixed $value Configured value, or `null` when the key is absent.
     *
     * @throws InvalidArgumentException When the value is not a `bool`.
     *
     * @return bool|null Validated flag, or `null` when the key is absent.
     */
    private static function enabledValue(mixed $value): bool|null
    {
        if ($value === null || is_bool($value)) {
            return $value;
        }

        throw new InvalidArgumentException(
            Message::PANEL_OPTION_TYPE_INVALID->getMessage('enabled', 'a boolean'),
        );
    }

    /**
     * Validates the configured icon key by shape, so no raw markup enters the configuration.
     *
     * @param mixed $value Configured value, or `null` when the key is absent.
     *
     * @throws InvalidArgumentException When the value is not a `string` or does not match the icon key shape.
     *
     * @return string|null Validated icon key, or `null` when the key is absent.
     */
    private static function iconValue(mixed $value): string|null
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) === false) {
            throw new InvalidArgumentException(
                Message::PANEL_OPTION_TYPE_INVALID->getMessage('icon', 'a string'),
            );
        }

        if (preg_match(Icon::KEY_PATTERN, $value) !== 1) {
            throw new InvalidArgumentException(
                Message::PANEL_ICON_INVALID->getMessage($value),
            );
        }

        return $value;
    }

    /**
     * Validates the configured sort weight.
     *
     * @param mixed $value Configured value, or `null` when the key is absent.
     *
     * @throws InvalidArgumentException When the value is not an `int`.
     *
     * @return int|null Validated sort weight, or `null` when the key is absent.
     */
    private static function positionValue(mixed $value): int|null
    {
        if ($value === null || is_int($value)) {
            return $value;
        }

        throw new InvalidArgumentException(
            Message::PANEL_OPTION_TYPE_INVALID->getMessage('position', 'an integer'),
        );
    }

    /**
     * Validates the configured display title.
     *
     * @param mixed $value Configured value, or `null` when the key is absent.
     *
     * @throws InvalidArgumentException When the value is not a `string` or is empty.
     *
     * @return string|null Validated title, or `null` when the key is absent.
     */
    private static function titleValue(mixed $value): string|null
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) === false) {
            throw new InvalidArgumentException(
                Message::PANEL_OPTION_TYPE_INVALID->getMessage('title', 'a string'),
            );
        }

        if ($value === '') {
            throw new InvalidArgumentException(
                Message::PANEL_TITLE_EMPTY->getMessage(),
            );
        }

        return $value;
    }
}
