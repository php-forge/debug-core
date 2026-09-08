<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Inertia;

use PHPForge\Debug\Panel\Inertia\InertiaMessage;
use PHPForge\Debug\Tests\Provider\InertiaMessageProvider;
use PHPForge\Debug\Tests\Support\MessageCatalogTestCase;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use UIAwesome\Html\Flow\P;

/**
 * Tests the {@see InertiaMessage} text catalog and direct enum content without changing rendering or escaping.
 */
#[Group('panel')]
#[Group('inertia')]
final class InertiaMessageTest extends MessageCatalogTestCase
{
    public function testCapturedValuesRemainEscapedAlongsideMessages(): void
    {
        $html = P::tag()
            ->content(InertiaMessage::EMPTY_HEADLINE, ': <script>&')
            ->render();

        self::assertSame(
            "<p>\nNo Inertia page in this request: &lt;script&gt;&amp;\n</p>",
            $html,
            'Enum content must not bypass encoding for adjacent captured values.',
        );
    }

    #[DataProviderExternal(InertiaMessageProvider::class, 'messages')]
    public function testRendersMessageDirectlyAsContent(InertiaMessage $message, string $expected): void
    {
        self::assertRendersAsContent(
            $message,
            $expected,
        );
    }

    /**
     * @return list<InertiaMessage> Cases of the catalog under test.
     */
    protected function catalogCases(): array
    {
        return InertiaMessage::cases();
    }

    /**
     * @return iterable<string, array{InertiaMessage, string}> Provider rows that drive the rendering test.
     */
    protected function catalogProvider(): iterable
    {
        return InertiaMessageProvider::messages();
    }
}
