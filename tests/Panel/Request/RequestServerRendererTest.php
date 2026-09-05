<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Request;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPForge\Debug\Helper\CellMore;
use PHPForge\Debug\Panel\Request\{RequestHero, RequestSection, RequestServerRenderer, RequestTab, RequestView};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function str_repeat;
use function substr_count;
use function trim;

use const LIBXML_NOERROR;
use const LIBXML_NOWARNING;

/**
 * Unit tests for additional server diagnostics and the complete raw-variable disclosure.
 */
#[Group('panel')]
#[Group('request')]
final class RequestServerRendererTest extends TestCase
{
    public function testRenderForRequestDemotesExactDuplicatesButPreservesOriginalData(): void
    {
        $entries = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/orders?a=1&a=2',
            'REMOTE_ADDR' => '203.0.113.5',
            'QUERY_STRING' => 'a=1&a=2',
            'HTTP_ACCEPT' => 'text/html',
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_PORT' => 5000,
            'PATH_INFO' => '/rewritten/orders',
            'REQUEST_TIME_FLOAT' => 1700000000.125,
            'SCRIPT_FILENAME' => '/app/public/index.php',
        ];

        $before = $entries;

        $html = RequestServerRenderer::renderForRequest(
            $entries,
            self::view(
                [
                    'Accept' => 'text/html',
                    'Content-Type' => ['application/json'],
                ],
            )
        );

        $xpath = self::xpath($html);

