<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

/**
 * Data provider for {@see \PHPForge\Debug\Tests\Panel\Asset\AssetBundleNormalizerTest} test cases.
 */
final class AssetBundleNormalizerProvider
{
    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: int, 2: string}>
     */
    public static function bodyColsCases(): iterable
    {
        yield 'files + depends' => [
            [
                'js' => ['a.js'],
                'depends' => ['app\\B'],
            ],
            2,
            "Files + depends must produce a '2-column' layout.",
        ];
        yield 'files + wiring' => [
            [
                'css' => ['a.css'],
                'sourcePath' => '@app/assets',
            ],
            2,
            "Files + wiring must produce a '2-column' layout.",
        ];
        yield 'only files' => [
            ['css' => ['a.css']],
            1,
            'Files-only bundles must use a 1-column layout.',
        ];
        yield 'only wiring' => [
            ['baseUrl' => '/assets'],
            1,
            'Wiring-only bundles must use a 1-column layout.',
        ];
    }
}
