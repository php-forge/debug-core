<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Helper;

use PHPForge\Debug\Helper\Trace;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see Trace} covering escaped source links, template selection, and source-path rewriting.
 */
#[Group('helper')]
final class TraceTest extends TestCase
{
    public function testRenderAppliesPathMappingsToTheLinkTargetOnly(): void
    {
        $frame = ['file' => '/app/data/file.php', 'line' => 10];

        self::assertSame(
            '<a href="ide://open?url=file:///app/localdata/file.php&amp;line=10">/app/data/file.php:10</a>',
            Trace::create()
                ->withPathMappings(['/app/data' => '/app/localdata', '/app' => '/newpath'])
                ->render($frame),
            'Only the first matching prefix may be applied.',
        );
        self::assertSame(
            '<a href="ide://open?url=file:///newpath/file.php&amp;line=10">/app/file.php:10</a>',
            Trace::create()
                ->withPathMappings(['/app' => '/newpath/'])
                ->render(['file' => '/app/file.php', 'line' => 10]),
            'A trailing slash on either side must not double up.',
        );
        self::assertSame(
            '<a href="ide://open?url=file:///local/file.php&amp;line=10">/app/file.php:10</a>',
            Trace::create()
                ->withPathMappings(['/skip' => ['ignored'], '/app' => '/local'])
                ->render(['file' => '/app/file.php', 'line' => 10]),
            'A later valid entry must survive a dropped one.',
        );
        self::assertSame(
            '<a href="ide://open?url=file:///local/file.php&amp;line=10">1234/file.php:10</a>',
            Trace::create()->withPathMappings(['1234' => '/local'])->render(['file' => '1234/file.php', 'line' => 10]),
            'Numeric prefixes must survive PHP integer key casting.',
        );
        self::assertSame(
            '<a href="ide://open?url=file:///app/data/file.php&amp;line=10">/app/data/file.php:10</a>',
            Trace::create()->withPathMappings(['/other' => '/local'])->render($frame),
            'An unmatched prefix must leave the path alone.',
        );
    }

    public function testRenderDumpsClosureResultsThatAreNotStrings(): void
    {
        self::assertSame(
            "[\n    &#039;not&#039; =&gt; &#039;&lt;string&gt;&#039;\n]",
            Trace::create()
                ->withTemplate(static fn(array $frame): array => ['not' => '<string>'])
                ->render(['file' => '/a.php', 'line' => 7]),
            'Unrenderable results must stay visible as an escaped dump.',
        );
    }

    public function testRenderEmitsPlainTextWhenTheTemplateIsDisabled(): void
    {
        self::assertSame(
            '/a&lt;b.php:7',
            Trace::create()->withTemplate(false)->render(['file' => '/a<b.php', 'line' => 7]),
            'Escaped text must replace the anchor markup.',
        );
    }

    public function testRenderEscapesSourceLinksAndUnsupportedFrames(): void
    {
        self::assertSame(
            '<a href="ide://open?url=file:///a&lt;b.php&amp;x&amp;line=7">/a&lt;b.php&amp;x:7</a>',
            Trace::create()->render(['file' => '/a<b.php&x', 'line' => 7]),
            'Source labels and URLs must be escaped.',
        );
        self::assertSame(
            '[]',
            Trace::create()->render([]),
            'Empty frames must use the shared dump fallback.',
        );
        self::assertSame(
            "[\n    &#039;file&#039; =&gt; &#039;/a&#039;\n]",
            Trace::create()->render(['file' => '/a']),
            'Missing line numbers must remain visible as a diagnostic dump.',
        );
    }

    public function testRenderNormalizesBackslashesOnBothSidesOfAMapping(): void
    {
        self::assertSame(
            '<a href="ide://open?url=file://D:/local/file.php&amp;line=10">C:\app\file.php:10</a>',
            Trace::create()
                ->withPathMappings(['C:\app\\' => 'D:\local'])
                ->render(['file' => 'C:\app\file.php', 'line' => 10]),
            'The link target must use forward slashes while the label stays verbatim.',
        );
    }

