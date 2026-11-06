<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Log;

use PHPForge\Debug\Panel\Log\DumpNode;
use PHPForge\Debug\Tests\Provider\DumpNodeProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see DumpNode} covering the typed tree built from normalized log context values.
 *
 * {@see DumpNodeProvider} for test case data providers.
 */
#[Group('panel')]
#[Group('log')]
final class DumpNodeTest extends TestCase
{
    #[DataProviderExternal(DumpNodeProvider::class, 'values')]
    public function testFromValueBuildsTypedTree(mixed $value, DumpNode $expected): void
    {
        self::assertEquals(
            $expected,
            DumpNode::fromValue($value),
            'Tree must keep every key, type, and value text.',
        );
    }
}
