<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request;

/**
 * Top-level typed view-model for the Request panel detail view.
 */
final readonly class RequestView
{
    public function __construct(
        /**
         * Hero header view-model.
         */
        public RequestHero $hero,
        /**
         * @var list<RequestTab> Tab view-models in display order; the first tab is rendered active by the section
         * renderer.
         */
        public array $tabs,
    ) {}
}
