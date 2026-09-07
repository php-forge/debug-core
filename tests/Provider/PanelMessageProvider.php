<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Panel\PanelMessage;
use PHPForge\Debug\Tests\Panel\PanelMessageTest;

/**
 * Provides the complete presentation-text catalog for {@see PanelMessageTest}.
 */
final class PanelMessageProvider
{
    /**
     * @return iterable<string, array{PanelMessage, string}>
     */
    public static function messages(): iterable
    {
        yield 'context' => [
            PanelMessage::CONTEXT,
            'Context',
        ];
        yield 'group_filters' => [
            PanelMessage::GROUP_FILTERS,
            'Group filters',
        ];
        yield 'source_trace' => [
            PanelMessage::SOURCE_TRACE,
            'Source trace',
        ];
    }
}
