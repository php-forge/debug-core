<?php

declare(strict_types=1);

use UIAwesome\Html\Helper\Attributes;

/**
 * @var string $dataUrl Adapter-generated toolbar data URL.
 * @var int $height Initial drawer height percentage.
 * @var string $position Initial toolbar position.
 * @var list<string> $skipUrls Same-origin URLs excluded from AJAX tracking.
 */
$skipUrlsJson = $skipUrls === []
    ? null
    : json_encode($skipUrls, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
$attributes = Attributes::render(
    [
        'id' => 'yii-debug-toolbar',
        'data-url' => $dataUrl,
        'data-skip-urls' => $skipUrlsJson,
        'data-position' => $position,
        'data-height' => $height,
    ],
);
?>
<yii-debug-toolbar<?= $attributes ?>></yii-debug-toolbar>
