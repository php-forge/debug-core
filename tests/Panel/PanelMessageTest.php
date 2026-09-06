<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel;

use PHPForge\Debug\Panel\PanelMessage;
use PHPForge\Debug\Tests\Provider\PanelMessageProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;
use UIAwesome\Html\Flow\P;

use function array_column;
use function iterator_to_array;

/**
 * Tests the panel text catalog and direct enum content without changing rendering or escaping.
 */
#[Group('panel')]
final class PanelMessageTest extends TestCase
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

    public function testProviderCoversTheCompleteCatalog(): void
    {
        self::assertEqualsCanonicalizing(
            PanelMessage::cases(),
            array_column(iterator_to_array(PanelMessageProvider::messages()), 0),
            'Every catalog case must have an explicit wording and rendering regression test.',
        );
    }

    #[DataProviderExternal(PanelMessageProvider::class, 'messages')]
    public function testRendersMessageDirectlyAsContent(PanelMessage $message, string $expected): void
    {
        $paragraph = P::tag();

        $rendered = $paragraph->content($message);

        self::assertSame(
            $expected,
            $message->value,
            'The catalog must preserve the existing panel wording.',
        );
        self::assertSame(
            "<p>\n{$expected}\n</p>",
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
