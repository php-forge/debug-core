<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel;

use PHPForge\Debug\Routing\DebugUrlGeneratorInterface;

/**
 * Supplies request, theme, and adapter-owned URL state to a framework-neutral panel renderer.
 */
final readonly class PanelRenderContext
{
    /**
     * @param string $tag Captured request tag.
     * @param string $panel Stable identifier of the panel being rendered.
     * @param array<array-key, mixed> $queryParams Parsed query parameters of the current debugger request.
     * @param string $theme Resolved debugger theme.
     * @param DebugUrlGeneratorInterface $urls Adapter-owned URL generator.
     */
    public function __construct(
        public string $tag,
        public string $panel,
        public array $queryParams,
        public string $theme,
        private DebugUrlGeneratorInterface $urls,
    ) {}

    /**
     * Builds an adapter action URL for this captured request.
     *
     * @param string $action Adapter-defined action identifier.
     * @param array<array-key, mixed>|null $queryParams Additional parameters, or `null` to reuse the current query.
     */
    public function actionUrl(string $action, array|null $queryParams = null): string
    {
        return $this->urls->action($action, $this->tag, $queryParams ?? $this->queryParams);
    }

    /**
     * Builds the request-history URL.
     *
     * @param array<array-key, mixed>|null $queryParams History parameters, or `null` to reuse the current query.
     */
    public function historyUrl(array|null $queryParams = null): string
    {
        return $this->urls->history($queryParams ?? $this->queryParams);
    }

    /**
     * Builds a panel URL for this captured request.
     *
     * @param string|null $panel Target panel, or `null` to keep the current panel.
     * @param array<array-key, mixed>|null $queryParams Panel parameters, or `null` to reuse the current query.
     */
    public function panelUrl(string|null $panel = null, array|null $queryParams = null): string
    {
        return $this->urls->panel(
            $this->tag,
            $panel ?? $this->panel,
            $queryParams ?? $this->queryParams,
        );
    }
}
