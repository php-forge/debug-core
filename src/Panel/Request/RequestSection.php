<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request;

/**
 * Typed view-model for one name/value section rendered in the Request panel detail view.
 */
final readonly class RequestSection
{
    public function __construct(
        /**
         * Section heading shown at the top of the rendered table.
         */
        public string $caption,
        /**
         * Name → value entries that populate the table rows.
         *
         * Keys are coerced to strings during rendering and values are dumped via {@see \PHPForge\Debug\Helper\Dump::asString()}
         * for a stable, syntax-aware presentation.
         *
         * @var array<int|string, mixed>
         */
        public array $entries,
        /**
         * `true` when the renderer should emit a search input next to the caption and wrap the table in a filter
         * target, so the developer can narrow long tables (Session, Server, Headers).
         */
        public bool $filterable = false,
        /**
         * Stable semantic identifier used when a composed Request view needs to move or omit this section.
         */
        public string $id = '',
    ) {}
}
