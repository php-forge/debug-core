<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Toolbar;

use PHPForge\Debug\Toolbar\{ToolbarData, ToolbarItem, ToolbarPanel};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function current;

/**
 * Unit tests for {@see ToolbarData} serializing portable toolbar panels and metrics.
 */
#[Group('toolbar')]
final class ToolbarDataTest extends TestCase
{
    public function testCreateAndWithersBuildAnImmutablePayload(): void
    {
        $empty = ToolbarData::create('request-1', 'Yii Debugger');

        $data = $empty
            ->withNavigation('/debug', '/debug/view?tag=request-1', '/debug/php-info')
            ->withPresentation('top', 42, '/icons/')
            ->withBranding('/yii.svg', '/yii-fallback.svg', '8.5.9', '3');

        self::assertSame(
            '',
            $empty->indexUrl,
            'The source payload must remain unchanged.',
        );
        self::assertSame(
            '/debug',
            $data->indexUrl,
            'Navigation enrichment must set the history URL.',
        );
        self::assertSame(
            '/debug/view?tag=request-1',
            $data->configUrl,
            'Navigation enrichment must set the configuration URL.',
        );
        self::assertSame(
            '/debug/php-info',
            $data->phpInfoUrl,
            'Navigation enrichment must set the PHP info URL.',
        );
        self::assertSame(
            'top',
            $data->position,
            'Presentation enrichment must set the toolbar position.',
        );
        self::assertSame(
            42,
            $data->defaultHeight,
            'Presentation enrichment must set the default height.',
        );
        self::assertSame(
            '/icons/',
            $data->iconBaseUrl,
            'Presentation enrichment must set the icon base URL.',
        );
        self::assertSame(
            '/yii.svg',
            $data->logo,
            'Branding enrichment must set the primary logo.',
        );
        self::assertSame(
            '/yii-fallback.svg',
            $data->logoFallback,
            'Branding enrichment must set the fallback logo.',
        );
        self::assertSame(
            '8.5.9',
            $data->phpVersion,
            'Branding enrichment must set the PHP version.',
        );
        self::assertSame(
            '3',
            $data->yiiVersion,
            'Branding enrichment must set the Yii version.',
        );
    }

    public function testJsonSerializeBuildsBrowserPayload(): void
    {
        $data = new ToolbarData(
            tag: 'request-1',
            title: 'Yii Debugger',
            indexUrl: '/debug',
            configUrl: '/debug/view?tag=request-1',
            items: [
                new ToolbarPanel(
                    id: 'request',
                    title: 'Request',
                    url: '/debug/view?tag=request-1&panel=request',
                    icon: 'request',
                    items: [
                        new ToolbarItem(
                            value: '200',
                            label: 'Status',
                            status: 'success',
                        ),
                    ],
                ),
            ],
            phpVersion: '8.5.9',
            yiiVersion: '3',
        );

        $payload = $data->jsonSerialize();

        $panel = current($payload['items']);

        self::assertSame(
            'request-1',
            $payload['tag'],
            'Request tag must be preserved.',
        );
        self::assertSame(
            50,
            $payload['defaultHeight'],
            "Default drawer height must remain '50' percent.",
        );
        self::assertIsArray(
            $panel,
            'Serialized payload must contain the request panel.',
        );
        self::assertSame(
            'request',
            $panel['id'],
            'Panel ID must be preserved.',
        );

        $item = current($panel['items']);

        self::assertIsArray(
            $item,
            'Request panel must contain its status metric.',
        );
        self::assertSame(
            'Status',
            $item['label'] ?? null,
            'Metric label must be preserved.',
        );
        self::assertSame(
            '200',
            $item['value'],
            'Metric value must be preserved.',
        );
        self::assertArrayNotHasKey(
            'icon',
            $item,
            'Absent optional metric fields must be omitted.',
        );
        self::assertNull(
            $payload['phpInfoUrl'],
            'Unavailable PHP information URL must remain `null`.',
        );
    }

    public function testJsonSerializeOmitsNullPanelUrlAndIcon(): void
    {
        $panel = new ToolbarPanel(
            id: 'logs',
            title: 'Logs',
            items: [
                new ToolbarItem(
                    value: '3',
                    status: 'info',
                ),
            ],
        );

        self::assertSame(
            [
                'id' => 'logs',
                'title' => 'Logs',
                'items' => [
                    [
                        'value' => '3',
                        'status' => 'info',
                    ],
                ],
            ],
            $panel->jsonSerialize(),
            'Payload must carry no `null` url or icon keys.',
        );
    }

    public function testJsonSerializePreservesAllPortableFields(): void
    {
        $item = new ToolbarItem(
            value: '15',
            label: 'Count',
            icon: 'db',
            status: 'warning',
            title: 'Database queries',
            url: '/debug/db',
            id: 'query-count',
        );
        $panel = new ToolbarPanel(
            id: 'db',
            title: 'Database',
            url: '/debug/db',
            icon: 'db',
            items: [$item],
        );
        $data = new ToolbarData(
            tag: 'request-2',
            title: 'Debugger',
            indexUrl: '/debug',
            configUrl: '/debug/config',
            items: [$panel],
            position: 'top',
            defaultHeight: 42,
            iconBaseUrl: '/icons/',
            logo: '/icons/yii.svg',
            logoFallback: '/yii.png',
            phpInfoUrl: '/debug/php-info',
            phpVersion: '8.5.9',
            yiiVersion: '3.0',
        );

        self::assertSame(
            [
                'configUrl' => '/debug/config',
                'defaultHeight' => 42,
                'iconBaseUrl' => '/icons/',
                'indexUrl' => '/debug',
                'items' => [
                    [
                        'id' => 'db',
                        'title' => 'Database',
                        'url' => '/debug/db',
                        'icon' => 'db',
                        'items' => [
                            [
                                'label' => 'Count',
                                'icon' => 'db',
                                'value' => '15',
                                'status' => 'warning',
                                'title' => 'Database queries',
                                'url' => '/debug/db',
                                'id' => 'query-count',
                            ],
                        ],
                    ],
                ],
                'logo' => '/icons/yii.svg',
                'logoFallback' => '/yii.png',
                'phpInfoUrl' => '/debug/php-info',
                'phpVersion' => '8.5.9',
                'position' => 'top',
                'tag' => 'request-2',
                'title' => 'Debugger',
                'yiiVersion' => '3.0',
            ],
            $data->jsonSerialize(),
            'Serialized payload must preserve every portable field.',
        );
    }
}
