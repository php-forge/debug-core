<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Log;

use PHPForge\Debug\Panel\Log\{DumpKind, DumpNode};
use PHPForge\Debug\Tests\Provider\DumpNodeProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see DumpNode} covering the typed tree built from normalized log context values and its depth and
 * value count bounds.
 *
 * {@see DumpNodeProvider} for test case data providers.
 */
#[Group('panel')]
#[Group('log')]
final class DumpNodeTest extends TestCase
{
    #[DataProviderExternal(DumpNodeProvider::class, 'withinBounds')]
    public function testFromValueBuildsArrayTreeWithinBounds(mixed $value): void
    {
        self::assertSame(
            DumpKind::ARRAY,
            DumpNode::fromValue($value)?->kind,
            'Array at the depth or value count bound must build a tree.',
        );
    }

    #[DataProviderExternal(DumpNodeProvider::class, 'arrays')]
    public function testFromValueBuildsTypedTree(mixed $value, DumpNode $expected): void
    {
        self::assertEquals(
            $expected,
            DumpNode::fromValue($value),
            'Tree must keep every key, type, and value text.',
        );
    }

    #[DataProviderExternal(DumpNodeProvider::class, 'scalars')]
    public function testFromValueBuildsTypedValue(mixed $value, DumpKind $kind, string $text): void
    {
        $node = DumpNode::fromValue($value);

        self::assertNotNull(
            $node,
            'Value must build a node.',
        );
        self::assertSame(
            $kind,
            $node->kind,
            'Value must keep its type.',
        );
        self::assertSame(
            $text,
            $node->text,
            'Value must keep its printed text.',
        );
        self::assertSame(
            [],
            $node->entries,
            'Value must have no entries.',
        );
    }

    #[DataProviderExternal(DumpNodeProvider::class, 'overBounds')]
    public function testFromValueReturnsNullOverBounds(mixed $value): void
    {
        self::assertNull(
            DumpNode::fromValue($value),
            'Array over the depth or value count bound must not build a tree.',
        );
    }
}
