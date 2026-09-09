<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Config;

/**
 * Typed view-model for the application section of the Configuration panel.
 */
final readonly class ApplicationConfig
{
    public function __construct(
        /**
         * Framework version reported at request capture time.
         */
        public string $yii,
        /**
         * Configured application name, or an empty string when the application is unavailable.
         */
        public string $name,
        /**
         * Configured application version, or an empty string when none is set.
         */
        public string $version,
        /**
         * Configured application language as a BCP-47 tag, or an empty string.
         */
        public string $language,
        /**
         * Configured source language as a BCP-47 tag, or an empty string.
         */
        public string $sourceLanguage,
        /**
         * Configured application charset, or an empty string.
         */
        public string $charset,
        /**
         * Active environment label reported by the adapter.
         */
        public string $env,
        /**
         * Whether the application ran with debug mode enabled.
         */
        public bool $debug,
    ) {}
}
