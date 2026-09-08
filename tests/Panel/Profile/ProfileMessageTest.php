<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Profile;

use PHPForge\Debug\Panel\Profile\ProfileMessage;
use PHPForge\Debug\Tests\Provider\ProfileMessageProvider;
use PHPForge\Debug\Tests\Support\MessageCatalogTestCase;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use UIAwesome\Html\Flow\P;

/**
 * Tests the {@see ProfileMessage} text catalog and direct enum content without changing rendering or escaping.
 */
#[Group('panel')]
#[Group('profile')]
final class ProfileMessageTest extends MessageCatalogTestCase
{
    public function testCapturedValuesRemainEscapedAlongsideMessages(): void
    {
        $html = P::tag()
            ->content(ProfileMessage::EMPTY_HEADLINE, ': <script>&')
            ->render();

        self::assertSame(
            "<p>\nNo profiling data captured: &lt;script&gt;&amp;\n</p>",
            $html,
            'Enum content must not bypass encoding for adjacent captured values.',
        );
    }

    #[DataProviderExternal(ProfileMessageProvider::class, 'messages')]
    public function testRendersMessageDirectlyAsContent(ProfileMessage $message, string $expected): void
    {
        self::assertRendersAsContent(
            $message,
            $expected,
        );
    }

    /**
     * @return list<ProfileMessage> Cases of the catalog under test.
     */
    protected function catalogCases(): array
    {
        return ProfileMessage::cases();
    }

    /**
     * @return iterable<string, array{ProfileMessage, string}> Provider rows that drive the rendering test.
     */
    protected function catalogProvider(): iterable
    {
        return ProfileMessageProvider::messages();
    }
}
