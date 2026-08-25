<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Request;

use PHPForge\Debug\Panel\Request\RequestDataNormalizer;
use PHPForge\Debug\Storage\RequestSummary;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see RequestDataNormalizer} covering captured request data plus the controller summary into the typed
 * {@see \PHPForge\Debug\Panel\Request\RequestView} aggregate (hero header + tab/section list).
 */
#[Group('panel')]
#[Group('request')]
final class RequestDataNormalizerTest extends TestCase
{
    public function testFromPanelDataAccumulatesActiveFlagsInDeclarationOrder(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            [
                'general' => [
                    'isAjax' => true,
                    'isPjax' => false,
                    'isFlash' => true,
                    'isSecureConnection' => true,
                ],
            ],
            null,
        );

        self::assertSame(
            ['AJAX', 'Flash', 'HTTPS'],
            $view->hero->flags,
            'Active flags must surface in declaration order.',
        );
    }

    public function testFromPanelDataCoercesNumericStringStatusCodeToInt(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            ['statusCode' => '404'],
            null,
        );

        self::assertSame(
            404,
            $view->hero->statusCode,
            'Numeric-string statusCode must coerce to int.',
        );
    }

    public function testFromPanelDataDropsServerTabWhenServerKeyMissing(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            [],
            null,
        );

        $labels = [];

        foreach ($view->tabs as $tab) {
            $labels[] = $tab->label;
        }

        self::assertSame(
            ['Parameters', 'Headers'],
            $labels,
            'Missing SERVER bucket must collapse the tab strip to the base pair.',
        );
    }

    public function testFromPanelDataDropsSessionTabWhenSessionOrFlashesMissing(): void
    {
        foreach ([[], ['SESSION' => []], ['flashes' => []]] as $data) {
            $view = RequestDataNormalizer::fromPanelData($data, null);

            $labels = [];

            foreach ($view->tabs as $tab) {
                $labels[] = $tab->label;
            }

            self::assertNotContains(
                'Session',
                $labels,
                'Without both SESSION and flashes the Session tab must not surface.',
            );
        }
    }

    public function testFromPanelDataExposesEveryTabWhenSessionAndServerArePresent(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            [
                'SESSION' => ['user' => 1],
                'flashes' => [],
                'SERVER' => ['HTTP_HOST' => 'localhost'],
            ],
            null,
        );

        $labels = [];

        foreach ($view->tabs as $tab) {
            $labels[] = $tab->label;
        }

        self::assertSame(
            ['Parameters', 'Headers', 'Session', 'Server'],
            $labels,
            'All four tabs must surface when SESSION + SERVER exist.',
        );
    }

    public function testFromPanelDataFallsBackToEmptyViewWhenDataIsEmpty(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            [],
            null,
        );

        self::assertSame(
            '',
            $view->hero->method,
            'Non-array data must yield an empty hero method.',
        );
        self::assertSame(
            0,
            $view->hero->statusCode,
            'Non-array data must yield a zero status code.'
        );
        self::assertSame(
            [],
            $view->hero->flags,
            'Non-array data must yield zero flags.',
        );
        self::assertSame(
            '',
            $view->hero->time,
            'Missing capture time must not render the Unix epoch.',
        );
        self::assertCount(
            2,
            $view->tabs,
            'Non-array data must still produce the base Parameters + Headers tabs.',
        );
    }

    public function testFromPanelDataMapsHttpStatusToVariantBucket(): void
    {
        foreach ([200 => '2xx', 304 => '3xx', 404 => '4xx', 500 => '5xx', 0 => 'none'] as $code => $expected) {
            $view = RequestDataNormalizer::fromPanelData(
                ['statusCode' => $code],
                null,
            );

            self::assertSame(
                $expected,
                $view->hero->statusVariant,
                "Status {$code} must map to the {$expected} variant.",
            );
        }
    }

    public function testFromPanelDataParametersTabExposesEveryOptionalBucketWhenPresent(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            [
                'route' => 'site/index',
                'action' => 'SiteController::actionIndex()',
                'actionParams' => [],
                'GET' => ['q' => 'x'],
                'POST' => ['x' => 1],
                'FILES' => [],
                'COOKIE' => ['session' => 'abc'],
                'requestBody' => [],
            ],
            null,
        );

        $firstTab = $view->tabs[0] ?? null;

        self::assertNotNull(
            $firstTab,
            'Tabs must be present.',
        );

        $captions = [];

        foreach ($firstTab->sections as $section) {
            $captions[] = $section->caption;
        }

        self::assertSame(
            ['Routing', 'Get', 'Post', 'Files', 'Cookies', 'Request Body'],
            $captions,
            'Parameters tab must include every optional bucket that exists in the payload.',
        );
    }

    public function testFromPanelDataPrefersCapturedGeneralMethodOverSummary(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            ['general' => ['method' => 'PATCH']],
            self::summary(['method' => 'GET']),
        );

        self::assertSame(
            'PATCH',
            $view->hero->method,
            'Captured method must override the controller summary method.',
        );
    }

    public function testFromPanelDataPrefersPanelStatusCodeOverSummary(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            ['statusCode' => 201],
            self::summary(['statusCode' => 500]),
        );

        self::assertSame(
            201,
            $view->hero->statusCode,
            'Panel data must override the controller summary status.',
        );
    }

    public function testFromPanelDataPreservesHeaderServerAndSessionSectionMetadata(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            [
                'requestHeaders' => ['Accept' => 'text/html'],
                'responseHeaders' => ['Content-Type' => 'text/html'],
                'SESSION' => ['user' => 1],
                'flashes' => ['notice' => 'Saved'],
                'SERVER' => ['HTTP_HOST' => 'localhost'],
            ],
            null,
        );

        self::assertSame(
            [
                [
                    'Headers',
                    [
                        ['Request Headers', ['Accept' => 'text/html'], true],
                        ['Response Headers', ['Content-Type' => 'text/html'], true],
                    ],
                ],
                [
                    'Session',
                    [
                        ['Session', ['user' => 1], true],
                        ['Flashes', ['notice' => 'Saved'], false],
                    ],
                ],
                ['Server', [['Server', ['HTTP_HOST' => 'localhost'], true]]],
            ],
            array_map(
                static fn($tab): array => [
                    $tab->label,
                    array_map(
                        static fn($section): array => [$section->caption, $section->entries, $section->filterable],
                        $tab->sections,
                    ),
                ],
                array_slice($view->tabs, 1),
            ),
            'Header, Session, and Server tabs must retain every section and its filtering metadata.',
        );
    }

    public function testFromPanelDataRoutingSectionAlwaysHasThreeEntries(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            [],
            null,
        );

        $firstTab = $view->tabs[0] ?? null;

        self::assertNotNull(
            $firstTab,
            'Parameters tab must always be present.',
        );

        $routing = $firstTab->sections[0] ?? null;

        self::assertNotNull(
            $routing,
            'Parameters tab must contain the Routing section.',
        );

        self::assertSame(
            'Routing',
            $routing->caption,
            'First parameters section must be the Routing block.',
        );
        self::assertSame(
            ['Route', 'Action', 'Parameters'],
            array_keys($routing->entries),
            'Routing keys must follow Route/Action/Parameters.',
        );
    }

    public function testFromPanelDataSurfacesIpTimeAndDurationFromSummary(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            [],
            self::summary(['ip' => '127.0.0.1', 'time' => 1_704_112_496.0, 'processingTime' => 0.0125]),
        );

        self::assertSame(
            '127.0.0.1',
            $view->hero->ip,
            'Summary ip must surface on the hero meta strip.',
        );
        self::assertMatchesRegularExpression(
            '/^\d{2}:\d{2}:\d{2}$/',
            $view->hero->time,
            "Time must format as 'HH:MM:SS'.",
        );
        self::assertSame(
            '12.5 ms',
            $view->hero->durationMs,
            "Duration must format as 'X.X ms'.",
        );
    }

    public function testFromPanelDataSurfacesRequestBodyEntries(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            ['requestBody' => ['payload' => 'value']],
            null,
        );

        $sections = $view->tabs[0]->sections ?? [];
        $bodySection = $sections[count($sections) - 1] ?? null;

        self::assertNotNull(
            $bodySection,
            'Parameters tab must end with a section.',
        );
        self::assertSame(
            'Request Body',
            $bodySection->caption,
            'The closing section must be the request body.',
        );
        self::assertSame(
            ['payload' => 'value'],
            $bodySection->entries,
            'Captured body entries must reach the section.',
        );
    }

    public function testFromPanelDataTreatsNonBoolFlagAsInactive(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            [
                'general' => [
                    'isAjax' => 1,
                    'isPjax' => 'yes',
                    'isFlash' => null,
                    'isSecureConnection' => true,
                ],
            ],
            null,
        );

        self::assertSame(
            ['HTTPS'],
            $view->hero->flags,
            "Only literal 'true' must enable a flag; truthy non-bools count as inactive.",
        );
    }

    public function testFromPanelDataUsesExactMillisecondConversionAndOmitsZeroTimestamp(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            [],
            self::summary(['time' => 0.0, 'processingTime' => 1.0]),
        );

        self::assertSame(
            '',
            $view->hero->time,
            'A zero capture timestamp must not render the Unix epoch.',
        );
        self::assertSame(
            '1000.0 ms',
            $view->hero->durationMs,
            'One second must convert to exactly one thousand milliseconds.',
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private static function summary(array $overrides = []): RequestSummary
    {
        return RequestSummary::fromArray(
            [
                'tag' => 'tag-1',
                'url' => 'https://example.test/',
                'ajax' => false,
                'method' => 'GET',
                'ip' => '127.0.0.1',
                'time' => 1_700_000_000.0,
                'statusCode' => 200,
                'sqlCount' => 0,
                'excessiveCallersCount' => 0,
                'mailCount' => 0,
                'mailFiles' => [],
                'processingTime' => null,
                'peakMemory' => null,
                ...$overrides,
            ],
        );
    }
}
