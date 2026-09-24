<?php

declare(strict_types=1);

namespace PHPForge\Debug\Registration;

/**
 * Represents one collector or panel entry of the application configuration, split into its class and its options.
 *
 * The options keep `enabled` next to every other key, so each host validates them with its own rules: a collector
 * reads the flag through {@see EntryParser::enabled()}, and a panel hands the whole map to
 * {@see PanelOverride::fromArray()}.
 */
final readonly class ParsedEntry
{
    /**
     * @param string $class Class name or container identifier the host resolves.
     * @param array<array-key, mixed> $options Keys the entry declares beside `class`, in declaration order.
     */
    public function __construct(public string $class, public array $options = []) {}
}
