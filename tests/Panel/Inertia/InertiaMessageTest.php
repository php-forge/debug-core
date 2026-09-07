<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Inertia;

use PHPForge\Debug\Panel\Inertia\InertiaMessage;
use PHPForge\Debug\Tests\Provider\InertiaMessageProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;
use UIAwesome\Html\Flow\P;
use UIAwesome\Html\Helper\Encode;

use function array_column;
use function iterator_to_array;

/**
 * Tests the {@see InertiaMessage} text catalog and direct enum content without changing rendering or escaping.
 */
#[Group('panel')]
#[Group('inertia')]
final class InertiaMessageTest extends TestCase
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

    public function testProviderCoversTheCompleteCatalog(): void
    {
        self::assertEqualsCanonicalizing(
            InertiaMessage::cases(),
            array_column(iterator_to_array(InertiaMessageProvider::messages()), 0),
            'Every catalog case must have an explicit wording and rendering regression test.',
        );
    }

    #[DataProviderExternal(InertiaMessageProvider::class, 'messages')]
    public function testRendersMessageDirectlyAsContent(InertiaMessage $message, string $expected): void
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
