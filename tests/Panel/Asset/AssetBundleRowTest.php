<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Asset;

use PHPForge\Debug\Panel\Asset\AssetBundleRow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see AssetBundleRow} narrowing a live asset bundle into its persisted form.
 */
#[Group('panel')]
#[Group('asset')]
final class AssetBundleRowTest extends TestCase
{
    public function testFromBundleDropsNonStringFilesAndUnwrapsFileOptions(): void
    {
        $row = AssetBundleRow::fromBundle(
            'app\\assets\\AppAsset',
            [
                'css' => ['css/site.css', ['css/print.css', 'media' => 'print'], 42, []],
                'js' => 'not a list',
            ],
        );

        self::assertSame(
            ['css/site.css', 'css/print.css'],
            $row->css,
            'A file declared with options must keep only its path.',
        );
        self::assertSame(
            [],
            $row->js,
            'A malformed file list must narrow to an empty list.',
        );
    }

    public function testFromBundleNarrowsEveryDeclaredProperty(): void
    {
        $row = AssetBundleRow::fromBundle(
            'app\\assets\\AppAsset',
            [
                'sourcePath' => '@app/assets',
                'basePath' => '@webroot/assets/1a2b',
                'baseUrl' => '/assets/1a2b',
                'css' => ['css/site.css'],
                'js' => ['js/app.js'],
                'depends' => ['yii\\web\\YiiAsset'],
            ],
        );

        self::assertSame(
            [
                'name' => 'app\\assets\\AppAsset',
                'sourcePath' => '@app/assets',
                'basePath' => '@webroot/assets/1a2b',
                'baseUrl' => '/assets/1a2b',
                'css' => ['css/site.css'],
                'js' => ['js/app.js'],
                'depends' => ['yii\\web\\YiiAsset'],
            ],
            $row->jsonSerialize(),
            'Every declared property must survive the capture unchanged.',
        );
    }

    public function testFromBundleUsesEmptyStringsForMissingWiring(): void
    {
        $row = AssetBundleRow::fromBundle(
            'app\\assets\\BareAsset',
            [],
        );

        self::assertSame(
            [
                'name' => 'app\\assets\\BareAsset',
                'sourcePath' => '',
                'basePath' => '',
                'baseUrl' => '',
                'css' => [],
                'js' => [],
                'depends' => [],
            ],
            $row->jsonSerialize(),
            'A bundle without wiring must capture empty values instead of `null`.',
        );
    }
}
