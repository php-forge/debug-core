<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel;

use InvalidArgumentException;
use PHPForge\Debug\Panel\PanelFactory;
use PHPForge\Vite\Debug\VitePanel;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Unit tests for {@see PanelFactory} resolving explicitly registered, optional panel providers.
 */
final class PanelFactoryTest extends TestCase
{
    public function testCreateResolvesAnInstalledProvider(): void
    {
        self::assertInstanceOf(
            VitePanel::class,
            PanelFactory::create(VitePanel::class),
            'Installed portable providers must resolve.',
        );
    }

    public function testThrowInvalidArgumentExceptionForMissingProvider(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Debug panel provider is not installed: Missing\\DebugPanel',
        );

        PanelFactory::create('Missing\\DebugPanel');
    }

    public function testThrowInvalidArgumentExceptionForUnsupportedProvider(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Debug panel provider must extend PHPForge\\Debug\\Panel.',
        );

        PanelFactory::create(stdClass::class);
    }
}
