<?php

declare(strict_types=1);

namespace PHPForge\Debug\Instrumentation;

use Closure;
use Throwable;

/**
 * Executes diagnostic observers without allowing their failures to alter application behavior.
 */
final readonly class InstrumentationGuard
{
    /**
     * @var (Closure(Throwable): void)|null
     */
    private Closure|null $failureHandler;

    /**
     * @param (callable(Throwable): void)|null $failureHandler Optional observer for suppressed instrumentation errors.
     */
    public function __construct(callable|null $failureHandler = null)
    {
        $this->failureHandler = $failureHandler === null ? null : Closure::fromCallable($failureHandler);
    }

    /**
     * Runs one diagnostic observer and reports, but never propagates, its failure.
     *
     * @param callable(): void $observer Diagnostic side effect.
     */
    public function observe(callable $observer): void
    {
        try {
            $observer();
        } catch (Throwable $failure) {
            if ($this->failureHandler === null) {
                return;
            }

            try {
                ($this->failureHandler)($failure);
            } catch (Throwable) {
                // A diagnostic failure handler is itself instrumentation and must remain fail-open.
            }
        }
    }
}
