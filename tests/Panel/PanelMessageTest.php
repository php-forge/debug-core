<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel;

use PHPForge\Debug\Panel\PanelMessage;
use PHPForge\Debug\Tests\Provider\PanelMessageProvider;
use PHPForge\Debug\Tests\Support\MessageCatalogTestCase;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use UIAwesome\Html\Flow\P;

/**
 * Tests the {@see PanelMessage} text catalog and direct enum content without changing rendering or escaping.
 */
#[Group('panel')]
final class PanelMessageTest extends MessageCatalogTestCase
{
    public function testCapturedValuesRemainEscapedAlongsideMessages(): void
    {
        $html = P::tag()
            ->content(PanelMessage::CONTEXT, ': <script>&')
            ->render();

        self::assertSame(
            "<p>\nContext: &lt;script&gt;&amp;\n</p>",
            $html,
            'Enum content must not bypass encoding for adjacent captured values.',
        );
    }

    #[DataProviderExternal(PanelMessageProvider::class, 'messages')]
    public function testRendersMessageDirectlyAsContent(PanelMessage $message, string $expected): void
    {
        self::assertRendersAsContent(
            $message,
            $expected,
        );
    }

    /**
     * @return list<PanelMessage> Cases of the catalog under test.
     */
    protected function catalogCases(): array
    {
        return PanelMessage::cases();
    }

    /**
     * @return iterable<string, array{PanelMessage, string}> Provider rows that drive the rendering test.
     */
    protected function catalogProvider(): iterable
    {
        return PanelMessageProvider::messages();
    }
}
