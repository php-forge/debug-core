<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request;

use PHPForge\Debug\Helper\Disclosure;
use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Form\InputSearch;
use UIAwesome\Html\Heading\H2;
use UIAwesome\Html\Interactive\{Details, Summary};
use UIAwesome\Html\List\{Dd, Dl, Dt};
use UIAwesome\Html\Phrasing\Span;
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Sectioning\Section;

use function count;
use function in_array;
use function is_string;
use function parse_url;
use function str_replace;
use function strtoupper;

/**
 * Shows additional server diagnostics while retaining the complete captured variables in a separate raw disclosure.
 */
final class RequestServerRenderer
{
    /**
     * Renders every variable when no Request context is available to establish duplication.
     *
     * @param array<int|string, mixed> $entries
     */
    public static function render(array $entries): string
    {
        return self::renderView($entries, $entries);
    }

    /**
     * Moves exact Request and inbound-header duplicates out of the primary view, not out of the captured data.
     *
     * @param array<int|string, mixed> $entries
     */
    public static function renderForRequest(array $entries, RequestView $view): string
    {
        return self::renderView($entries, self::additionalEntries($entries, $view));
    }

    /**
     * @param array<int|string, mixed> $entries
     *
     * @return array<int|string, mixed>
     */
    private static function additionalEntries(array $entries, RequestView $view): array
    {
        $url = parse_url($view->hero->getUrl());

        $query = $url === false ? '' : ($url['query'] ?? '');
        $target = $url === false ? '' : ($url['path'] ?? '');

        if ($url !== false && isset($url['query'])) {
            $target .= '?' . $query;
        }

        $shown = [
            'REQUEST_METHOD' => [$view->hero->getMethod()],
            'REQUEST_URI' => [$target],
            'REMOTE_ADDR' => [$view->hero->getIp()],
            'QUERY_STRING' => [$query],
        ];

        foreach ($view->tabs as $tab) {
            if ($tab->id !== 'headers') {
                continue;
            }

            foreach ($tab->sections as $section) {
                if ($section->id !== 'request-headers') {
                    continue;
                }

                foreach ($section->entries as $name => $value) {
                    if (!is_string($name)) {
                        continue;
                    }

                    $key = strtoupper(str_replace('-', '_', $name));
                    $key = in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)
                        ? $key
                        : "HTTP_{$key}";
                    $shown[$key][] = $value;
                }
            }
        }

        foreach ($entries as $key => $value) {
            if (!is_string($key) || !is_string($value) || $value === '') {
                continue;
            }

            $candidates = $shown[strtoupper($key)] ?? [];

            $candidate = $candidates[0] ?? null;

            // Repeated or ambiguous headers and transformed request values remain primary diagnostics.
            if (count($candidates) === 1 && ($candidate === $value || $candidate === [$value])) {
                unset($entries[$key]);
            }
        }

        return $entries;
    }

    private static function renderGroup(ServerVariableGroup $group): string
    {
        $description = $group->id === 'raw'
            ? P::tag()
                ->class('yii-debug-diagnostic-empty-state')
                ->content('Original captured variables, including values already shown in Request and Headers.')
                ->render()
            : '';

        return Details::tag()
            ->addAriaAttribute('label', $group->label)
            ->class('yii-debug-server-group yii-debug-server-group-disclosure')
            ->html(
                Summary::tag()
                    ->class('yii-debug-server-group-summary')
                    ->html(
                        Span::tag()
                            ->class('yii-debug-server-group-identity')
                            ->html(
                                Span::tag()->class('yii-debug-server-group-title')->content($group->label),
                                Span::tag()->class('yii-debug-server-group-count')->content((string) count($group->entries)),
                            ),
                        Disclosure::hint(),
                    ),
                Div::tag()
                    ->addDataAttribute('yii-debug-filter-scope', true)
                    ->class('yii-debug-server-group-body')
                    ->html(
                        $description,
                        Div::tag()
                            ->class('yii-debug-mini-toolbar')
                            ->html(
                                InputSearch::tag()
                                    ->addAriaAttribute('label', 'Filter ' . $group->label)
                                    ->addDataAttribute('yii-debug-filter', true)
                                    ->class('yii-debug-filter-input')
                                    ->placeholder('Filter variables…'),
                            ),
                        Div::tag()
                            ->addDataAttribute('yii-debug-filter-target', true)
                            ->addDataAttribute('yii-debug-filter-unit', 'variables')
                            ->html(
                                self::renderLedger($group->entries),
                                P::tag()
                                    ->addDataAttribute('yii-debug-filter-empty', true)
                                    ->addAttribute('hidden', true)
                                    ->class('yii-debug-diagnostic-filter-empty')
                                    ->content('No server variables match this filter.'),
                            ),
                    ),
            )
            ->open(!$group->collapsed)
            ->render();
    }

    /**
     * @param array<int|string, mixed> $entries
     */
    private static function renderLedger(array $entries): string
    {
        $rows = [];

        foreach ($entries as $name => $value) {
            $rows[] = Div::tag()
                ->addDataAttribute('yii-debug-filter-row', true)
                ->class('yii-debug-diagnostic-row yii-debug-server-row')
                ->html(
                    Dt::tag()->html(RequestDiagnosticValueRenderer::escape((string) $name)),
                    Dd::tag()->html(RequestDiagnosticValueRenderer::value($value)),
                );
        }

        return Dl::tag()
            ->class('yii-debug-diagnostic-ledger yii-debug-server-ledger')
            ->html(...$rows)
            ->render();
    }

    /**
     * @param array<int|string, mixed> $entries
     * @param array<int|string, mixed> $additional
     */
    private static function renderView(array $entries, array $additional): string
    {
        $groups = '';

        foreach (ServerVariableGrouper::group($additional) as $group) {
            $groups .= self::renderGroup(new ServerVariableGroup(
                $group->id,
                $group->id === 'header-mirrors' ? 'Additional header variables' : $group->label,
                $group->entries,
            ));
        }

        if ($groups === '') {
            $groups = P::tag()
                ->class('yii-debug-diagnostic-empty-state')
                ->content($entries === [] ? 'No server variables captured.' : 'No additional server details.')
                ->render();
        }

        return Section::tag()
            ->addAriaAttribute('labelledby', 'yii-debug-server-environment-title')
            ->class('yii-debug-diagnostic-shell yii-debug-server-environment')
            ->html(
                Header::tag()
                    ->class('yii-debug-diagnostic-header')
                    ->html(
                        Div::tag()
                            ->class('yii-debug-diagnostic-heading-copy')
                            ->html(
                                H2::tag()->id('yii-debug-server-environment-title')->content('Server details'),
                                Span::tag()
                                    ->class('yii-debug-diagnostic-total')
                                    ->content(count($additional) . ' additional / ' . count($entries) . ' captured'),
                            ),
                    ),
                Div::tag()->class('yii-debug-server-additional')->html($groups),
                $entries === [] ? '' : self::renderGroup(new ServerVariableGroup(
                    'raw',
                    'Raw server variables',
                    $entries,
                    true,
                )),
            )
            ->render();
    }
}
