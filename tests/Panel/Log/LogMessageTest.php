<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Log;

use PHPForge\Debug\Panel\Log\LogMessage;
use PHPForge\Debug\Tests\Provider\LogMessageProvider;
use PHPForge\Debug\Tests\Support\MessageCatalogTestCase;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use UIAwesome\Html\Flow\P;

/**
 * Tests the {@see LogMessage} text catalog and direct enum content without changing rendering or escaping.
 */
#[Group('panel')]
#[Group('log')]
final class LogMessageTest extends MessageCatalogTestCase
{
    public function testCapturedValuesRemainEscapedAlongsideMessages(): void
    {
        $html = P::tag()
            ->content(LogMessage::EMPTY_HEADLINE, ': <script>&')
            ->render();

        self::assertSame(
            "<p>\nNo log messages captured: &lt;script&gt;&amp;\n</p>",
            $html,
            'Enum content must not bypass encoding for adjacent captured values.',
        );
    }

    #[DataProviderExternal(LogMessageProvider::class, 'messages')]
    public function testRendersMessageDirectlyAsContent(LogMessage $message, string $expected): void
    {
        self::assertRendersAsContent(
            $message,
            $expected,
        );
    }

    /**
     * @return list<LogMessage> Cases of the catalog under test.
     */
    protected function catalogCases(): array
    {
        return LogMessage::cases();
    }

    /**
     * @return iterable<string, array{LogMessage, string}> Provider rows that drive the rendering test.
     */
    protected function catalogProvider(): iterable
    {
        return LogMessageProvider::messages();
    }
}
