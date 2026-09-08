<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Toolbar;

use PHPForge\Debug\Tests\Provider\ToolbarItemProvider;
use PHPForge\Debug\Toolbar\ToolbarItem;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

use function get_object_vars;

/**
 * Unit tests for fluent toolbar metric construction and immutable optional fields.
 */
#[Group('toolbar')]
final class ToolbarItemTest extends TestCase
{
    public function testCreateLeavesEveryOptionalFieldUnset(): void
    {
        self::assertSame(
            [
                'value' => '0',
                'label' => null,
                'icon' => null,
                'status' => 'default',
                'title' => null,
                'url' => null,
                'id' => null,
            ],
            get_object_vars(ToolbarItem::create('0')),
            "Only the value and the 'default' status must be set.",
        );
    }

    public function testFluentConstructionSerializesEveryField(): void
    {
        $item = ToolbarItem::create('0')
            ->withLabel('Status')
            ->withIcon('request')
            ->withStatus('default')
            ->withTitle('<status>')
            ->withUrl('/debug?tag=0&panel=request')
            ->withId('status');

        self::assertSame(
            [
                'label' => 'Status',
                'icon' => 'request',
                'value' => '0',
                'status' => 'default',
                'title' => '<status>',
                'url' => '/debug?tag=0&panel=request',
                'id' => 'status',
            ],
            $item->jsonSerialize(),
            'Serialized field order and raw values must be preserved.',
        );
    }

    #[DataProviderExternal(ToolbarItemProvider::class, 'nullableValues')]
    public function testWithIconPreservesOriginalAndOtherFields(string|null $value): void
    {
        $original = self::sample();

        $before = get_object_vars($original);

        $expected = $before;
        $expected['icon'] = $value;

        $modified = $original->withIcon($value);

        self::assertNotSame(
            $original,
            $modified,
            'Configuration must always return a distinct instance.',
        );
        self::assertSame(
            $before,
            get_object_vars($original),
            'Configuration must leave the original unchanged.',
        );
        self::assertSame(
            $expected,
            get_object_vars($modified),
            'Configuration must preserve every other field.',
        );

        $payload = $modified->jsonSerialize();

        if ($value === null) {
            self::assertArrayNotHasKey(
                'icon',
                $payload,
                'Only null optional fields must be omitted.',
            );
        } else {
            self::assertSame(
                $value,
                $payload['icon'] ?? null,
                'Empty and zero strings must remain present.',
            );
        }
    }

    #[DataProviderExternal(ToolbarItemProvider::class, 'nullableValues')]
    public function testWithIdPreservesOriginalAndOtherFields(string|null $value): void
    {
        $original = self::sample();

        $before = get_object_vars($original);

        $expected = $before;
        $expected['id'] = $value;
        $modified = $original->withId($value);

        self::assertNotSame(
            $original,
            $modified,
            'Configuration must always return a distinct instance.',
        );
        self::assertSame(
            $before,
            get_object_vars($original),
            'Configuration must leave the original unchanged.',
        );
        self::assertSame(
            $expected,
            get_object_vars($modified),
            'Configuration must preserve every other field.',
        );

        $payload = $modified->jsonSerialize();

        if ($value === null) {
            self::assertArrayNotHasKey(
                'id',
                $payload,
                'Only null optional fields must be omitted.',
            );
        } else {
            self::assertSame(
                $value,
                $payload['id'] ?? null,
                'Empty and zero strings must remain present.',
            );
        }
    }

    #[DataProviderExternal(ToolbarItemProvider::class, 'nullableValues')]
    public function testWithLabelPreservesOriginalAndOtherFields(string|null $value): void
    {
        $original = self::sample();

        $before = get_object_vars($original);

        $expected = $before;
        $expected['label'] = $value;

        $modified = $original->withLabel($value);

        self::assertNotSame(
            $original,
            $modified,
            'Configuration must always return a distinct instance.',
        );
        self::assertSame(
            $before,
            get_object_vars($original),
            'Configuration must leave the original unchanged.',
        );
        self::assertSame(
            $expected,
            get_object_vars($modified),
            'Configuration must preserve every other field.',
        );

        $payload = $modified->jsonSerialize();

        if ($value === null) {
            self::assertArrayNotHasKey(
                'label',
                $payload,
                'Only null optional fields must be omitted.',
            );
        } else {
            self::assertSame(
                $value,
                $payload['label'] ?? null,
                'Empty and zero strings must remain present.',
            );
        }
    }

    #[DataProviderExternal(ToolbarItemProvider::class, 'statusValues')]
    public function testWithStatusPreservesOriginalAndOtherFields(string $value): void
    {
        $original = self::sample();

        $before = get_object_vars($original);

        $expected = $before;
        $expected['status'] = $value;

        $modified = $original->withStatus($value);

        self::assertNotSame(
            $original,
            $modified,
            'Configuration must always return a distinct instance.',
        );
        self::assertSame(
            $before,
            get_object_vars($original),
            'Configuration must leave the original unchanged.',
        );
        self::assertSame(
            $expected,
            get_object_vars($modified),
            'Configuration must preserve every other field.',
        );

        $payload = $modified->jsonSerialize();

        self::assertSame(
            $value,
            $payload['status'],
            'Status must be serialized without normalization.',
        );
    }

    #[DataProviderExternal(ToolbarItemProvider::class, 'nullableValues')]
    public function testWithTitlePreservesOriginalAndOtherFields(string|null $value): void
    {
        $original = self::sample();

        $before = get_object_vars($original);

        $expected = $before;
        $expected['title'] = $value;

        $modified = $original->withTitle($value);

        self::assertNotSame(
            $original,
            $modified,
            'Configuration must always return a distinct instance.',
        );
        self::assertSame(
            $before,
            get_object_vars($original),
            'Configuration must leave the original unchanged.',
        );
        self::assertSame(
            $expected,
            get_object_vars($modified),
            'Configuration must preserve every other field.',
        );

        $payload = $modified->jsonSerialize();

        if ($value === null) {
            self::assertArrayNotHasKey(
                'title',
                $payload,
                'Only null optional fields must be omitted.',
            );
        } else {
            self::assertSame(
                $value,
                $payload['title'] ?? null,
                'Empty and zero strings must remain present.',
            );
        }
    }

    #[DataProviderExternal(ToolbarItemProvider::class, 'nullableValues')]
    public function testWithUrlPreservesOriginalAndOtherFields(string|null $value): void
    {
        $original = self::sample();

        $before = get_object_vars($original);

        $expected = $before;
        $expected['url'] = $value;

        $modified = $original->withUrl($value);

        self::assertNotSame(
            $original,
            $modified,
            'Configuration must always return a distinct instance.',
        );
        self::assertSame(
            $before,
            get_object_vars($original),
            'Configuration must leave the original unchanged.',
        );
        self::assertSame(
            $expected,
            get_object_vars($modified),
            'Configuration must preserve every other field.',
        );

        $payload = $modified->jsonSerialize();

        if ($value === null) {
            self::assertArrayNotHasKey(
                'url',
                $payload,
                'Only null optional fields must be omitted.',
            );
        } else {
            self::assertSame(
                $value,
                $payload['url'] ?? null,
                'Empty and zero strings must remain present.',
            );
        }
    }

    /**
     * Returns a metric with every optional field populated.
     */
    private static function sample(): ToolbarItem
    {
        return ToolbarItem::create('0')
            ->withLabel('Label')
            ->withIcon('request')
            ->withStatus('success')
            ->withTitle('<title>')
            ->withUrl('/debug')
            ->withId('metric');
    }
}
