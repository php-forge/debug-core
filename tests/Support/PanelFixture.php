<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Support;

use PHPForge\Debug\{Panel, PanelView};

/**
 * Provides a minimal installed panel provider for factory resolution tests.
 */
final class PanelFixture extends Panel
{
    protected const string ICON = 'request';
    protected const string ID = 'fixture';
    protected const string TITLE = 'Fixture';

    /**
     * @param array<string, mixed> $data Captured data presented verbatim.
     *
     * @return PanelView Overview of the captured data.
     */
    public function present(array $data): PanelView
    {
        return PanelView::create()->overview($data);
    }
}
