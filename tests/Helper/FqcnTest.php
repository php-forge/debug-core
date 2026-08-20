<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Helper;

use PHPForge\Debug\Helper\Fqcn;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see Fqcn} covering the shared two-tone label markup: namespace/short-name splitting, method-suffix
 * handling, the plain-value and empty-value fallbacks, and the `title` attribute.
 */
#[Group('helpers')]
#[Group('fqcn')]
final class FqcnTest extends TestCase
{
    public function testNamespacePartReturnsPrefixOrEmptyString(): void
    {
        self::assertSame(
            'App\\Service',
            Fqcn::namespacePart('App\\Service\\Mailer'),
            'Namespace prefix must be returned.',
        );
        self::assertSame(
            '',
            Fqcn::namespacePart('Mailer'),
            'Unqualified class name must have no namespace prefix.',
        );
    }

    public function testRenderLabelExposesFullValueInTitleAttribute(): void
    {
        self::assertSame(
            <<<HTML
            <span title="yii\base\Event"><span class="yii-debug-muted">yii\base\</span><wbr><strong>Event</strong></span>
            HTML,
            Fqcn::renderLabel('yii\\base\\Event'),
            'Full value must sit in the `title` attribute.',
        );
    }

    public function testRenderLabelKeepsMethodSuffixInsideStrongShortName(): void
    {
        $label = Fqcn::renderLabel('yii\\db\\Command::query');

        self::assertSame(
            <<<HTML
            <span title="yii\db\Command::query"><span class="yii-debug-muted">yii\db\</span><wbr><strong>Command::query</strong></span>
            HTML,
            $label,
            'Method pair must render bold as one segment.',
        );

    }

    public function testRenderLabelOmitsMutedPrefixForPlainValues(): void
    {
        $label = Fqcn::renderLabel('application');

        self::assertSame(
            <<<HTML
            <span title="application"><strong>application</strong></span>
            HTML,
            $label,
            'Plain value must render bold.',
        );

    }

    public function testRenderLabelRendersEmDashForEmptyValue(): void
    {
        self::assertSame(
            '—',
            Fqcn::renderLabel(''),
            'Empty value must collapse to an em dash.',
        );
    }

    public function testRenderLabelSplitsNamespacedValueIntoMutedNamespaceAndStrongShortName(): void
    {
        $label = Fqcn::renderLabel('yii\\base\\Event');

        self::assertSame(
            <<<HTML
            <span title="yii\base\Event"><span class="yii-debug-muted">yii\base\</span><wbr><strong>Event</strong></span>
            HTML,
            $label,
            'Namespaced labels must keep the namespace, break opportunity, and short name in display order.',
        );
        self::assertSame(
            <<<HTML
            <span title="yii\base\Event"><span class="yii-debug-muted">yii\base\</span><wbr><strong>Event</strong></span>
            HTML,
            $label,
            'Namespace prefix must render muted.',
        );

    }

    public function testShortNameReturnsFinalSegmentOrOriginalValue(): void
    {
        self::assertSame(
            'Mailer',
            Fqcn::shortName('App\\Service\\Mailer'),
            'Final class-name segment must be returned.',
        );
        self::assertSame(
            'Mailer',
            Fqcn::shortName('Mailer'),
            'Unqualified class name must be preserved.',
        );
    }
}