    public function testRenderPassesTheNormalizedFrameToTheClosureAndKeepsItsMarkup(): void
    {
        $captured = [];

        $html = Trace::create()
            ->withPathMappings(['C:\app' => '/local'])
            ->withTemplate(
                static function (array $frame) use (&$captured): string {
                    $captured = $frame;

                    return '<b>raw & markup</b>';
                },
            )
            ->render(['file' => 'C:\app\file.php', 'line' => 10, 'extra' => 'kept']);

        self::assertSame(
            '<b>raw & markup</b>',
            $html,
            'Closure markup must be trusted verbatim.',
        );
        self::assertSame(
            [
                'file' => '/local/file.php',
                'line' => '10',
                'extra' => 'kept',
                'text' => 'C:\app\file.php:10',
            ],
            $captured,
            'The frame must carry the mapped file, the line as `string`, and the default label.',
        );
    }

    public function testRenderResolvesPlaceholdersOfAClosureTemplate(): void
    {
        self::assertSame(
            '/a&#039;b.php:7 @ /a&#039;b.php',
            Trace::create()
                ->withTemplate(static fn(array $frame): string => '{text} @ {file}')
                ->render(['file' => "/a'b.php", 'line' => 7]),
            'A returned template must resolve the escaped placeholders.',
        );
    }

    public function testRenderResolvesPlaceholdersOfACustomStringTemplate(): void
    {
        self::assertSame(
            '<a href="phpstorm://open?url=/a&#039;b&lt;c.php&amp;line=7">/a&#039;b&lt;c.php:7</a>',
            Trace::create()
                ->withTemplate('<a href="phpstorm://open?url={file}&amp;line={line}">{text}</a>')
                ->render(['file' => "/a'b<c.php", 'line' => 7]),
            'Quotes and angle brackets must be escaped in every placeholder.',
        );
    }

    public function testRenderTreatsStringAndIntegerLineNumbersAlike(): void
    {
        $trace = Trace::create();

        self::assertSame(
            '<a href="ide://open?url=file:///a.php&amp;line=7">/a.php:7</a>',
            $trace->render(['file' => '/a.php', 'line' => '7']),
            'A `string` line must render as a plain number.',
        );
        self::assertSame(
            $trace->render(['file' => '/a.php', 'line' => 7]),
            $trace->render(['file' => '/a.php', 'line' => '7']),
            'Both line types must produce one output.',
        );
    }

    public function testRenderUsesTheProvidedTextLabel(): void
    {
        self::assertSame(
            '<a href="ide://open?url=file:///a.php&amp;line=7">custom &amp; text</a>',
            Trace::create()->render(['file' => '/a.php', 'line' => 7, 'text' => 'custom & text']),
            'The label must replace the default `file:line` text.',
        );
        self::assertSame(
            "<a href=\"ide://open?url=file:///a.php&amp;line=7\">[\n    0 =&gt; &#039;x&#039;\n]</a>",
            Trace::create()->render(['file' => '/a.php', 'line' => 7, 'text' => ['x']]),
            'A non-scalar label must fall back to an escaped dump.',
        );
    }

    public function testWithersReturnIndependentCopies(): void
    {
        $frame = ['file' => '/app/file.php', 'line' => 10];

        $base = Trace::create();

        $mapped = $base->withPathMappings(['/app' => '/local']);
        $templated = $mapped->withTemplate('{file}|{line}');

        self::assertNotSame(
            $base,
            $mapped,
            'Mapping must not mutate the source instance.',
        );
        self::assertNotSame(
            $mapped,
            $templated,
            'Templating must not mutate the source instance.',
        );
        self::assertSame(
            '<a href="ide://open?url=file:///app/file.php&amp;line=10">/app/file.php:10</a>',
            $base->render($frame),
            'The source instance must stay unmapped.',
        );
        self::assertSame(
            '/local/file.php|10',
            $templated->render($frame),
            'A new template must keep the mappings.',
        );
        self::assertSame(
            '/app/file.php|10',
            $base->withTemplate('{file}|{line}')->withPathMappings([])->render($frame),
            'New mappings must keep the template.',
        );
    }
}
