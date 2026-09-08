<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Support;

use BackedEnum;
use PHPUnit\Framework\TestCase;
use UIAwesome\Html\Flow\P;
use UIAwesome\Html\Helper\Encode;

use function array_column;
use function iterator_to_array;

/**
 * Shared contract for the panel presentation-text catalogs backed by a string enum and an external data provider.
 *
 * Concrete cases expose the catalog under test through {@see catalogCases()} and {@see catalogProvider()}, then
 * delegate their provider-driven test to {@see assertRendersAsContent()}, so PHPUnit still reports one class per enum.
 */
abstract class MessageCatalogTestCase extends TestCase
{
    /**
     * Returns every case of the catalog under test.
     *
     * @return list<BackedEnum> Cases of the catalog under test.
     */
    abstract protected function catalogCases(): array;

    /**
     * Returns the provider rows that drive the per-message rendering test.
     *
     * @return iterable<string, array{BackedEnum, string}> Provider rows that drive the rendering test.
     */
    abstract protected function catalogProvider(): iterable;

    public function testProviderCoversTheCompleteCatalog(): void
    {
        self::assertEqualsCanonicalizing(
            $this->catalogCases(),
            array_column(iterator_to_array($this->catalogProvider()), 0),
            'Every catalog case must have an explicit wording and rendering regression test.',
        );
    }

    /**
     * Asserts that a message renders as encoded paragraph content without mutating the source tag.
     *
     * @param BackedEnum $message Catalog case under test.
     * @param string $expected Wording the case must preserve.
     */
    protected static function assertRendersAsContent(BackedEnum $message, string $expected): void
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
