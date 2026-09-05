<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Request;

use PHPForge\Debug\Panel\Request\RequestToolbarItemFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the shared route-and-status Request toolbar metrics.
 */
#[Group('panel')]
#[Group('request')]
#[Group('toolbar')]
final class RequestToolbarItemFactoryTest extends TestCase
{
    public function testCreateMapsEveryStatusFamilyThroughSharedVocabulary(): void
    {
        foreach ([204 => 'status-2xx', 302 => 'status-3xx', 404 => 'status-4xx', 503 => 'status-5xx'] as $code => $status) {
            self::assertSame(
                [$status],
                array_map(static fn($item): string => $item->status, RequestToolbarItemFactory::create('', $code)),
                "HTTP {$code} must use the shared '{$status}' class.",
            );
        }
    }

    public function testCreateOmitsEmptyRouteAndTrimsStatusTitle(): void
    {
        $items = RequestToolbarItemFactory::create('', 0);

        self::assertCount(
            1,
            $items,
            'An unavailable route must not leave an empty toolbar metric.',
        );
        self::assertSame(
            [
                [
                    'value' => '0',
                    'status' => 'default',
                    'title' => 'Status code: 0',
                    'id' => 'status',
                ],
            ],
            array_map(static fn($item): array => $item->jsonSerialize(), $items),
            'The fallback status must keep a stable ID and no trailing title whitespace.',
        );
    }

    public function testCreatePlacesRouteBeforeSemanticStatus(): void
    {
        $items = RequestToolbarItemFactory::create('site/index', 201, 'Created');

        self::assertSame(
            [
                [
                    'value' => 'site/index',
                    'status' => 'default',
                    'title' => 'Resolved route: site/index',
                    'id' => 'route',
                ],
                [
                    'value' => '201',
                    'status' => 'status-2xx',
                    'title' => 'Status code: 201 Created',
                    'id' => 'status',
                ],
            ],
            array_map(static fn($item): array => $item->jsonSerialize(), $items),
            'Request toolbar metrics must read as route then response status.',
        );
    }
}
