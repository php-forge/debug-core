<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Profile;

use PHPForge\Debug\Panel\Profile\ProfileMessage;
use PHPForge\Debug\Tests\Provider\ProfileMessageProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;
use UIAwesome\Html\Flow\P;
use UIAwesome\Html\Helper\Encode;

use function array_column;
use function iterator_to_array;

/**
 * Tests the {@see ProfileMessage} text catalog and direct enum content without changing rendering or escaping.
 */
#[Group('panel')]
#[Group('profile')]
final class ProfileMessageTest extends TestCase
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

    public function testProviderCoversTheCompleteCatalog(): void
    {
        self::assertEqualsCanonicalizing(
            ProfileMessage::cases(),
            array_column(iterator_to_array(ProfileMessageProvider::messages()), 0),
            'Every catalog case must have an explicit wording and rendering regression test.',
        );
    }

    #[DataProviderExternal(ProfileMessageProvider::class, 'messages')]
    public function testRendersMessageDirectlyAsContent(ProfileMessage $message, string $expected): void
    {
        $paragraph = P::tag();

        $rendered = $paragraph->content($message);

        self::assertSame(
            $expected,
            $message->value,
            'The catalog must preserve the existing panel wording.',
        );
        self::assertSame(
            "<p>\n" . Encode::content($expected) . "\n</p>",
            $rendered->render(),
            'Content must accept the enum case without extracting its value.',
        );
        self::assertNotSame(
            $paragraph,
            $rendered,
            'Enum content must preserve immutable tag construction.',
        );
        self::assertSame(
            '',
            $paragraph->getContent(),
            'Rendering a message must not mutate the original tag.',
        );
    }
}
