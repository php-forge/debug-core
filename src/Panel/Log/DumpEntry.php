<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Log;

/**
 * Represents one key and its value inside an array, object, or enum block of a dump tree.
 *
 * Only the property keys of a `print_r()` object or enum dump, `stdClass` excluded, carry a visibility.
 */
final readonly class DumpEntry
{
    /**
     * @param string $key Array key or property name.
     * @param string $visibility `protected`, `private`, or `''` for a public property or an array key.
     * @param string $declaringClass Class declaring a private property; `''` otherwise.
     */
    public function __construct(
        public string $key,
        public DumpNode $value,
        public string $visibility = '',
        public string $declaringClass = '',
    ) {}
}
