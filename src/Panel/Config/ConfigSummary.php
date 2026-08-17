<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Config;

use function count;

/**
 * Typed aggregate view-model for the Configuration panel detail view.
 */
final readonly class ConfigSummary
{
    public function __construct(
        /**
         * Typed application configuration block, mirroring the `application` slice of the raw payload after scalar
         * narrowing.
         */
        public ApplicationConfig $application,
        /**
         * Typed PHP runtime configuration block, mirroring the `php` slice of the raw payload after scalar narrowing.
         */
        public PhpConfig $php,
        /**
         * @var array<string, string> Sorted map of installed extension `name => version`, taken verbatim from
         * the Configuration panel extension roster.
         */
        public array $extensions,
    ) {}

    /**
     * Returns the number of installed extensions in {@see $extensions}.
     */
    public function extensionCount(): int
    {
        return count($this->extensions);
    }

    /**
     * Returns whether at least one installed extension is recorded.
     */
    public function hasExtensions(): bool
    {
        return $this->extensions !== [];
    }
}
