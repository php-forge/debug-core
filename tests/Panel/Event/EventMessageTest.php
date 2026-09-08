<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Event;

use PHPForge\Debug\Panel\Event\EventMessage;
use PHPForge\Debug\Tests\Provider\EventMessageProvider;
use PHPForge\Debug\Tests\Support\MessageCatalogTestCase;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use UIAwesome\Html\Flow\P;

/**
 * Tests the {@see EventMessage} text catalog and direct enum content without changing rendering or escaping.
 */
#[Group('panel')]
#[Group('event')]
final class EventMessageTest extends MessageCatalogTestCase
{
    public function testCapturedValuesRemainEscapedAlongsideMessages(): void
    {
        $html = P::tag()
            ->content(EventMessage::EMPTY_HEADLINE, ': <script>&')
            ->render();

        self::assertSame(
            "<p>\nNo events dispatched in this request: &lt;script&gt;&amp;\n</p>",
            $html,
            'Enum content must not bypass encoding for adjacent captured values.',
        );
    }

    #[DataProviderExternal(EventMessageProvider::class, 'messages')]
    public function testRendersMessageDirectlyAsContent(EventMessage $message, string $expected): void
    {
        self::assertRendersAsContent(
            $message,
            $expected,
        );
    }

    /**
     * @return list<EventMessage> Cases of the catalog under test.
     */
    protected function catalogCases(): array
    {
        return EventMessage::cases();
    }

    /**
     * @return iterable<string, array{EventMessage, string}> Provider rows that drive the rendering test.
     */
    protected function catalogProvider(): iterable
    {
        return EventMessageProvider::messages();
    }
}
