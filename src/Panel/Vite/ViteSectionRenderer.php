<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Vite;

use PHPForge\Debug\Helper\EmptyState;
use Stringable;
use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Heading\{H1, H2};
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Sectioning\Section;
use UIAwesome\Html\Table\{Table, Tbody, Td, Th, Thead, Tr};

use function implode;

/**
 * Renders the framework-neutral Vite integration summary, configuration, and production chunk inventory.
 */
final class ViteSectionRenderer
{
    /**
     * Renders the complete Vite detail content.
     */
    public static function render(ViteSummary $summary): string
    {
        $content = self::renderHeader($summary);

        if ($summary->isEmpty()) {
            return $content . EmptyState::card(
                'No Vite integrations captured',
                P::tag()->content('This request did not use an initialized Vite application component.'),
            );
        }

        foreach ($summary->components as $component) {
            $content .= self::renderComponent($component);
        }

        return $content;
    }

    private static function flagLabel(bool|null $value): string
    {
        return match ($value) {
            true => 'Enabled',
            false => 'Disabled',
            null => 'Unknown',
        };
    }

    private static function modeLabel(string $mode): string
    {
        return match ($mode) {
            ViteComponent::MODE_DEVELOPMENT => 'Development',
            ViteComponent::MODE_PRODUCTION => 'Production',
            default => 'Unknown',
        };
    }

    /**
     * Renders the production chunk table or the mode-specific explanation for an empty inventory.
     */
    private static function renderChunks(ViteComponent $component): Stringable
    {
        $chunks = $component->chunks();

        if ($chunks === []) {
            return P::tag()->content(
                match ($component->mode) {
                    ViteComponent::MODE_DEVELOPMENT
                        => 'Development mode resolves entry points through the dev server.',
                    ViteComponent::MODE_PRODUCTION
                        => 'The Vite manifest is missing or empty — run the front-end build to populate it.',
                    default => 'No build chunks were available for inspection.',
                },
            );
        }

        $rows = [];

        foreach ($chunks as $index => $chunk) {
            $entry = $chunk->isEntry
                ? Span::tag()->class('yii-debug-badge yii-debug-badge-success')->content('entry')
                : Span::tag()->content('—');

            $rows[] = Tr::tag()->html(
                Td::tag()->content((string) ($index + 1)),
                Td::tag()
                    ->class('yii-debug-cell-mono')
                    ->html(Strong::tag()->content($chunk->name)),
                Td::tag()
                    ->class('yii-debug-cell-mono')
                    ->content($chunk->file !== '' ? $chunk->file : '—'),
                Td::tag()
                    ->class('yii-debug-cell-numeric')
                    ->content((string) $chunk->cssCount),
                Td::tag()
                    ->class('yii-debug-cell-numeric')
                    ->content((string) $chunk->imports),
                Td::tag()
                    ->class('yii-debug-cell-pill')
                    ->html($entry),
            );
        }

        return Div::tag()
            ->class('yii-debug-table-wrap')
            ->html(
                Table::tag()
                    ->class('yii-debug-table')
                    ->html(
                        Thead::tag()->html(
                            Tr::tag()->html(
                                Th::tag()->scope('col')->content('#'),
                                Th::tag()->scope('col')->content('Chunk'),
                                Th::tag()->scope('col')->content('Output'),
                                Th::tag()->scope('col')->content('CSS'),
                                Th::tag()->scope('col')->content('Imports'),
                                Th::tag()->scope('col')->content('Entry'),
                            ),
                        ),
                        Tbody::tag()->html(...$rows),
                    ),
            );
    }

    /**
     * Renders one captured Vite integration without repeating the panel name as a visible heading.
     */
    private static function renderComponent(ViteComponent $component): string
    {
        $inspectionBadge = Span::tag()
            ->class(
                $component->inspectionAvailable
                    ? 'yii-debug-badge yii-debug-badge-success'
                    : 'yii-debug-badge yii-debug-badge-warning',
            )
            ->content($component->inspectionAvailable ? 'Available' : 'Unavailable');

        $viteClient = $component->mode === ViteComponent::MODE_PRODUCTION
            ? 'Not applicable'
            : self::flagLabel($component->includeViteClient);
        $modulePreload = $component->mode === ViteComponent::MODE_DEVELOPMENT
            ? 'Not applicable'
            : self::flagLabel($component->modulePreload);
        $rows = [
            self::renderOverviewRow('Component ID', $component->id),
            self::renderOverviewRow('Class', $component->class),
            self::renderOverviewRow('Implementation', $component->implementation),
            self::renderOverviewRow('Mode', self::modeLabel($component->mode)),
            self::renderOverviewRow('Inspection', $inspectionBadge),
            self::renderOverviewRow(
                'Entry points',
                $component->entrypoints === [] ? '—' : implode(', ', $component->entrypoints),
            ),
            self::renderOverviewRow('Base URL', $component->baseUrl !== '' ? $component->baseUrl : '—'),
            self::renderOverviewRow('Dev server', $component->devServerUrl ?? '—'),
            self::renderOverviewRow('Manifest', $component->manifestPath !== '' ? $component->manifestPath : '—'),
            self::renderOverviewRow('Vite client', $viteClient),
            self::renderOverviewRow('Module preload', $modulePreload),
        ];
        $content = Div::tag()
            ->class('yii-debug-table-wrap')
            ->html(
                Table::tag()
                    ->class('yii-debug-table yii-debug-table-mono yii-debug-table-vite-overview')
                    ->html(Tbody::tag()->html(...$rows)),
            )
            ->render();

        if ($component->inspectionAvailable === false) {
            $content .= P::tag()
                ->class('yii-debug-callout yii-debug-callout-warning')
                ->role('status')
                ->html(
                    'Runtime inspection is unavailable for this component. Its public configuration could not be read ',
                    'without changing application state.',
                )
                ->render();
        }

        return Section::tag()
            ->addAriaAttribute('label', "Vite component {$component->id}")
            ->class('yii-debug-vite-component')
            ->html(
                $content,
                Div::tag()
                    ->class('yii-debug-section-header')
                    ->html(H2::tag()->content('Build chunks')),
                self::renderChunks($component),
            )
            ->render();
    }

    /**
     * Renders the accessible panel heading and aggregate component/mode summary.
     */
    private static function renderHeader(ViteSummary $summary): string
    {
        $count = $summary->count();
        $items = [
            Span::tag()
                ->html(
                    Strong::tag()->content((string) $count),
                    $count === 1 ? ' component' : ' components',
                ),
        ];

        if ($summary->isEmpty() === false) {
            $items[] = Span::tag()
                ->class('yii-debug-grid-summary-sep')
                ->content('·');
            $items[] = Span::tag()->content($summary->modeLabel());
        }

        return H1::tag()
            ->class('yii-debug-sr-only')
            ->content('Vite')
            ->render()
            . Header::tag()
                ->class('yii-debug-grid-summary')
                ->html(...$items)
                ->render();
    }

    /**
     * Renders one key/value row in the shared panel overview table.
     */
    private static function renderOverviewRow(string $term, Stringable|string $value): Tr
    {
        $description = Td::tag();
        $description = $value instanceof Stringable
            ? $description->html($value)
            : $description->content($value);

        return Tr::tag()
            ->html(
                Th::tag()->scope('row')->content($term),
                $description,
            );
    }
}