        self::assertSame(
            ['PATH_INFO', 'REQUEST_TIME_FLOAT', 'REMOTE_PORT', 'SCRIPT_FILENAME'],
            self::names($xpath, '//div[@class="yii-debug-server-additional"]'),
            'Only exact duplicates may leave the primary view.',
        );
        self::assertSame(
            array_keys($entries),
            self::names($xpath, '//details[@aria-label="Raw server variables"]'),
            'Raw data must include every original key.',
        );
        self::assertSame(
            $before,
            $entries,
            'Presentation must not mutate the captured data.',
        );
        self::assertStringContainsString(
            '4 additional / 10 captured',
            $html,
            'Counts must distinguish technical details from raw capture.',
        );
        self::assertStringNotContainsString(
            'Additional header variables',
            $html,
            'Fully duplicated header groups must not create empty sections.',
        );
        self::assertStringContainsString(
            '1700000000.125',
            $html,
            'Timestamp precision must remain inspectable.',
        );
    }

    public function testRenderForRequestIgnoresUnrelatedSectionsAndMalformedOverviewUrl(): void
    {
        $view = self::view([]);

        $hero = $view->hero;

        $invalid = new RequestView(
            RequestHero::create(method: $hero->getMethod(), url: 'http://:')
                ->withStatus(200, '2xx')
                ->withIp('')
                ->withTiming('', '')
                ->withFlags([]),
            [
                new RequestTab(
                    'Other',
                    [
                        new RequestSection('Headers', ['Accept' => 'text/html'], id: 'request-headers'),
                    ],
                    'other',
                ),
                new RequestTab(
                    'Headers',
                    [
                        new RequestSection('Response', ['Accept' => 'text/html'], id: 'response-headers'),
                    ],
                    'headers',
                ),
            ],
        );

        $html = RequestServerRenderer::renderForRequest(['HTTP_ACCEPT' => 'text/html', 'REQUEST_URI' => '/'], $invalid);

        self::assertStringContainsString(
            '2 additional / 2 captured',
            $html,
            'Only valid overview and inbound-header context may establish duplicates.',
        );
    }

    public function testRenderForRequestKeepsDifferencesAmbiguousHeadersAndMalformedValues(): void
    {
        $entries = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/internal/orders',
            'REMOTE_ADDR' => '10.0.0.1',
            'QUERY_STRING' => 'a=2&a=1',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_MULTI' => 'first, second',
            'HTTP_X_CASE' => 'same',
            'HTTP_EMPTY' => '',
            'HTTP_MISSING' => 'unknown',
            'REDIRECT_HTTP_ACCEPT' => 'text/html',
            'CONTENT_LENGTH' => 12,
            'CUSTOM' => ['original' => true],
            4 => 'legacy',
        ];

        $html = RequestServerRenderer::renderForRequest(
            $entries,
            self::view(
                [
                    'Accept' => 'text/html',
                    'X-Multi' => ['first', 'second'],
                    'X-Case' => 'same',
                    'x-case' => 'same',
                    'Empty' => '',
                    'Content-Length' => '12',
                    0 => 'Raw line',
                ],
            ),
        );

        $xpath = self::xpath($html);

        self::assertCount(
            13,
            self::names($xpath, '//div[@class="yii-debug-server-additional"]'),
            'Proxy differences, raw shapes, and ambiguity must remain visible.',
        );
        self::assertStringContainsString(
            'Additional header variables',
            $html,
            'Unmatched server headers must remain discoverable.',
        );
        self::assertStringContainsString(
            '13 additional / 13 captured',
            $html,
            'Potential duplicates must not be assumed equivalent.',
        );
    }

    public function testRenderForRequestPreservesEncodedTargetsAndOriginalKeyCasing(): void
    {
        $view = self::view(['accept' => 'text/html']);

        $html = RequestServerRenderer::renderForRequest(
            [
                'http_accept' => 'text/html',
                'REQUEST_URI' => '/orders?a=%31&a=2',
                'SERVER_NAME' => '::1',
                'SERVER_PORT' => 8080,
            ],
            $view,
        );

        $xpath = self::xpath($html);

        self::assertSame(
            ['REQUEST_URI', 'SERVER_NAME', 'SERVER_PORT'],
            self::names($xpath, '//div[@class="yii-debug-server-additional"]'),
            'Encoded URI differences must not be normalized away.',
        );
        self::assertSame(
            ['http_accept', 'REQUEST_URI', 'SERVER_NAME', 'SERVER_PORT'],
            self::names($xpath, '//details[@aria-label="Raw server variables"]'),
            'Original casing must survive in the raw view.',
        );
        self::assertStringContainsString(
            '::1',
            $html,
            'IPv6 values must remain unchanged.',
        );
        self::assertStringNotContainsString(
            '[::1]:8080',
            $html,
            'The removed summary must not synthesize host values.',
        );
    }

    public function testRenderKeepsEveryVariableWhenRequestContextIsUnavailable(): void
    {
        $entries = [
            'REQUEST_METHOD' => 'GET',
            'SERVER_NAME' => '::1',
            'SERVER_PORT' => 8080,
            'SCRIPT_FILENAME' => '/app/index.php',
            'HTTP_ACCEPT' => 'text/html',
            'APP_ENV' => 'debug',
        ];

        $html = RequestServerRenderer::render($entries);

        $xpath = self::xpath($html);

        self::assertStringContainsString(
            'Server details',
            $html,
            'The title must describe the secondary diagnostics.',
        );
        self::assertStringContainsString(
            '6 additional / 6 captured',
            $html,
            'Shown and captured counts must remain explicit.',
        );
        self::assertStringNotContainsString(
            'Execution context',
            $html,
            'The duplicate execution summary must be removed.',
        );
        self::assertSame(
            array_keys($entries),
            self::names($xpath, '//div[@class="yii-debug-server-additional"]'),
            'Unknown duplication must not hide entries.',
        );
        self::assertSame(
            array_keys($entries),
            self::names($xpath, '//details[@aria-label="Raw server variables"]'),
            'Raw variables must retain capture order.',
        );
        self::assertSame(
            5.0,
            $xpath->evaluate('count(//div[@class="yii-debug-server-additional"]/details[@open])'),
            'Every populated technical group must start open.',
        );
        self::assertSame(
            0.0,
            $xpath->evaluate('count(//details[@aria-label="Raw server variables"][@open])'),
            'The complete raw view must start closed.',
        );
        self::assertSame(
            6,
            substr_count($html, 'data-yii-debug-filter="true"'),
            'Each group must have its own filter.',
        );
        self::assertSame(
            6,
            substr_count($html, 'data-yii-debug-filter-scope="true"'),
            'Every filter must stay inside its group scope.',
        );
        self::assertStringContainsString(
            'aria-label="Filter Raw server variables"',
            $html,
            'Raw data needs an independent filter.',
        );
        self::assertStringNotContainsString(
            '&#039;GET&#039;',
            $html,
            'Plain strings must not acquire dump quotes.',
        );
    }

    public function testRenderKeepsUnsafeAndLongLegacyValuesInspectable(): void
    {
        $html = RequestServerRenderer::render([
            'CUSTOM_<KEY>' => "<script>alert(1)</script>\xFF",
            4 => ['nested' => '<value>'],
            'LONG_VALUE' => str_repeat('x', CellMore::THRESHOLD + 1),
        ]);

        self::assertStringNotContainsString(
            '<script>',
            $html,
            'Server diagnostics must never become executable.',
        );
        self::assertStringContainsString(
            '&lt;script&gt;',
            $html,
            'Escaped diagnostics must remain readable.',
        );
        self::assertStringContainsString(
            "\u{FFFD}",
            $html,
            'Malformed UTF-8 must stay inspectable.',
        );
        self::assertStringContainsString(
            'nested',
            $html,
            'Structured values must remain visible.',
        );
        self::assertStringContainsString(
            '&lt;value&gt;',
            $html,
            'Nested values must remain escaped.',
        );
        self::assertStringContainsString(
            'yii-debug-cell-more',
            $html,
            'Long values must retain the shared visual clamp.',
        );
    }

    public function testRenderUsesCompactEmptyStatesWithoutInertFilters(): void
    {
        $html = RequestServerRenderer::render([]);

        self::assertStringContainsString(
            '0 additional / 0 captured',
            $html,
            'Empty counts must remain explicit.',
        );
        self::assertStringContainsString(
            'No server variables captured.',
            $html,
            'Absent capture must be distinguished from duplication.',
        );
        self::assertStringNotContainsString(
            'data-yii-debug-filter="true"',
            $html,
            'Empty capture must not create a dead filter.',
        );
        self::assertStringNotContainsString(
            'Raw server variables',
            $html,
            'Empty capture must not create an empty raw view.',
        );

        $html = RequestServerRenderer::renderForRequest(['REQUEST_METHOD' => 'GET'], self::view([]));

        self::assertStringContainsString(
            'No additional server details.',
            $html,
            'Fully duplicated capture must explain the empty primary view.',
        );
        self::assertSame(
            1,
            substr_count($html, 'data-yii-debug-filter="true"'),
            'Only the populated raw view needs a filter.',
        );
        self::assertStringContainsString(
            'REQUEST_METHOD',
            $html,
            'Demoted values must remain available in raw data.',
        );
    }

    /**
     * @return list<string>
     */
    private static function names(DOMXPath $xpath, string $scope): array
    {
        $nodes = $xpath->query($scope . '//dt');

        self::assertNotFalse($nodes, 'The variable-name query must be valid.');

        $names = [];

        foreach ($nodes as $node) {
            self::assertInstanceOf(
                DOMElement::class,
                $node,
                'Variable labels must be elements.',
            );

            $names[] = trim($node->textContent);
        }

        return $names;
    }

    /**
     * @param array<int|string, mixed> $headers
     */
    private static function view(array $headers): RequestView
    {
        return new RequestView(
            RequestHero::create(method: 'GET', url: 'https://example.test/orders?a=1&a=2')
                ->withStatus(200, '2xx')
                ->withIp('203.0.113.5')
                ->withTiming('12:00:00', '')
                ->withFlags([]),
            [
                new RequestTab(
                    'Headers',
                    [
                        new RequestSection('Request Headers', $headers, id: 'request-headers'),
                    ],
                    'headers',
                ),
            ],
        );
    }

    private static function xpath(string $html): DOMXPath
    {
        if ($html === '') {
            self::fail('The rendered view must not be empty.');
        }

        $document = new DOMDocument();

        self::assertTrue(
            $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING),
            'Server markup must parse successfully.',
        );

        return new DOMXPath($document);
    }
}
