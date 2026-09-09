<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use InvalidArgumentException;
use PHPForge\Debug\Exception\Message;
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\List\{Li, Ul};
use UIAwesome\Html\Palpable\A;

use function array_key_exists;

/**
 * Renders the shared accessible tab pattern used by debug panels.
 */
final class Tabs
{
    /**
     * Renders the tab list and its panels as one markup fragment.
     *
     * @param non-empty-string $id Base ID every tab and panel ID derives from.
     * @param non-empty-string $ariaLabel Accessible name announced for the tab list.
     * @param list<array{label: string, content: string}> $tabs Tabs in display order.
     * @param int $activeIndex Zero-based index of the tab selected on initial render.
     *
     * @return string Rendered tab list followed by its panels.
     */
    public static function render(string $id, string $ariaLabel, array $tabs, int $activeIndex = 0): string
    {
        if ($tabs !== [] && !array_key_exists($activeIndex, $tabs)) {
            throw new InvalidArgumentException(
                Message::ACTIVE_TAB_INDEX_INVALID->getMessage(),
            );
        }

        $items = [];
        $panels = [];

        foreach ($tabs as $index => $tab) {
            $active = $index === $activeIndex;

            $tabId = "{$id}-tab-{$index}";
            $panelId = "{$id}-panel-{$index}";

            $items[] = Li::tag()
                ->class('yii-debug-tab')
                ->addAttribute('role', 'presentation')
                ->html(
                    A::tag()
                        ->id($tabId)
                        ->addAriaAttribute('controls', $panelId)
                        ->addAriaAttribute('selected', $active ? 'true' : 'false')
                        ->addAttribute('data-yii-debug-toggle', 'tab')
                        ->addAttribute('role', 'tab')
                        ->addAttribute('tabindex', $active ? '0' : '-1')
                        ->class($active ? 'yii-debug-tab-link is-active' : 'yii-debug-tab-link')
                        ->content($tab['label'])
                        ->href("#{$panelId}"),
                );

            $panel = Div::tag()
                ->id($panelId)
                ->addAriaAttribute('labelledby', $tabId)
                ->addAttribute('role', 'tabpanel')
                ->class($active ? 'yii-debug-tab-panel is-active' : 'yii-debug-tab-panel')
                ->html($tab['content']);

            if ($active === false) {
                $panel = $panel->addAttribute('hidden', true);
            }

            $panels[] = $panel;
        }

        $tabList = Ul::tag()
            ->class('yii-debug-tabs')
            ->addAriaAttribute('label', $ariaLabel)
            ->addAttribute('role', 'tablist')
            ->html(...$items)
            ->render();
        $content = Div::tag()
            ->class('yii-debug-tab-content')
            ->html(...$panels)
            ->render();

        return "{$tabList}{$content}";
    }
}
