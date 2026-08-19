<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Helper;

use PHPForge\Debug\Helper\Text;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see Text} covering identifier separators and Unicode case conversion.
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
}
