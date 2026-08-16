<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Table\{Table, Tbody, Td, Thead, Tr};

/**
 * @var list<array{
 *     capturedAt: string,
 *     duration: string,
 *     memory: string,
 *     method: string,
 *     methodVariant: string,
 *     requestUrl: string,
 *     statusCode: int,
 *     statusVariant: string,
 *     tag: string,
 *     viewUrl: string,
 * }> $rows Normalized request history rows.
 */

$heading = H1::tag()->class('yii-debug-sr-only')->content('Request history');
$summary = Header::tag()
    ->class('yii-debug-grid-summary')
    ->html(
        Span::tag()->html(
            Strong::tag()->content((string) count($rows)),
            ' Requests',
        ),
    );

if ($rows === []) {
    $history = Div::tag()->class('yii-debug-empty-state')->content('No debug snapshots are available.');
} else {
    $body = Tbody::tag();

    foreach ($rows as $row) {
        $body = $body->tr(
            Tr::tag()
                ->addDataAttribute('yii-debug-tag', $row['tag'])
                ->html(
                    Td::tag()->html(
                        A::tag()
                            ->class('yii-debug-tag-link')
                            ->content($row['tag'])
                            ->href($row['viewUrl']),
                    ),
                    Td::tag()->html(
                        Span::tag()
                            ->class('yii-debug-method yii-debug-verb-' . $row['methodVariant'])
                            ->content($row['method']),
                    ),
                    Td::tag()->html(
                        Span::tag()
                            ->class('yii-debug-badge yii-debug-status-' . $row['statusVariant'])
                            ->content((string) $row['statusCode']),
                    ),
                    Td::tag()->content($row['duration']),
                    Td::tag()->content($row['memory']),
                    Td::tag()->class('yii-debug-cell-nowrap')->content($row['capturedAt']),
                    Td::tag()->html(
                        A::tag()
                            ->class('yii-debug-url-cell')
                            ->content($row['requestUrl'])
                            ->href($row['viewUrl'])
                            ->title($row['requestUrl']),
                    ),
                ),
        );
    }

    $history = Div::tag()
        ->class('yii-debug-table-wrap yii-debug-grid-history')
        ->html(
            Table::tag()
                ->class('yii-debug-table')
                ->thead(
                    Thead::tag()->tr(
                        Tr::tag()->headerCells('ID', 'Method', 'Status', 'Duration', 'Memory', 'Captured', 'URL'),
                    ),
                )
                ->tbody($body),
        );
}
?>
<?= $heading->render() ?>
<?= $summary->render() ?>
<?= $history->render();
