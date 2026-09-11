<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel;

use InvalidArgumentException;
use PHPForge\Debug\Panel;

/**
 * Resolves explicitly registered, optional panel providers without a package catalog.
 */
final class PanelFactory
{
    /**
     * @param string $class Application-configured provider, never a class name from a stored capture.
     */
    public static function create(string $class): Panel
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException(
                "Debug panel provider is not installed: {$class}.",
            );
        }

        $provider = new $class();

        if (!$provider instanceof Panel) {
            throw new InvalidArgumentException(
                'Debug panel provider must extend ' . Panel::class . '.',
            );
        }

        return $provider;
    }
}
