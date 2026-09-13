<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Tests\Panel\Request\RequestRendererTest;

/**
 * Provides the session and flash combinations {@see RequestRendererTest} renders.
 */
final class RequestRendererProvider
{
    /**
     * @return iterable<string, array{array{SESSION: array<string, mixed>, flashes: array<string, mixed>}}>
     */
    public static function sessionCaptures(): iterable
    {
        yield 'both populated' => [['SESSION' => ['user' => 1], 'flashes' => ['notice' => 'Saved']]];
        yield 'flashes only' => [['SESSION' => [], 'flashes' => ['notice' => 'Saved']]];
        yield 'neither populated' => [['SESSION' => [], 'flashes' => []]];
        yield 'session only' => [['SESSION' => ['user' => 1], 'flashes' => []]];
    }
}
