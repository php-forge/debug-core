<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Log;

/**
 * Represents one `[key] => value` line of a parsed `print_r()` block.
 *
 * Inside object and enum blocks the key is split into the property name, its visibility, and the class that declares
 * a private property; array keys and the dynamic property names of a `stdClass` object stay verbatim.
 */
final readonly class PrintREntry
{
    /**
     * @param string $key Array key or property name.
     * @param PrintRNode $value Value of the entry.
     * @param string $visibility `protected`, `private`, or `''` for a public property or an array key.
     * @param string $declaringClass Class declaring a private property; `''` otherwise.
     */
    public function __construct(
        public string $key,
        public PrintRNode $value,
        public string $visibility = '',
        public string $declaringClass = '',
    ) {}
}
