<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Config;

/**
 * Typed view-model for the PHP runtime section of the Configuration panel.
 *
 * Mirrors the `php` slice of the configuration snapshot payload after every value has been narrowed to its
 * declared scalar type.
 */
final readonly class PhpConfig
{
    public function __construct(
        /**
         * Active `PHP_VERSION` at request capture time.
         */
        public string $version,
        /**
         * `true` when the `xdebug` extension was loaded.
         */
        public bool $xdebug,
        /**
         * `true` when the `apcu` extension was loaded.
         */
        public bool $apcu,
        /**
         * `true` when the `memcache` extension was loaded.
         */
        public bool $memcache,
        /**
         * `true` when the `memcached` extension was loaded.
         */
        public bool $memcached,
    ) {}
}
