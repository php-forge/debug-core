<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request;

/**
 * Typed view-model for one tab in the Request panel detail view.
 */
final readonly class RequestTab
{
    public function __construct(
        /**
         * Navigation label shown in the tab strip (`'Parameters'`, `'Headers'`, `'Session'`, `'Server'`).
         */
        public string $label,
        /**
         * @var list<RequestSection> Sections rendered when this tab is active, in display order.
         */
        public array $sections,
    ) {}
}
