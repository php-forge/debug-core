<?php

declare(strict_types=1);

namespace PHPForge\Debug\View\Sidebar;

use PHPForge\Debug\Helper\{Icon, Vocabulary};
use PHPForge\Debug\View\ViewMessage;
use UIAwesome\Html\Core\Component\{Item, Menu};
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Interop\Inline;
use UIAwesome\Html\Phrasing\Span;
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Sectioning\{Aside, Section};

/**
 * Renders the debugger sidebar partial.
 */
final class SidebarRenderer
{
    private const string ICON_BTN_CLASS = 'yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon';

    /**
     * Renders the full sidebar (`<aside>` with the snapshot card + panel nav).
     */
    public static function render(SidebarView $view): string
    {
        $children = [];

        if ($view->snapshot !== null) {
            $children[] = self::renderSnapshotSection($view->snapshot);
        }

        $children[] = self::renderPanelNav($view->navItems, 'Debug panels');

        foreach ($view->navGroups as $label => $items) {
            if ($items === []) {
                continue;
            }

            $children[] = self::renderNavGroup($label, $items);
        }

        return Aside::tag()
            ->class('yii-debug-sidebar')
            ->html(...$children)
            ->render();
    }

    /**
     * Renders the snapshot card body (method/url line + meta strip + navigator row).
     */
    private static function renderHistoryCard(SidebarSnapshot $snapshot): Div
    {
        $method = $snapshot->method !== '' ? "{$snapshot->method} " : '';

        return Div::tag()
            ->class('yii-debug-history-card')
            ->title("{$method}{$snapshot->fullUrl}")
            ->html(
                Div::tag()
                    ->class('yii-debug-snapshot-line')
                    ->html(
                        Span::tag()
                            ->class('yii-debug-snapshot-method yii-debug-verb-' . Vocabulary::verb($snapshot->method))
                            ->addDataAttribute('snapshot-field', 'method')
                            ->content($snapshot->method),
                        Span::tag()
                            ->class('yii-debug-snapshot-url')
                            ->addDataAttribute('snapshot-field', 'url')
                            ->title($snapshot->fullUrl)
                            ->content($snapshot->path),
                    ),
                self::renderMetaStrip($snapshot),
                self::renderNavRow($snapshot),
            );
    }

    /**
     * Renders the snapshot card meta strip (status pill + time chip + AJAX tag).
     */
    private static function renderMetaStrip(SidebarSnapshot $snapshot): Div
    {
        $time = Span::tag()
            ->class('yii-debug-snapshot-time')
            ->addDataAttribute('snapshot-field', 'time')
            ->content($snapshot->time);

        if ($snapshot->time === '') {
            $time = $time->addAttribute('hidden', true);
        }

        $ajax = Span::tag()
            ->class('yii-debug-snapshot-tag')
            ->addDataAttribute('snapshot-field', 'ajax')
            ->content(ViewMessage::AJAX);

        if ($snapshot->isAjax === false) {
            $ajax = $ajax->addAttribute('hidden', true);
        }

        return Div::tag()
            ->class('yii-debug-snapshot-meta')
            ->html(
                Span::tag()
                    ->class('yii-debug-snapshot-status yii-debug-status-' . $snapshot->statusVariant)
                    ->addDataAttribute('snapshot-field', 'status')
                    ->content($snapshot->statusCode > 0 ? (string) $snapshot->statusCode : '–'),
                $time,
                $ajax,
            );
    }

    /**
     * Renders one labeled navigation group after the primary panel menu.
     *
     * @param string $label Group heading announced as the section label.
     * @param list<SidebarNavItem> $items Navigation entries belonging to the group.
     *
     * @return Section Rendered group section.
     */
    private static function renderNavGroup(string $label, array $items): Section
    {
        return Section::tag()
            ->addAriaAttribute('label', $label)
            ->class('yii-debug-side-section yii-debug-nav-group')
            ->html(
                Header::tag()
                    ->class('yii-debug-side-section-title')
                    ->content($label),
                self::renderPanelNav($items, "{$label} debug panels"),
            );
    }

