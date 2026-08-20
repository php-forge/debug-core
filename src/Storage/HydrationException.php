<?php

declare(strict_types=1);

namespace PHPForge\Debug\Storage;

use RuntimeException;

/**
 * Reports a strict JSON schema violation with its payload path.
 */
final class HydrationException extends RuntimeException
{
    /**
     * Creates an exception that identifies an invalid payload path and its expected value.
     *
     * @param string $path Path of the invalid payload value.
     * @param string $expected Description of the expected value.
     *
     * @return self Hydration exception containing the schema violation.
     */
    public static function at(string $path, string $expected): self
    {
        return new self(
            "Invalid debug snapshot value at '{$path}': expected {$expected}.",
        );
    }
}
