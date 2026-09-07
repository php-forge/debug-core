<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Panel\Inertia\InertiaMessage;
use PHPForge\Debug\Tests\Panel\Inertia\InertiaMessageTest;

/**
 * Provides the complete presentation-text catalog for {@see InertiaMessageTest}.
 */
final class InertiaMessageProvider
{
    /**
     * @return iterable<string, array{InertiaMessage, string}>
     */
    public static function messages(): iterable
    {
        yield 'empty_coverage' => [
            InertiaMessage::EMPTY_COVERAGE,
            'Both full page loads and Inertia XHR visits populate this view; plain JSON endpoints, redirects, and '
            . 'asset requests do not.',
        ];
        yield 'empty_headline' => [
            InertiaMessage::EMPTY_HEADLINE,
            'No Inertia page in this request',
        ];
        yield 'version_conflict_headline' => [
            InertiaMessage::VERSION_CONFLICT_HEADLINE,
            'Version conflict interrupted this visit',
        ];
    }
}