    /**
     * Renders the navigator row (Newest | Newer | Older | Oldest) as a {@see Menu} of {@see Item} entries.
     *
     * The row is a flat grid of controls, so the list wrappers are switched off and each entry renders its control
     * directly. An entry links to its target capture, except in cursor mode and when the target does not exist, where
     * it renders as a button the history script drives.
     *
     * @param SidebarSnapshot $snapshot Capture the navigator moves away from.
     *
     * @return string Rendered navigator row.
     */
    private static function renderNavRow(SidebarSnapshot $snapshot): string
    {
        $entries = [
            [
                'newest', $snapshot->isNewest, $snapshot->newestUrl, ViewMessage::NEWEST_REQUEST->value,
                ViewMessage::NEWEST_CAPTURED_REQUEST->value, 'chevrons-up',
            ],
            [
                'newer', $snapshot->hasNewer === false, $snapshot->newerUrl, 'Newer request',
                'Newer captured request', 'chevron-up',
            ],
            [
                'older', $snapshot->hasOlder === false, $snapshot->olderUrl, 'Older request',
                'Older captured request', 'chevron-down',
            ],
            [
                'oldest', $snapshot->isOldest, $snapshot->oldestUrl, 'Oldest request',
                'Oldest captured request', 'chevrons-down',
            ],
        ];

        $items = [];

        foreach ($entries as [$target, $isDisabled, $url, $title, $ariaLabel, $icon]) {
            $asButton = $snapshot->isCursor || $isDisabled;

            $attributes = $asButton ? ['type' => 'button', 'title' => $title] : ['title' => $title];

            if ($asButton && $isDisabled) {
                $attributes['disabled'] = true;
            }

            $attributes['aria-label'] = $ariaLabel;

            if ($asButton && $snapshot->isCursor) {
                $attributes['data-yii-debug-cursor'] = $target;
            }

            $items[] = Item::tag()
                ->label(Icon::render($icon), false)
                ->link($asButton ? '' : $url)
                ->linkAttributes($attributes)
                ->linkClass($isDisabled ? self::ICON_BTN_CLASS . ' is-disabled' : self::ICON_BTN_CLASS)
                ->linkTag($asButton ? 'button' : Inline::A);
        }

        return Menu::tag()
            ->class('yii-debug-request-nav-row')
            ->addAttribute('role', 'group')
            ->listItemTag(false)
            ->listType(false)
            ->items(...$items)
            ->render();
    }

    /**
     * Renders the bottom panel-nav (History + every non-config panel) as a {@see Menu} of {@see Item} entries.
     *
     * Each entry wraps its label in a `<span>` and, when present, its icon SVG in a sibling `<span>`. The active entry
     * receives the `is-active` class and the `aria-current="page"` attribute; inactive entries are styled through
     * `.yii-debug-nav-link:not(.is-active)`.
     *
     * @param list<SidebarNavItem> $items Navigation entries to render.
     *
     * @return string Rendered `<nav>` markup for the panel navigation.
     */
    private static function renderPanelNav(array $items, string $ariaLabel): string
    {
        $menuItems = [];

        foreach ($items as $item) {
            $menuItem = Item::tag()
                ->label($item->label)
                ->labelTag(Inline::SPAN)
                ->labelClass('yii-debug-nav-link-label')
                ->link($item->url)
                ->active($item->isActive)
                ->linkAttributes(['title' => $item->tooltip]);

            if ($item->iconSvg !== '') {
                $menuItem = $menuItem
                    ->iconTag('span')
                    ->iconClass('yii-debug-nav-link-icon')
                    ->iconAttributes(['aria-hidden' => 'true'])
                    ->iconContent($item->iconSvg);
            }

            $menuItems[] = $menuItem;
        }

        return Menu::tag()
            ->type('nav')
            ->class('yii-debug-nav yii-debug-nav-iconed')
            ->addAriaAttribute('label', $ariaLabel)
            ->linkClass('yii-debug-nav-link')
            ->linkActiveClass(['yii-debug-nav-link', 'is-active'])
            ->linkAriaCurrent()
            ->items(...$menuItems)
            ->render();
    }

    /**
     * Renders the top snapshot section (`<section>` with header + history card).
     */
    private static function renderSnapshotSection(SidebarSnapshot $snapshot): Section
    {
        $section = Section::tag()
            ->class('yii-debug-side-section yii-debug-request-nav')
            ->addAriaAttribute('label', $snapshot->ariaLabel);

        if ($snapshot->isCursor) {
            $section = $section->addDataAttribute('yii-debug-history-cursor', true);

            if ($snapshot->cursorInitTag !== '') {
                $section = $section->addDataAttribute('yii-debug-cursor-init', $snapshot->cursorInitTag);
            }
        }

        return $section->html(
            Header::tag()
                ->class('yii-debug-side-section-title')
                ->content($snapshot->title),
            self::renderHistoryCard($snapshot),
        );
    }
}
