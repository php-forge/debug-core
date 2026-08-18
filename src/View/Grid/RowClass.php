<?php

declare(strict_types=1);

namespace PHPForge\Debug\View\Grid;

use function in_array;

/**
 * Maps status keywords onto the `yii-debug-row-<variant>` row classes used by the debug grids.
 */
final class RowClass
{
    /**
     * Returns the row-attributes array carrying the `yii-debug-row-<variant>` CSS class for the given status level.
     *
     * Accepts `success`, `info`, `warning`, `danger`, and `error` (aliased to `danger`). Unknown or empty levels yield
     * an empty array, so the caller can splat the result safely.
     *
     * Usage example:
     * ```php
     * $attributes = \PHPForge\Debug\View\Grid\RowClass::for('danger');
     * ```
     *
     * @param string|null $level Status keyword, or `null` to skip the class.
     *
     * @return array<string, mixed> Row-attributes array with the `class` key set, or `[]` for unknown/`null` levels.
     */
    public static function for(string|null $level): array
    {
        $normalized = $level === 'error' ? 'danger' : $level;

        if (!in_array($normalized, ['success', 'info', 'warning', 'danger'], true)) {
            return [];
        }

        return ['class' => 'yii-debug-row-' . $normalized];
    }
}
