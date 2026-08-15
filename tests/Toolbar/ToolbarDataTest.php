<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Toolbar;

use PHPForge\Debug\Toolbar\{ToolbarData, ToolbarItem, ToolbarPanel};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see ToolbarData} serializing portable toolbar panels and metrics.
 *
 * @since 0.1
 */
#[Group('toolbar')]
final class ToolbarDataTest extends TestCase
{
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

        self::assertSame('request-1', $payload['tag'], 'Request tag must be preserved.');
        self::assertIsArray($panel, 'Serialized payload must contain the request panel.');
        self::assertSame('request', $panel['id'], 'Panel ID must be preserved.');

        $item = current($panel['items']);

        self::assertIsArray($item, 'Request panel must contain its status metric.');
        self::assertSame('200', $item['value'], 'Metric value must be preserved.');
        self::assertArrayNotHasKey(
            'icon',
            $item,
            'Absent optional metric fields must be omitted.',
        );
        self::assertNull($payload['phpInfoUrl'], 'Unavailable PHP information URL must remain `null`.');
    }
}
