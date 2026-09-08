<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Db;

use PHPForge\Debug\Panel\Db\DbMessage;
use PHPForge\Debug\Tests\Provider\DbMessageProvider;
use PHPForge\Debug\Tests\Support\MessageCatalogTestCase;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};

/**
 * Unit tests for the Database text catalog with explicit DbMessageProvider wording cases.
 */
#[Group('db')]
final class DbMessageTest extends MessageCatalogTestCase
{
    #[DataProviderExternal(DbMessageProvider::class, 'messages')]
    public function testMessageRendersAsContent(DbMessage $message, string $expected): void
    {
        self::assertRendersAsContent($message, $expected);
    }

    protected function catalogCases(): array
    {
        return DbMessage::cases();
    }

    protected function catalogProvider(): iterable
    {
        return DbMessageProvider::messages();
    }
}
