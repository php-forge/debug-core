<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Helper;

use PHPForge\Debug\Helper\Text;
use PHPForge\Debug\Tests\Provider\{LineBreakProvider, UrlPathProvider};
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see Text} covering identifiers, Unicode case conversion, line break preservation, and captured URL
 * display.
 *
 * {@see LineBreakProvider} and {@see UrlPathProvider} for test case data providers.
 */
#[Group('helpers')]
final class TextTest extends TestCase
{
    public function testCamel2idConvertsUnderscoresAndCamelCase(): void
    {
        self::assertSame(
            'app-asset-bundle',
            Text::camel2id('App_assetBundle'),
            'Underscores and CamelCase boundaries must use one canonical dash separator.',
        );
    }

    public function testCamel2idLowercasesUnicodeLetters(): void
    {
        self::assertSame(
            'árbol-über',
            Text::camel2id('ÁrbolÜber'),
            'Unicode uppercase letters must be lowercased with multibyte semantics.',
        );
    }
    public function testCamel2idPreservesEmptyInput(): void
    {
        self::assertSame(
            '',
            Text::camel2id(''),
            'Empty input must remain empty.',
        );
    }

    #[DataProviderExternal(LineBreakProvider::class, 'lineBreaks')]
    public function testPreserveLineBreaksEscapesTextAndEncodesLineBreaks(string $text, string $expected): void
    {
        self::assertSame(
            $expected,
            Text::preserveLineBreaks($text),
            'Content must be escaped once with every line break as `&#10;`.',
        );
    }

    #[DataProviderExternal(UrlPathProvider::class, 'paths')]
    public function testUrlToPathPreservesCapturedDisplay(string $url, string $expected): void
    {
        self::assertSame(
            $expected,
            Text::urlToPath($url),
            'Captured URL display must match both adapters exactly.',
        );
    }
}
