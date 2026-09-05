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
    public function testCreateMatchesConstructorDefaults(): void
    {
        self::assertEquals(
            new ToolbarItem('0'),
            ToolbarItem::create('0'),
            'Factory defaults must match the constructor.',
        );
    }

    public function testFluentConstructionMatchesLegacyPayload(): void
    {
        $item = ToolbarItem::create('0')
            ->withLabel('Status')
            ->withIcon('request')
            ->withStatus('default')
            ->withTitle('<status>')
            ->withUrl('/debug?tag=0&panel=request')
            ->withId('status');

        self::assertSame(
            (new ToolbarItem('0', 'Status', 'request', 'default', '<status>', '/debug?tag=0&panel=request', 'status'))
                ->jsonSerialize(),
            $item->jsonSerialize(),
            'Fluent construction must preserve the exact serialized field order and raw values.',
        );
    }

    #[DataProviderExternal(ToolbarItemProvider::class, 'nullableValues')]
    public function testWithIconPreservesOriginalAndOtherFields(string|null $value): void
    {
        $original = new ToolbarItem('0', 'Label', 'request', 'success', '<title>', '/debug', 'metric');

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
        $original = new ToolbarItem('0', 'Label', 'request', 'success', '<title>', '/debug', 'metric');

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
        $original = new ToolbarItem('0', 'Label', 'request', 'success', '<title>', '/debug', 'metric');

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
        $original = new ToolbarItem('0', 'Label', 'request', 'success', '<title>', '/debug', 'metric');

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
        $original = new ToolbarItem('0', 'Label', 'request', 'success', '<title>', '/debug', 'metric');

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
        $original = new ToolbarItem('0', 'Label', 'request', 'success', '<title>', '/debug', 'metric');

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
}
