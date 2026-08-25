<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\PhpInfo;

use PHPForge\Debug\PhpInfo\{
    PhpInfoCompactModule,
    PhpInfoDataNormalizer,
    PhpInfoTile,
    PhpInfoTocEntry,
    PhpInfoToken,
    PhpInfoView,
};
use PHPForge\Debug\Tests\Provider\PhpInfoDataNormalizerProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;
use Xepozz\InternalMocker\MockerState;

/**
 * Unit tests for {@see PhpInfoDataNormalizer} covering the parsing of the raw {@see phpinfo()} HTML output, the
 * tile-kind classification (pill / path / token list) and the wrapping of module blocks into deep-linkable sections.
 *
 * {@see PhpInfoDataNormalizerProvider} for test case data providers.
 */
#[Group('phpinfo')]
final class PhpInfoDataNormalizerTest extends TestCase
{
    /**
     * @var list<string> Home-directory environment variables modified by the tests.
     */
    private const array HOME_ENVIRONMENT_VARIABLES = ['HOME', 'USERPROFILE'];

    /**
     * @var array{HOME: string|false, USERPROFILE: string|false} Original process environment values.
     */
    private array $processHomeEnvironment = [
        'HOME' => false,
        'USERPROFILE' => false,
    ];

    /**
     * @var array{
     *     HOME: array{defined: bool, value: mixed},
     *     USERPROFILE: array{defined: bool, value: mixed}
     * } Original `$_SERVER` state.
     */
    private array $serverHomeEnvironment = [
        'HOME' => ['defined' => false, 'value' => null],
        'USERPROFILE' => ['defined' => false, 'value' => null],
    ];

    public function testCaptureBuildsViewFromBufferedPhpInfoOutput(): void
    {
        MockerState::addCondition(
            'PHPForge\Debug\PhpInfo',
            'ob_start',
            [],
            true,
            true,
        );
        MockerState::addCondition(
            'PHPForge\Debug\PhpInfo',
            'phpinfo',
            [],
            true,
            true,
        );
        MockerState::addCondition(
            'PHPForge\Debug\PhpInfo',
            'ob_get_clean',
            [],
            '<html><body><table><tr><td>Server API</td><td>cli</td></tr></table></body></html>',
            true,
        );

        $view = PhpInfoDataNormalizer::capture();

        $tile = $this->findTileByLabel($view, 'Server API');

        self::assertNotNull(
            $tile,
            "'Server API' tile must surface from the buffered body.",
        );
        self::assertSame(
            'cli',
            $tile->displayValue,
            'Tile value must come from the parsed body.',
        );

        $osTile = $this->findTileByLabel($view, 'OS');

        self::assertNotNull(
            $osTile,
            'Capture must surface the runtime OS tile.',
        );
        self::assertSame(
            php_uname('s') . ' ' . php_uname('r'),
            $osTile->displayValue,
            'The OS tile must separate the operating-system name and release with one space.',
        );
        self::assertCount(
            1,
            MockerState::getTraces('PHPForge\Debug\PhpInfo', 'ob_start'),
            'Capture must start exactly one output buffer.',
        );
        self::assertCount(
            1,
            MockerState::getTraces('PHPForge\Debug\PhpInfo', 'phpinfo'),
            'Capture must invoke phpinfo exactly once.',
        );
    }

    public function testCaptureFallsBackToEmptyBodyWhenOutputBufferingFails(): void
    {
        MockerState::addCondition(
            'PHPForge\Debug\PhpInfo',
            'ob_start',
            [],
            true,
            true,
        );
        MockerState::addCondition(
            'PHPForge\Debug\PhpInfo',
            'phpinfo',
            [],
            true,
            true,
        );
        MockerState::addCondition(
            'PHPForge\Debug\PhpInfo',
            'ob_get_clean',
            [],
            false,
            true,
        );

        $view = PhpInfoDataNormalizer::capture();

        self::assertSame(
            '',
            $view->modulesHtml,
            'Empty body must produce no module markup.',
        );
        self::assertNotEmpty(
            $view->sections,
            'Runtime metrics must still build the hero sections.',
        );
    }

    public function testFromOutputBuildsHeroSectionWithVersionHeadline(): void
    {
        $body = '<table><tr><td>Server API</td><td>cli</td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            '8.5.3',
            'cli',
            'Linux',
            '128M',
        );

        $section = $view->sections[0] ?? null;

        self::assertNotNull(
            $section,
            'Hero sections must be present.',
        );
        self::assertSame(
            'PHP version',
            $section->eyebrow,
            "First section must be the 'PHP' version hero.",
        );
        self::assertSame(
            '8.5.3',
            $section->headline,
            "Headline must echo the active 'PHP_VERSION'.",
        );
    }

    public function testFromOutputClassifiesAndEnhancesModuleTables(): void
    {
        $body = <<<HTML
        <h2>curl</h2>
        <table>
        <tr><td class="e">cURL support</td><td class="v">enabled</td></tr>
        <tr><td class="e">Features</td></tr>
        <tr><td class="e">HTTP2</td><td class="v">Yes</td></tr>
        </table>
        <table>
        <tr class="h"><th>Directive</th><th>Local Value</th><th>Master Value</th></tr>
        <tr><td class="e">curl.cainfo</td><td class="v"><i>no value</i></td><td class="v"><i>no value</i></td></tr>
        </table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertSame(
            <<<HTML
            <section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="phpinfo-curl" data-section="curl"><header class="yii-debug-phpinfo-module-head"><h2 id="phpinfo-curl-heading">curl</h2></header>
            <div class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-facts"><header class="yii-debug-phpinfo-table-section-head"><span>Module information</span><span class="yii-debug-phpinfo-table-section-count">2 values</span></header><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Module information" class="yii-debug-table is-facts">
            <tr class="yii-debug-phpinfo-fact"><th scope="row" class="e">cURL support</th><td class="v"><span class="yii-debug-phpinfo-status-pill" data-variant="success">enabled</span></td></tr>
            <tr class="yii-debug-phpinfo-fact-subheading"><th colspan="2">Features</th></tr>
            <tr class="yii-debug-phpinfo-fact"><th scope="row" class="e">HTTP2</th><td class="v"><span class="yii-debug-phpinfo-status-pill" data-variant="success">Yes</span></td></tr>
            </table></div></div>
            <div class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-directives"><header class="yii-debug-phpinfo-table-section-head"><span>Configuration directives</span><span class="yii-debug-phpinfo-table-section-count">1 directive</span></header><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Configuration directives" class="yii-debug-table is-directives">
            <tr class="h"><th>Directive</th><th>Local Value</th><th>Master Value</th></tr>
            <tr><th scope="row" class="e">curl.cainfo</th><td class="v"><i>no value</i></td><td class="v"><i>no value</i></td></tr>
            </table></div></div></section>
            HTML,
            $view->modulesHtml,
            'Two-column module metadata must use the compact facts presentation.',
        );
    }

    public function testFromOutputClassifiesDisabledAsMutedPill(): void
    {
        $body = '<table><tr><td>Debug Build</td><td>disabled</td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        $tile = $this->findTileByLabel($view, 'Debug Build');

        self::assertNotNull(
            $tile,
            'Debug Build tile must surface.',
        );
        self::assertSame(
            PhpInfoTile::KIND_PILL_MUTED,
            $tile->kind,
            "'disabled' values must classify as the muted pill.",
        );
    }

    public function testFromOutputClassifiesEnabledAsSuccessPill(): void
    {
        $body = '<table><tr><td>IPv6 Support</td><td>enabled</td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        $capabilitiesSection = null;

        foreach ($view->sections as $section) {
            if ($section->eyebrow === 'Capabilities') {
                $capabilitiesSection = $section;
            }
        }

        self::assertNotNull(
            $capabilitiesSection,
            'Capabilities section must surface.',
        );

        $ipv6Tile = null;

        foreach ($capabilitiesSection->tiles as $tile) {
            if ($tile->label === 'IPv6 Support') {
                $ipv6Tile = $tile;
            }
        }

        self::assertNotNull(
            $ipv6Tile,
            'IPv6 tile must be present.',
        );
        self::assertSame(
            PhpInfoTile::KIND_PILL_SUCCESS,
            $ipv6Tile->kind,
            "'enabled' must classify as the success pill.",
        );
    }

    public function testFromOutputClassifiesPathListWithBasenameTokens(): void
    {
        $body = '<table><tr><td>Additional .ini files parsed</td><td>/etc/php/apcu.ini, /etc/php/oci.ini</td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        $configSection = null;

        foreach ($view->sections as $section) {
            if ($section->eyebrow === 'Configuration') {
                $configSection = $section;
            }
        }

        self::assertNotNull(
            $configSection,
            'Configuration section must surface.',
        );

        $tile = $configSection->tiles[0] ?? null;

        self::assertNotNull(
            $tile,
            'Tile must surface for the parsed entry.',
        );
        self::assertSame(
            PhpInfoTile::KIND_PATH_LIST,
            $tile->kind,
            "Path list with comma + leading '/' must classify as KIND_PATH_LIST.",
        );
        self::assertCount(
            2,
            $tile->tokens,
            'Path list must produce one token per entry.',
        );

        $token = $tile->tokens[0] ?? null;

        self::assertNotNull(
            $token,
            'First token must exist.',
        );
        self::assertSame(
            'apcu.ini',
            $token->label,
            'Basename must surface as the token label.',
        );
        self::assertSame(
            '/etc/php/apcu.ini',
            $token->title,
            'Full path must survive in the token title.',
        );
    }

    public function testFromOutputClassifiesTokenListWithShortCommaSeparatedValues(): void
    {
        $body = '<table><tr><td>Registered PHP Streams</td><td>https,ftps,ssh2</td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        $tile = $this->findTileByLabel($view, 'Registered PHP Streams');

        self::assertNotNull(
            $tile,
            'Registered PHP Streams tile must surface.',
        );
        self::assertSame(
            PhpInfoTile::KIND_TOKEN_LIST,
            $tile->kind,
            'Comma-separated short tokens (≤32 chars, no whitespace) must classify as KIND_TOKEN_LIST.',
        );
        self::assertCount(
            3,
            $tile->tokens,
            'Token list must produce one token per comma-separated entry.',
        );
    }

    public function testFromOutputCompactsExactlyThreeTrimmedTokens(): void
    {
        $body = <<<'HTML'
        <h2>calendar</h2>
        <table><tr><td class="e"> Calendar backends </td><td class="v"> mysql, pgsql, sqlite </td></tr></table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertSame(
            [
                [
                    'calendar',
                    [
                        ['Calendar backends', 'mysql, pgsql, sqlite', PhpInfoTile::KIND_TOKEN_LIST, 3],
                    ],
                ],
            ],
            array_map(
                static fn(PhpInfoCompactModule $module): array => [
                    $module->title,
                    array_map(
                        static fn(PhpInfoTile $tile): array => [
                            $tile->label,
                            $tile->displayValue,
                            $tile->kind,
                            count($tile->tokens),
                        ],
                        $module->tiles,
                    ),
                ],
                $view->compactModules,
            ),
            'A three-token fact must remain eligible for the compact Overview summary.',
        );
    }

    public function testFromOutputCountsFactRowsWithHeaderLookalikeClassAttributes(): void
    {
        $body = <<<HTML
        <h2>acme</h2>
        <table>
        <tr><td>Vendor</td><td>Acme</td></tr>
        <tr class="a h b"><td>Branding</td><td>Custom</td></tr>
        <tr><td>Version</td><td>1.2</td></tr>
        </table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertSame(
            <<<HTML
            <section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="phpinfo-acme" data-section="acme"><header class="yii-debug-phpinfo-module-head"><h2 id="phpinfo-acme-heading">acme</h2></header>
            <div class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-facts"><header class="yii-debug-phpinfo-table-section-head"><span>Module information</span><span class="yii-debug-phpinfo-table-section-count">3 values</span></header><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Module information" class="yii-debug-table is-facts">
            <tr class="yii-debug-phpinfo-fact"><td>Vendor</td><td>Acme</td></tr>
            <tr class="yii-debug-phpinfo-fact"><td>Branding</td><td>Custom</td></tr>
            <tr class="yii-debug-phpinfo-fact"><td>Version</td><td>1.2</td></tr>
            </table></div></div></section>
            HTML,
            $view->modulesHtml,
            "The value badge must count every fact row, header lookalikes included: '3 values'.",
        );
    }

    public function testFromOutputDoesNotRedactOrdinaryModuleDirectives(): void
    {
        $body = <<<HTML
        <h2>session</h2>
        <table>
        <tr class="h"><th>Directive</th><th>Local Value</th><th>Master Value</th></tr>
        <tr><td class="e">session.cookie_path</td><td class="v">/</td><td class="v">/</td></tr>
        </table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertSame(
            <<<HTML
            <section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="phpinfo-session" data-section="session"><header class="yii-debug-phpinfo-module-head"><h2 id="phpinfo-session-heading">session</h2></header>
            <div class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-directives"><header class="yii-debug-phpinfo-table-section-head"><span>Configuration directives</span><span class="yii-debug-phpinfo-table-section-count">1 directive</span></header><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Configuration directives" class="yii-debug-table is-directives">
            <tr class="h"><th>Directive</th><th>Local Value</th><th>Master Value</th></tr>
            <tr><th scope="row" class="e">session.cookie_path</th><td class="v">/</td><td class="v">/</td></tr>
            </table></div></div></section>
            HTML,
            $view->modulesHtml,
            'Sensitive-name detection must not hide ordinary lowercase PHP directives.',
        );

    }

    public function testFromOutputDoesNotRedactTokenizerSupport(): void
    {
        $body = <<<HTML
        <h2>tokenizer</h2>
        <table><tr><td class="e">Tokenizer Support</td><td class="v">enabled</td></tr></table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        $tile = $view->compactModules[0]->tiles[0] ?? null;

        self::assertNotNull(
            $tile,
            'Tokenizer must be summarized as a compact module.',
        );
        self::assertSame(
            'enabled',
            $tile->displayValue,
            'Module labels containing TOKEN must not trigger redaction.',
        );
    }

    public function testFromOutputDowngradesTokenListToTextWhenTokenContainsWhitespace(): void
    {
        $body = '<table><tr><td>Registered PHP Streams</td><td>https,ftp with space,ssh2</td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        $tile = $this->findTileByLabel($view, 'Registered PHP Streams');

        self::assertNotNull(
            $tile,
            'Registered PHP Streams tile must surface.',
        );
        self::assertSame(
            PhpInfoTile::KIND_TEXT,
            $tile->kind,
            "Token-list candidates with whitespace inside an entry must fall back to 'KIND_TEXT'.",
        );
    }

    public function testFromOutputDropsEmptyTokenListEntriesAndReindexesTokens(): void
    {
        $body = '<table><tr><td>Registered PHP Streams</td><td>https, ,ftps</td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        $tile = $this->findTileByLabel($view, 'Registered PHP Streams');

        self::assertNotNull(
            $tile,
            'Registered PHP Streams tile must surface.',
        );
        self::assertSame(
            PhpInfoTile::KIND_TOKEN_LIST,
            $tile->kind,
            "Short tokens around a blank entry must classify as 'KIND_TOKEN_LIST'.",
        );
        self::assertSame(
            [
                0 => ['https', ''],
                1 => ['ftps', ''],
            ],
            array_map(
                static fn(PhpInfoToken $token): array => [$token->label, $token->title],
                $tile->tokens,
            ),
            'Tokens must be typed DTOs reindexed from zero.',
        );
    }

    public function testFromOutputExtractsConfigureCommand(): void
    {
        $body = '<table><tr><td>Configure Command</td><td> ./configure --foo=bar </td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            ''
        );

        self::assertSame(
            './configure --foo=bar',
            $view->configureCommand,
            'Configure Command must surface verbatim.',
        );
    }

    public function testFromOutputGroupsPhpVariablesBySource(): void
    {
        $body = <<<'HTML'
        <h2>PHP Variables</h2>
        <table>
        <tr class="H"><th>Variable</th><th>Value</th></tr>
        <tr><td class="e">$_REQUEST['page']</td><td class="v">1</td></tr>
        <tr><td class="e">$_COOKIE['theme']</td><td class="v">dark</td></tr>
        <tr><td class="e">$_SERVER['REQUEST_METHOD']</td><td class="v">GET</td></tr>
        <tr><td class="e">APP_ENV</td><td class="v">dev</td></tr>
        </table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertSame(
            4,
            substr_count($view->modulesHtml, 'data-yii-debug-phpinfo-collapsible="true"'),
            'PHP Variables must render one collapsible table per populated source group.',
        );
        self::assertMatchesRegularExpression(
            '~<summary[^>]*>.*?<span>Request</span>.*?</summary>.*?\$_REQUEST~s',
            $view->modulesHtml,
            'Request superglobals must render in the Request group.',
        );
        self::assertMatchesRegularExpression(
            '~<summary[^>]*>.*?<span>Cookies</span>.*?</summary>.*?\$_COOKIE~s',
            $view->modulesHtml,
            'Cookie variables must render in the Cookies group.',
        );
        self::assertMatchesRegularExpression(
            '~<summary[^>]*>.*?<span>Server</span>.*?</summary>.*?\$_SERVER~s',
            $view->modulesHtml,
            'Server variables must render in the Server group.',
        );
        self::assertMatchesRegularExpression(
            '~<summary[^>]*>.*?<span>Environment</span>.*?</summary>.*?APP_ENV~s',
            $view->modulesHtml,
            'Environment variables must render in the Environment group.',
        );
        self::assertSame(
            1,
            substr_count($view->modulesHtml, 'data-yii-debug-phpinfo-default-open="true"'),
            'Only the first populated variable group must be expanded initially.',
        );
        self::assertSame(
            <<<'HTML'
            <section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="phpinfo-php-variables" data-section="PHP Variables"><header class="yii-debug-phpinfo-module-head"><h2 id="phpinfo-php-variables-heading">PHP Variables</h2></header>
            <details class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-data yii-debug-phpinfo-variable-group" data-yii-debug-phpinfo-collapsible="true" data-yii-debug-phpinfo-default-open="true" open><summary class="yii-debug-phpinfo-table-section-head"><span>Request</span><span class="yii-debug-phpinfo-table-section-count">1 row</span></summary><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Request" class="yii-debug-table is-data"><tr class="H"><th>Variable</th><th>Value</th></tr><tr><th scope="row" class="e">$_REQUEST['page']</th><td class="v">1</td></tr></table></div></details><details class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-data yii-debug-phpinfo-variable-group" data-yii-debug-phpinfo-collapsible="true" data-yii-debug-phpinfo-default-open="false"><summary class="yii-debug-phpinfo-table-section-head"><span>Cookies</span><span class="yii-debug-phpinfo-table-section-count">1 row</span></summary><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Cookies" class="yii-debug-table is-data"><tr class="H"><th>Variable</th><th>Value</th></tr><tr><th scope="row" class="e">$_COOKIE['theme']</th><td  class="v"><span class="yii-debug-phpinfo-redacted" aria-label="Sensitive value hidden">redacted</span></td></tr></table></div></details><details class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-data yii-debug-phpinfo-variable-group" data-yii-debug-phpinfo-collapsible="true" data-yii-debug-phpinfo-default-open="false"><summary class="yii-debug-phpinfo-table-section-head"><span>Server</span><span class="yii-debug-phpinfo-table-section-count">1 row</span></summary><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Server" class="yii-debug-table is-data"><tr class="H"><th>Variable</th><th>Value</th></tr><tr><th scope="row" class="e">$_SERVER['REQUEST_METHOD']</th><td class="v">GET</td></tr></table></div></details><details class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-data yii-debug-phpinfo-variable-group" data-yii-debug-phpinfo-collapsible="true" data-yii-debug-phpinfo-default-open="false"><summary class="yii-debug-phpinfo-table-section-head"><span>Environment</span><span class="yii-debug-phpinfo-table-section-count">1 row</span></summary><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Environment" class="yii-debug-table is-data"><tr class="H"><th>Variable</th><th>Value</th></tr><tr><th scope="row" class="e">APP_ENV</th><td class="v">dev</td></tr></table></div></details></section>
            HTML,
            $view->modulesHtml,
            'The first variable group must use an open details disclosure.',
        );
        self::assertLessThan(
            strpos($view->modulesHtml, '<span>Cookies</span>'),
            strpos($view->modulesHtml, '<span>Request</span>'),
            'Request must remain the first variable group when populated.',
        );
        self::assertMatchesRegularExpression(
            '~<table[^>]*><tr class="H">.*?</tr><tr>.*?\$_REQUEST~s',
            $view->modulesHtml,
            'Each grouped table must retain its header before its data rows.',
        );
    }

    public function testFromOutputIgnoresColspanSubheadingsWhenSummarizingAModule(): void
    {
        // `php_info_print_table_colspan_header()` emits a single-cell row inside an otherwise two-column facts table.
        $body = <<<HTML
        <h2>ftp</h2>
        <table>
        <tr><td class="e">FTP support</td><td class="v">enabled</td></tr>
        <tr><td class="e">Features</td></tr>
        <tr><td class="e">FTPS support</td><td class="v">enabled</td></tr>
        </table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        $module = $view->compactModules[0] ?? null;

        self::assertNotNull(
            $module,
            'A subheading must not stop the module from being summarized.',
        );
        self::assertSame(
            ['FTP support', 'FTPS support'],
            array_map(static fn(PhpInfoTile $tile): string => $tile->label, $module->tiles),
            'Only the two-cell rows may become tiles.',
        );
    }

    public function testFromOutputIgnoresPosixHomeFallbackWhenPasswordEntryLookupFails(): void
    {
        unset($_SERVER['HOME'], $_SERVER['USERPROFILE']);

        putenv('HOME');
        putenv('USERPROFILE');

        MockerState::addCondition(
            'PHPForge\Debug\PhpInfo',
            'function_exists',
            ['posix_getpwuid'],
            true,
        );
        MockerState::addCondition(
            'PHPForge\Debug\PhpInfo',
            'function_exists',
            ['posix_getuid'],
            true,
        );
        MockerState::addCondition(
            'PHPForge\Debug\PhpInfo',
            'posix_getuid',
            [],
            1000,
        );
        MockerState::addCondition(
            'PHPForge\Debug\PhpInfo',
            'posix_getpwuid',
            [1000],
            false,
        );

        $view = PhpInfoDataNormalizer::fromOutput(
            '<table><tr><td>Loaded Configuration File</td><td>/home/example/php.ini</td></tr></table>',
            'x',
            'cli',
            'Linux',
            '',
        );

        self::assertSame(
            '/home/example/php.ini',
            $this->findTileByLabel($view, 'Loaded Configuration File')?->displayValue,
            'A failed password-entry lookup must leave the path unshortened.',
        );
    }

    public function testFromOutputKeepsAbsolutePathVerbatimWhenHomeNotResolved(): void
    {
        $body = '<table><tr><td>Loaded Configuration File</td><td>/etc/php/cli/php.ini</td></tr></table>';

        unset($_SERVER['HOME'], $_SERVER['USERPROFILE']);

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        $tile = $this->findTileByLabel($view, 'Loaded Configuration File');

        self::assertNotNull(
            $tile,
            'Loaded Configuration File tile must surface.',
        );
        self::assertSame(
            '/etc/php/cli/php.ini',
            $tile->displayValue,
            'Paths outside the resolved home directory must surface verbatim.',
        );
    }

    public function testFromOutputKeepsLongValueListsInStandaloneModules(): void
    {
        $body = <<<HTML
        <h2>PDO</h2>
        <table>
        <tr><td class="e">PDO support</td><td class="v">enabled</td></tr>
        <tr><td class="e">PDO drivers</td><td class="v">mysql, pgsql, sqlite, oci, sqlsrv</td></tr>
        </table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertSame(
            [],
            $view->compactModules,
            'Capability lists must not be compressed into a small card.',
        );
        self::assertSame(
            <<<HTML
            <section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="phpinfo-pdo" data-section="PDO"><header class="yii-debug-phpinfo-module-head"><h2 id="phpinfo-pdo-heading">PDO</h2></header>
            <div class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-facts"><header class="yii-debug-phpinfo-table-section-head"><span>Module information</span><span class="yii-debug-phpinfo-table-section-count">2 values</span></header><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Module information" class="yii-debug-table is-facts">
            <tr class="yii-debug-phpinfo-fact"><th scope="row" class="e">PDO support</th><td class="v"><span class="yii-debug-phpinfo-status-pill" data-variant="success">enabled</span></td></tr>
            <tr class="yii-debug-phpinfo-fact"><th scope="row" class="e">PDO drivers</th><td class="v">mysql, pgsql, sqlite, oci, sqlsrv</td></tr>
            </table></div></div></section>
            HTML,
            $view->modulesHtml,
            'PDO driver information must retain its own panel.',
        );
    }

    public function testFromOutputKeepsModuleStandaloneWhenAFactValueIsEmpty(): void
    {
        $body = '<h2>example</h2><table><tr><td class="e">Statistics</td><td class="v"></td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertSame(
            [],
            $view->compactModules,
            'A blank value must block the Overview summary.',
        );
        self::assertSame(
            <<<'HTML'
        <section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="phpinfo-example" data-section="example"><header class="yii-debug-phpinfo-module-head"><h2 id="phpinfo-example-heading">example</h2></header><div class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-facts"><header class="yii-debug-phpinfo-table-section-head"><span>Module information</span><span class="yii-debug-phpinfo-table-section-count">1 value</span></header><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Module information" class="yii-debug-table is-facts"><tr class="yii-debug-phpinfo-fact"><th scope="row" class="e">Statistics</th><td class="v"></td></tr></table></div></div></section>
        HTML,
            $view->modulesHtml,
            'The module must keep its own section instead.',
        );
    }

    public function testFromOutputKeepsOverviewBoundariesAndDropsEmptySections(): void
    {
        $body = <<<HTML
        <tr><td> Build Date </td><td> overview build </td></tr>
        <h2>example</h2>
        <table>
        <tr><td class="e">Build Date</td><td class="v">module build</td></tr>
        <tr><td class="e">Second</td><td class="v">value</td></tr>
        <tr><td class="e">Third</td><td class="v">value</td></tr>
        </table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', '', '', '');

        self::assertSame(
            ['PHP version', 'Build'],
            array_map(static fn($section): string => $section->eyebrow, $view->sections),
            'Only the headline section and populated Build section may remain.',
        );
        self::assertSame(
            'overview build',
            $this->findTileByLabel($view, 'Build Date')?->displayValue,
            'Overview parsing must stop before module rows and trim both key and value.',
        );
    }

    public function testFromOutputKeepsOverviewHeadingOutOfModuleParsing(): void
    {
        $body = <<<HTML
        <h1>Overview</h1>
        <table>
        <tr><td>Build Date</td><td>overview build</td></tr>
        <tr><td>Compiler</td><td>overview compiler</td></tr>
        <tr><td>Architecture</td><td>x86_64</td></tr>
        <tr><td>Server API</td><td>cli</td></tr>
        </table>
        <h2>example</h2>
        <table>
        <tr><td class="e">First</td><td class="v">one</td></tr>
        <tr><td class="e">Second</td><td class="v">two</td></tr>
        <tr><td class="e">Third</td><td class="v">three</td></tr>
        <tr><td class="e">Fourth</td><td class="v">four</td></tr>
        </table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertSame(
            ['Overview', 'example'],
            array_map(static fn(PhpInfoTocEntry $entry): string => $entry->title, $view->tocEntries),
            'The overview heading must not create a duplicate module navigation entry.',
        );
        self::assertSame(
            <<<HTML
            <section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="phpinfo-example" data-section="example"><header class="yii-debug-phpinfo-module-head"><h2 id="phpinfo-example-heading">example</h2></header>
            <div class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-facts"><header class="yii-debug-phpinfo-table-section-head"><span>Module information</span><span class="yii-debug-phpinfo-table-section-count">4 values</span></header><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Module information" class="yii-debug-table is-facts">
            <tr class="yii-debug-phpinfo-fact"><th scope="row" class="e">First</th><td class="v">one</td></tr>
            <tr class="yii-debug-phpinfo-fact"><th scope="row" class="e">Second</th><td class="v">two</td></tr>
            <tr class="yii-debug-phpinfo-fact"><th scope="row" class="e">Third</th><td class="v">three</td></tr>
            <tr class="yii-debug-phpinfo-fact"><th scope="row" class="e">Fourth</th><td class="v">four</td></tr>
            </table></div></div></section>
            HTML,
            $view->modulesHtml,
            'The overview prefix must not be rendered again as a detailed module.',
        );
        self::assertSame(
            'overview build',
            $this->findTileByLabel($view, 'Build Date')?->displayValue,
            'Overview rows must remain available to the overview section.',
        );
    }

    #[DataProviderExternal(PhpInfoDataNormalizerProvider::class, 'dataTableHeads')]
    public function testFromOutputLabelsDataTablesByTheirLeadingHeader(string $headers, string $expectedLabel): void
    {
        $body = <<<HTML
        <h2>example</h2>
        <table>
        <tr class="h">{$headers}</tr>
        <tr><td class="e">first</td><td class="v">second</td></tr>
        </table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertSame(
            <<<HTML
            <section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="phpinfo-example" data-section="example"><header class="yii-debug-phpinfo-module-head"><h2 id="phpinfo-example-heading">example</h2></header>
            <div class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-data"><header class="yii-debug-phpinfo-table-section-head"><span>{$expectedLabel}</span><span class="yii-debug-phpinfo-table-section-count">1 row</span></header><div class="yii-debug-phpinfo-table-scroll"><table aria-label="{$expectedLabel}" class="yii-debug-table is-data">
            <tr class="h">{$headers}</tr>
            <tr><th scope="row" class="e">first</th><td class="v">second</td></tr>
            </table></div></div></section>
            HTML,
            $view->modulesHtml,
            'Data tables must render the exact labeled module markup.',
        );
    }

    public function testFromOutputLabelsGenericSingleColumnTablesAsNotes(): void
    {
        $body = '<h2>example</h2><table><tr><td>License text</td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertSame(
            <<<HTML
            <section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="phpinfo-example" data-section="example"><header class="yii-debug-phpinfo-module-head"><h2 id="phpinfo-example-heading">example</h2></header><div class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-note"><header class="yii-debug-phpinfo-table-section-head"><span>Notes</span><span class="yii-debug-phpinfo-table-section-count">1 note</span></header><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Notes" class="yii-debug-table is-note"><tr><td>License text</td></tr></table></div></div></section>
            HTML,
            $view->modulesHtml,
            'A single-column table without a native caption must use the Notes label.',
        );
    }

    public function testFromOutputLeavesConfigureCommandUntouchedWithoutHome(): void
    {
        unset($_SERVER['HOME'], $_SERVER['USERPROFILE']);

        putenv('HOME');
        putenv('USERPROFILE');

        MockerState::addCondition('PHPForge\Debug\PhpInfo', 'function_exists', [], false, true);

        $view = PhpInfoDataNormalizer::fromOutput(
            '<table><tr><td>Configure Command</td><td>/etc/php/configure</td></tr></table>',
            'x',
            'cli',
            'Linux',
            '',
        );

        self::assertSame(
            '/etc/php/configure',
            $view->configureCommand,
            'An empty home directory must leave absolute configure-command paths untouched.',
        );
    }

    public function testFromOutputMarksLongFactValuesAsWide(): void
    {
        $long = str_repeat('a', 73);

        // A fourth fact keeps the module out of the Overview summary, so the rows reach the fact-row normalizer.
        $body = <<<HTML
        <h2>example</h2>
        <table>
        <tr><td class="e">Short</td><td class="v">brief</td></tr>
        <tr><td class="e">Another</td><td class="v">value</td></tr>
        <tr><td class="e">Third</td><td class="v">value</td></tr>
        <tr><td class="e">Long</td><td class="v">{$long}</td></tr>
        </table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertSame(
            <<<HTML
            <section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="phpinfo-example" data-section="example"><header class="yii-debug-phpinfo-module-head"><h2 id="phpinfo-example-heading">example</h2></header>
            <div class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-facts"><header class="yii-debug-phpinfo-table-section-head"><span>Module information</span><span class="yii-debug-phpinfo-table-section-count">4 values</span></header><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Module information" class="yii-debug-table is-facts">
            <tr class="yii-debug-phpinfo-fact"><th scope="row" class="e">Short</th><td class="v">brief</td></tr>
            <tr class="yii-debug-phpinfo-fact"><th scope="row" class="e">Another</th><td class="v">value</td></tr>
            <tr class="yii-debug-phpinfo-fact"><th scope="row" class="e">Third</th><td class="v">value</td></tr>
            <tr class="yii-debug-phpinfo-fact yii-debug-phpinfo-fact-wide"><th scope="row" class="e">Long</th><td class="v">aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa</td></tr>
            </table></div></div></section>
            HTML,
            $view->modulesHtml,
            'Values beyond 72 characters must claim the full row.',
        );

    }

    public function testFromOutputNormalizesFactRowWhitespaceAttributesAndUnicodeWidth(): void
    {
        $unicodeValue = str_repeat('é', 40);
        $boundaryValue = str_repeat('a', 72);

        $body = <<<HTML
        <h2>example</h2>
        <table>
        <tr   data-source="fixture" class="legacy"   ><td class="e">  <strong>Heading</strong>  </td></tr>
        <tr><td class="e">First</td><td class="v">one</td></tr>
        <tr><td class="e">Second</td><td class="other">enabled</td></tr>
        <tr><td class="e">Status</td><td class="V"> ENABLED </td></tr>
        <tr><td class="e">Unicode</td><td class="v">{$unicodeValue}</td></tr>
        <tr><td class="e">Boundary</td><td class="v">{$boundaryValue}</td></tr>
        </table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertSame(
            <<<HTML
            <section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="phpinfo-example" data-section="example"><header class="yii-debug-phpinfo-module-head"><h2 id="phpinfo-example-heading">example</h2></header>
            <div class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-facts"><header class="yii-debug-phpinfo-table-section-head"><span>Module information</span><span class="yii-debug-phpinfo-table-section-count">5 values</span></header><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Module information" class="yii-debug-table is-facts">
            <tr data-source="fixture" class="yii-debug-phpinfo-fact-subheading"><th colspan="2"><strong>Heading</strong></th></tr>
            <tr class="yii-debug-phpinfo-fact"><th scope="row" class="e">First</th><td class="v">one</td></tr>
            <tr class="yii-debug-phpinfo-fact"><th scope="row" class="e">Second</th><td class="other">enabled</td></tr>
            <tr class="yii-debug-phpinfo-fact"><th scope="row" class="e">Status</th><td class="V"><span class="yii-debug-phpinfo-status-pill" data-variant="success">ENABLED</span></td></tr>
            <tr class="yii-debug-phpinfo-fact"><th scope="row" class="e">Unicode</th><td class="v">éééééééééééééééééééééééééééééééééééééééé</td></tr>
            <tr class="yii-debug-phpinfo-fact"><th scope="row" class="e">Boundary</th><td class="v">aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa</td></tr>
            </table></div></div></section>
            HTML,
            $view->modulesHtml,
            'Fact subheadings must trim content and normalize row attributes without extra whitespace.',
        );

        self::assertSame(
            1,
            substr_count($view->modulesHtml, 'yii-debug-phpinfo-status-pill'),
            'Only class-v status cells may become status pills.',
        );
    }

    public function testFromOutputOmitsModulesWithoutContentRows(): void
    {
        $body = <<<HTML
        <h2>Additional Modules</h2>
        <table><tr class="h"><th>Module Name</th></tr></table>
        <h2>Core</h2>
        <table>
        <tr><td>Version</td><td>8.5</td></tr>
        <tr><td>Debug</td><td>disabled</td></tr>
        <tr><td>Thread Safety</td><td>disabled</td></tr>
        </table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertSame(
            ['Overview', 'Core'],
            array_map(static fn(PhpInfoTocEntry $entry): string => $entry->title, $view->tocEntries),
            'A title-only phpinfo table must not create an empty navigation destination.',
        );
        self::assertSame(
            <<<HTML
            <section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="phpinfo-core" data-section="Core"><header class="yii-debug-phpinfo-module-head"><h2 id="phpinfo-core-heading">Core</h2></header>
            <div class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-facts"><header class="yii-debug-phpinfo-table-section-head"><span>Module information</span><span class="yii-debug-phpinfo-table-section-count">3 values</span></header><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Module information" class="yii-debug-table is-facts">
            <tr class="yii-debug-phpinfo-fact"><td>Version</td><td>8.5</td></tr>
            <tr class="yii-debug-phpinfo-fact"><td>Debug</td><td>disabled</td></tr>
            <tr class="yii-debug-phpinfo-fact"><td>Thread Safety</td><td>disabled</td></tr>
            </table></div></div></section>
            HTML,
            $view->modulesHtml,
            'Empty modules must be omitted from the rendered content.',
        );
    }

    public function testFromOutputOmitsWhitespaceOnlyModuleTables(): void
    {
        $body = '<h2>Empty example</h2><table><tr><td>   </td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertSame(
            '',
            $view->modulesHtml,
            'Whitespace-only table cells must not create an empty module.',
        );
        self::assertSame(
            ['Overview'],
            array_map(static fn(PhpInfoTocEntry $entry): string => $entry->title, $view->tocEntries),
            'Whitespace-only modules must not create a TOC entry.',
        );
    }

    public function testFromOutputPreservesRedactedRowAttributes(): void
    {
        $body = <<<HTML
        <h2>Environment</h2>
        <table><tr data-source="fixture"><td class="e">DB_PASSWORD</td><td class="v">secret</td></tr></table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertMatchesRegularExpression(
            '~<tr data-source="fixture" class="yii-debug-phpinfo-fact"><th scope="row" class="e">DB_PASSWORD</th><td\s+class="v"><span class="yii-debug-phpinfo-redacted"~',
            $view->modulesHtml,
            'Redaction must preserve the source row and its attributes.',
        );
    }

    public function testFromOutputProducesTocEntryPerDetailedModuleH2(): void
    {
        $body = <<<HTML
        <h2>apcu</h2>
        <table><tr><td>Version</td><td>5.1.0</td></tr></table>
        <table>
        <tr class="h"><th>Directive</th><th>Local Value</th><th>Master Value</th></tr>
        <tr><td>apc.enabled</td><td>On</td><td>On</td></tr>
        </table>
        <h2>Core</h2>
        <table><tr><td>PHP Version</td><td>8.5</td></tr></table>
        <table>
        <tr class="h"><th>Directive</th><th>Local Value</th><th>Master Value</th></tr>
        <tr><td>memory_limit</td><td>128M</td><td>128M</td></tr>
        </table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        $titles = [];

        foreach ($view->tocEntries as $entry) {
            $titles[] = $entry->title;
        }

        self::assertSame(
            [
                'Overview',
                'apcu',
                'Core',
            ],
            $titles,
            "Every detailed '<h2>' module must produce a TOC entry.",
        );
    }

    public function testFromOutputProducesUniqueSlugsForTocEntries(): void
    {
        $body = <<<HTML
        <h2>apcu</h2>
        <table>
        <tr><td>Version</td><td>5.1</td></tr>
        <tr><td>Debug</td><td>disabled</td></tr>
        <tr><td>MMAP</td><td>enabled</td></tr>
        </table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        $slugs = [];

        foreach ($view->tocEntries as $entry) {
            $slugs[] = $entry->slug;
        }

        self::assertSame(
            [
                'phpinfo-overview',
                'phpinfo-apcu',
            ],
            $slugs,
            "Slugs must follow the 'phpinfo-<title>' convention."
        );
    }

    public function testFromOutputQuotesHomePathAndTrimsModuleSlug(): void
    {
        $originalHome = $_SERVER['HOME'] ?? null;
        $_SERVER['HOME'] = '/home/a.b';

        try {
            $view = PhpInfoDataNormalizer::fromOutput(
                '<table><tr><td>Configure Command</td><td>/home/axb/configure</td></tr></table>'
                . '<h2>--example--</h2><table><tr><td>First</td><td>one</td></tr><tr><td>Second</td><td>two</td></tr><tr><td>Third</td><td>three</td></tr></table>',
                'x',
                'cli',
                'Linux',
                '',
            );
        } finally {
            if ($originalHome === null) {
                unset($_SERVER['HOME']);
            } else {
                $_SERVER['HOME'] = $originalHome;
            }
        }

        self::assertSame(
            '/home/axb/configure',
            $view->configureCommand,
            'Regex characters in the home directory must be quoted before replacement.',
        );
        self::assertSame(
            ['phpinfo-overview', 'phpinfo-example'],
            array_map(static fn(PhpInfoTocEntry $entry): string => $entry->slug, $view->tocEntries),
            'Module slugs must trim separator runs from both ends.',
        );
    }

    public function testFromOutputReadsMultilineCaseInsensitiveHeadersAndCountsOnlyDataRows(): void
    {
        $body = <<<HTML
        <h2>example</h2>
        <table>
        <tr class="H">
        <th>Variable</th><th>Value</th>
        </tr>
        <tr><td class="e"><span class="h">first</span></td><td class="v">one</td></tr>
        <tr><td class="e">second</td><td class="v">two</td></tr>
        </table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertSame(
            <<<HTML
            <section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="phpinfo-example" data-section="example"><header class="yii-debug-phpinfo-module-head"><h2 id="phpinfo-example-heading">example</h2></header>
            <details class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-data yii-debug-phpinfo-variable-group" data-yii-debug-phpinfo-collapsible="true" data-yii-debug-phpinfo-default-open="true" open><summary class="yii-debug-phpinfo-table-section-head"><span>Other</span><span class="yii-debug-phpinfo-table-section-count">2 rows</span></summary><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Other" class="yii-debug-table is-data"><tr class="H">
            <th>Variable</th><th>Value</th>
            </tr><tr><th scope="row" class="e"><span class="h">first</span></th><td class="v">one</td></tr><tr><th scope="row" class="e">second</th><td class="v">two</td></tr></table></div></details></section>
            HTML,
            $view->modulesHtml,
            'Multiline uppercase variable headers must enable grouping and stay out of the data count.',
        );
    }

    public function testFromOutputRedactsSensitiveEnvironmentAndRuntimeVariables(): void
    {
        $body = <<<'HTML'
        <h2>PHP Variables</h2>
        <table>
        <tr class="h"><th>Variable</th><th>Value</th></tr>
        <tr><td class="e">$_COOKIE['XSRF-TOKEN']</td><td class="v">sensitive-cookie-value</td></tr>
        <tr><td class="e">APP_KEY</td><td class="v">sensitive-app-key</td></tr>
        <tr><td class="e">$_SERVER['PHP_AUTH_PW']</td><td class="v">sensitive-basic-auth-value</td></tr>
        <tr><td class="e">WEBHOOK_SIGNATURE</td><td class="v">sensitive-signature-value</td></tr>
        <tr><td class="e">database_url</td><td class="v">sensitive-database-url</td></tr>
        <tr><td class="e">PWD</td><td class="v">/srv/app</td></tr>
        <tr><td class="e">OLDPWD</td><td class="v">/srv</td></tr>
        <tr><td class="e">CHPWD_STATUS</td><td class="v">unchanged</td></tr>
        <tr><td class="e">APP_NAME</td><td class="v">Yii application</td></tr>
        </table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertSame(
            <<<'HTML'
            <section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="phpinfo-php-variables" data-section="PHP Variables"><header class="yii-debug-phpinfo-module-head"><h2 id="phpinfo-php-variables-heading">PHP Variables</h2></header>
            <details class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-data yii-debug-phpinfo-variable-group" data-yii-debug-phpinfo-collapsible="true" data-yii-debug-phpinfo-default-open="true" open><summary class="yii-debug-phpinfo-table-section-head"><span>Cookies</span><span class="yii-debug-phpinfo-table-section-count">1 row</span></summary><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Cookies" class="yii-debug-table is-data"><tr class="h"><th>Variable</th><th>Value</th></tr><tr><th scope="row" class="e">$_COOKIE['XSRF-TOKEN']</th><td  class="v"><span class="yii-debug-phpinfo-redacted" aria-label="Sensitive value hidden">redacted</span></td></tr></table></div></details><details class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-data yii-debug-phpinfo-variable-group" data-yii-debug-phpinfo-collapsible="true" data-yii-debug-phpinfo-default-open="false"><summary class="yii-debug-phpinfo-table-section-head"><span>Server</span><span class="yii-debug-phpinfo-table-section-count">1 row</span></summary><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Server" class="yii-debug-table is-data"><tr class="h"><th>Variable</th><th>Value</th></tr><tr><th scope="row" class="e">$_SERVER['PHP_AUTH_PW']</th><td  class="v"><span class="yii-debug-phpinfo-redacted" aria-label="Sensitive value hidden">redacted</span></td></tr></table></div></details><details class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-data yii-debug-phpinfo-variable-group" data-yii-debug-phpinfo-collapsible="true" data-yii-debug-phpinfo-default-open="false"><summary class="yii-debug-phpinfo-table-section-head"><span>Environment</span><span class="yii-debug-phpinfo-table-section-count">6 rows</span></summary><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Environment" class="yii-debug-table is-data"><tr class="h"><th>Variable</th><th>Value</th></tr><tr><th scope="row" class="e">APP_KEY</th><td  class="v"><span class="yii-debug-phpinfo-redacted" aria-label="Sensitive value hidden">redacted</span></td></tr><tr><th scope="row" class="e">WEBHOOK_SIGNATURE</th><td  class="v"><span class="yii-debug-phpinfo-redacted" aria-label="Sensitive value hidden">redacted</span></td></tr><tr><th scope="row" class="e">PWD</th><td class="v">/srv/app</td></tr><tr><th scope="row" class="e">OLDPWD</th><td class="v">/srv</td></tr><tr><th scope="row" class="e">CHPWD_STATUS</th><td class="v">unchanged</td></tr><tr><th scope="row" class="e">APP_NAME</th><td class="v">Yii application</td></tr></table></div></details><details class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-data yii-debug-phpinfo-variable-group" data-yii-debug-phpinfo-collapsible="true" data-yii-debug-phpinfo-default-open="false"><summary class="yii-debug-phpinfo-table-section-head"><span>Other</span><span class="yii-debug-phpinfo-table-section-count">1 row</span></summary><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Other" class="yii-debug-table is-data"><tr class="h"><th>Variable</th><th>Value</th></tr><tr><th scope="row" class="e">database_url</th><td  class="v"><span class="yii-debug-phpinfo-redacted" aria-label="Sensitive value hidden">redacted</span></td></tr></table></div></details></section>
            HTML,
            $view->modulesHtml,
            'Cookie values must never reach the rendered phpinfo HTML.',
        );
    }

    public function testFromOutputRedactsSensitiveVariablesWhenTableHasNoVariableHeader(): void
    {
        $body = <<<HTML
        <h2>Environment</h2>
        <table>
        <tr><td class="e">DB_PASSWORD</td><td class="v">sensitive-database-password</td></tr>
        <tr><td class="e">APP_NAME</td><td class="v">Yii application</td></tr>
        </table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertSame(
            <<<HTML
            <section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="phpinfo-environment" data-section="Environment"><header class="yii-debug-phpinfo-module-head"><h2 id="phpinfo-environment-heading">Environment</h2></header>
            <div class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-facts"><header class="yii-debug-phpinfo-table-section-head"><span>Module information</span><span class="yii-debug-phpinfo-table-section-count">2 values</span></header><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Module information" class="yii-debug-table is-facts">
            <tr class="yii-debug-phpinfo-fact"><th scope="row" class="e">DB_PASSWORD</th><td  class="v"><span class="yii-debug-phpinfo-redacted" aria-label="Sensitive value hidden">redacted</span></td></tr>
            <tr class="yii-debug-phpinfo-fact"><th scope="row" class="e">APP_NAME</th><td class="v">Yii application</td></tr>
            </table></div></div></section>
            HTML,
            $view->modulesHtml,
            'Redaction must survive the fallback taken when grouping is impossible.',
        );
    }

    public function testFromOutputRequiresBothPosixFunctionsForHomeFallback(): void
    {
        unset($_SERVER['HOME'], $_SERVER['USERPROFILE']);

        putenv('HOME');
        putenv('USERPROFILE');

        MockerState::addCondition(
            'PHPForge\Debug\PhpInfo',
            'function_exists',
            ['posix_getpwuid'],
            true,
        );
        MockerState::addCondition(
            'PHPForge\Debug\PhpInfo',
            'function_exists',
            ['posix_getuid'],
            false,
        );

        PhpInfoDataNormalizer::fromOutput('', 'x', 'cli', 'Linux', '');

        self::assertSame(
            [],
            MockerState::getTraces('PHPForge\Debug\PhpInfo', 'posix_getuid'),
            'The POSIX lookup must not run when posix_getuid is unavailable.',
        );
    }

    public function testFromOutputRequiresPosixPasswordLookupForHomeFallback(): void
    {
        unset($_SERVER['HOME'], $_SERVER['USERPROFILE']);

        putenv('HOME');
        putenv('USERPROFILE');

        MockerState::addCondition(
            'PHPForge\Debug\PhpInfo',
            'function_exists',
            ['posix_getpwuid'],
            false,
        );
        MockerState::addCondition(
            'PHPForge\Debug\PhpInfo',
            'function_exists',
            ['posix_getuid'],
            true,
        );

        PhpInfoDataNormalizer::fromOutput('', 'x', 'cli', 'Linux', '');

        self::assertSame(
            [],
            MockerState::getTraces('PHPForge\Debug\PhpInfo', 'posix_getuid'),
            'The POSIX lookup must not run when posix_getpwuid is unavailable.',
        );
    }

    public function testFromOutputResolvesHomeDirectoryFromPosixWhenEnvUnset(): void
    {
        $body = '<table><tr><td>Loaded Configuration File</td><td>/tmp/php.ini</td></tr></table>';

        unset($_SERVER['HOME'], $_SERVER['USERPROFILE']);

        putenv('HOME');
        putenv('USERPROFILE');

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        $tile = $this->findTileByLabel($view, 'Loaded Configuration File');

        self::assertNotNull(
            $tile,
            'Loaded Configuration File tile must surface.',
        );
        self::assertSame(
            PhpInfoTile::KIND_PATH,
            $tile->kind,
            "Without env signals, 'resolveHomeDirectory()' must still produce a PATH tile (the posix fallback "
            . 'or empty home are both acceptable).',
        );
    }

    public function testFromOutputSeparatesPhpCreditsFromPhpVariables(): void
    {
        $body = <<<HTML
        <h2>PHP Variables</h2><table><tr><td>Variable</td><td>Value</td></tr></table>
        <h1>PHP Credits</h1><table><tr><td>PHP Group</td><td>Contributors</td></tr></table>
        <h2>PHP License</h2><table><tr><td>License text</td></tr></table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');
        $titles = [];

        foreach ($view->tocEntries as $entry) {
            $titles[] = $entry->title;
        }

        self::assertSame(
            ['Overview', 'PHP Variables', 'PHP Credits', 'PHP License'],
            $titles,
            'The h1-based PHP Credits block must become an independent module instead of extending PHP Variables.',
        );
        self::assertSame(
            <<<HTML
            <section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="phpinfo-php-variables" data-section="PHP Variables"><header class="yii-debug-phpinfo-module-head"><h2 id="phpinfo-php-variables-heading">PHP Variables</h2></header><div class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-facts"><header class="yii-debug-phpinfo-table-section-head"><span>Module information</span><span class="yii-debug-phpinfo-table-section-count">1 value</span></header><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Module information" class="yii-debug-table is-facts"><tr class="yii-debug-phpinfo-fact"><td>Variable</td><td>Value</td></tr></table></div></div>
            </section><section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="phpinfo-php-credits" data-section="PHP Credits"><header class="yii-debug-phpinfo-module-head"><h2 id="phpinfo-php-credits-heading">PHP Credits</h2></header><div class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-facts"><header class="yii-debug-phpinfo-table-section-head"><span>Module information</span><span class="yii-debug-phpinfo-table-section-count">1 value</span></header><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Module information" class="yii-debug-table is-facts"><tr class="yii-debug-phpinfo-fact"><td>PHP Group</td><td>Contributors</td></tr></table></div></div>
            </section><section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="phpinfo-php-license" data-section="PHP License"><header class="yii-debug-phpinfo-module-head"><h2 id="phpinfo-php-license-heading">PHP License</h2></header><div class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-note"><header class="yii-debug-phpinfo-table-section-head"><span>Notes</span><span class="yii-debug-phpinfo-table-section-count">1 note</span></header><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Notes" class="yii-debug-table is-note"><tr><td>License text</td></tr></table></div></div></section>
            HTML,
            $view->modulesHtml,
            'PHP Credits must expose its own deep-linkable section.',
        );
    }

    public function testFromOutputShortenPathsAgainstHomeDirectory(): void
    {
        $body = '<table><tr><td>Loaded Configuration File</td><td>/home/dev/projects/app/php.ini</td></tr></table>';
        $_SERVER['HOME'] = '/home/dev';

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        $tile = $this->findTileByLabel($view, 'Loaded Configuration File');

        unset($_SERVER['HOME']);

        self::assertNotNull(
            $tile,
            'Loaded Configuration File tile must surface.',
        );
        self::assertSame(
            '~/projects/app/php.ini',
            $tile->displayValue,
            "Paths under the resolved home directory must be shortened to '~/...'.",
        );
        self::assertSame(
            '/home/dev/projects/app/php.ini',
            $tile->rawValue,
            'Raw path must be preserved alongside the shortened display value.',
        );
    }

    public function testFromOutputShortensHomeDirectoryInsideConfigureCommand(): void
    {
        $_SERVER['HOME'] = '/home/dev';

        $body = <<<HTML
        <table><tr><td>Configure Command</td>
        <td>'./configure' '--prefix=/home/dev/.local/php' '--with-config=/opt/home/dev/etc'</td></tr></table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertStringContainsString(
            "'--prefix=~/.local/php'",
            $view->configureCommand,
            'Account name must not leak through build flags.',
        );
        self::assertStringContainsString(
            "'--with-config=/opt/home/dev/etc'",
            $view->configureCommand,
            'A directory merely ending in the home path must survive.',
        );
    }

    public function testFromOutputSkipsPhpLogoRows(): void
    {
        $body = '<table><tr><td>PHP Logo GUID</td><td>some-guid</td></tr><tr><td>SAPI</td><td>cli</td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            ''
        );

        $section = $view->sections[0] ?? null;

        self::assertNotNull(
            $section,
            'Normalized output must expose at least one hero section.',
        );

        $heroLabels = [];

        foreach ($section->tiles as $tile) {
            $heroLabels[] = $tile->label;
        }

        self::assertNotContains(
            'PHP Logo GUID',
            $heroLabels,
            "'PHP' Logo entries must be filtered out.",
        );
    }

    public function testFromOutputSummarizesSmallFactsOnlyModules(): void
    {
        $body = <<<HTML
        <h2>calendar</h2>
        <table><tr><td class="e">Calendar support</td><td class="v">enabled</td></tr></table>
        <h2>fileinfo</h2>
        <table>
        <tr><td class="e">fileinfo support</td><td class="v">enabled</td></tr>
        <tr><td class="e">libmagic</td><td class="v">5.46</td></tr>
        </table>
        <h2>bcmath</h2>
        <table><tr><td class="e">BCMath support</td><td class="v">enabled</td></tr></table>
        <table>
        <tr class="h"><th>Directive</th><th>Local Value</th><th>Master Value</th></tr>
        <tr><td class="e">bcmath.scale</td><td class="v">0</td><td class="v">0</td></tr>
        </table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertSame(
            ['calendar', 'fileinfo'],
            array_map(static fn(PhpInfoCompactModule $module): string => $module->title, $view->compactModules),
            'One- and two-value modules without other tables must move into the Overview.',
        );
        self::assertSame(
            ['Overview', 'bcmath'],
            array_map(static fn(PhpInfoTocEntry $entry): string => $entry->title, $view->tocEntries),
            'Modules with directives must retain a standalone TOC entry.',
        );
        self::assertSame(
            <<<HTML
            <section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="phpinfo-bcmath" data-section="bcmath"><header class="yii-debug-phpinfo-module-head"><h2 id="phpinfo-bcmath-heading">bcmath</h2></header>
            <div class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-facts"><header class="yii-debug-phpinfo-table-section-head"><span>Module information</span><span class="yii-debug-phpinfo-table-section-count">1 value</span></header><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Module information" class="yii-debug-table is-facts"><tr class="yii-debug-phpinfo-fact"><th scope="row" class="e">BCMath support</th><td class="v"><span class="yii-debug-phpinfo-status-pill" data-variant="success">enabled</span></td></tr></table></div></div>
            <div class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-directives"><header class="yii-debug-phpinfo-table-section-head"><span>Configuration directives</span><span class="yii-debug-phpinfo-table-section-count">1 directive</span></header><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Configuration directives" class="yii-debug-table is-directives">
            <tr class="h"><th>Directive</th><th>Local Value</th><th>Master Value</th></tr>
            <tr><th scope="row" class="e">bcmath.scale</th><td class="v">0</td><td class="v">0</td></tr>
            </table></div></div></section>
            HTML,
            $view->modulesHtml,
            'Compact modules must not retain a duplicate standalone section.',
        );

    }

    public function testFromOutputSurfacesPathTokensForStandaloneAbsolutePath(): void
    {
        $body = '<table><tr><td>Loaded Configuration File</td><td>/etc/php/cli/php.ini</td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        $tile = $this->findTileByLabel($view, 'Loaded Configuration File');

        self::assertNotNull(
            $tile,
            'Loaded Configuration File tile must surface.',
        );
        self::assertSame(
            PhpInfoTile::KIND_PATH,
            $tile->kind,
            "Single leading '/' path must classify as 'KIND_PATH'.",
        );
    }

    public function testFromOutputTreatsAHeaderOnlyTwoColumnTableAsFacts(): void
    {
        $body = '<h2>PHP Credits</h2><table><tr class="h"><th>Unknown</th><th>Other</th></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertSame(
            <<<HTML
            <section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="phpinfo-php-credits" data-section="PHP Credits"><header class="yii-debug-phpinfo-module-head"><h2 id="phpinfo-php-credits-heading">PHP Credits</h2></header><div class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-facts"><header class="yii-debug-phpinfo-table-section-head"><span>Module information</span><span class="yii-debug-phpinfo-table-section-count">1 value</span></header><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Module information" class="yii-debug-table is-facts"><tr class="yii-debug-phpinfo-fact"><th>Unknown</th><th>Other</th></tr></table></div></div></section>
            HTML,
            $view->modulesHtml,
            'A two-column header itself carries a fact value when no known data heading is present.',
        );
    }

    public function testFromOutputTrimsRuntimeTilesAndKeepsTokenBoundariesUnicodeAware(): void
    {
        $unicodeToken = str_repeat('é', 20);
        $boundaryToken = str_repeat('a', 32);

        $body = <<<HTML
        <table>
        <tr><td>Registered PHP Streams</td><td>single,</td></tr>
        <tr><td>Registered Stream Socket Transports</td><td>{$boundaryToken},short</td></tr>
        <tr><td>Registered Stream Filters</td><td>{$unicodeToken},short</td></tr>
        </table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', ' cli ', ' Linux ', ' 128M ');

        $tiles = [];

        foreach ($view->sections as $section) {
            foreach ($section->tiles as $tile) {
                $tiles[$tile->label] = [$tile->displayValue, $tile->kind];
            }
        }

        self::assertSame(
            ['cli', PhpInfoTile::KIND_TEXT],
            $tiles['SAPI'] ?? null,
            'Runtime values must be trimmed.',
        );
        self::assertSame(
            ['128M', PhpInfoTile::KIND_TEXT],
            $tiles['Memory limit'] ?? null,
            'Memory limit must be trimmed before classification.',
        );
        self::assertSame(
            ['single,', PhpInfoTile::KIND_TEXT],
            $tiles['Registered PHP Streams'] ?? null,
            'A comma that yields only one non-empty token must remain text.',
        );
        self::assertSame(
            ["{$boundaryToken},short", PhpInfoTile::KIND_TOKEN_LIST],
            $tiles['Registered Stream Socket Transports'] ?? null,
            'A token of exactly 32 characters must remain a token list.',
        );
        self::assertSame(
            ["{$unicodeToken},short", PhpInfoTile::KIND_TOKEN_LIST],
            $tiles['Registered Stream Filters'] ?? null,
            'Token length must be measured in Unicode characters rather than bytes.',
        );
    }

    public function testFromOutputTrimsTrailingHomeSeparators(): void
    {
        $originalHome = $_SERVER['HOME'] ?? null;
        $_SERVER['HOME'] = '/home/example/';

        try {
            $view = PhpInfoDataNormalizer::fromOutput(
                '<table><tr><td>Loaded Configuration File</td><td>/home/example/php.ini</td></tr></table>',
                'x',
                'cli',
                'Linux',
                '',
            );
        } finally {
            if ($originalHome === null) {
                unset($_SERVER['HOME']);
            } else {
                $_SERVER['HOME'] = $originalHome;
            }
        }

        self::assertSame(
            '~/php.ini',
            $this->findTileByLabel($view, 'Loaded Configuration File')?->displayValue,
            'Trailing home-directory separators must not prevent path shortening.',
        );
    }

    public function testFromOutputUsesAndTrimsPosixHomeFallback(): void
    {
        unset($_SERVER['HOME'], $_SERVER['USERPROFILE']);

        putenv('HOME');
        putenv('USERPROFILE');

        MockerState::addCondition(
            'PHPForge\Debug\PhpInfo',
            'function_exists',
            ['posix_getpwuid'],
            true,
        );
        MockerState::addCondition(
            'PHPForge\Debug\PhpInfo',
            'function_exists',
            ['posix_getuid'],
            true,
        );
        MockerState::addCondition(
            'PHPForge\Debug\PhpInfo',
            'posix_getuid',
            [],
            1000,
        );
        MockerState::addCondition(
            'PHPForge\Debug\PhpInfo',
            'posix_getpwuid',
            [1000],
            ['dir' => '/home/example/'],
        );

        $view = PhpInfoDataNormalizer::fromOutput(
            '<table><tr><td>Loaded Configuration File</td><td>/home/example/php.ini</td></tr></table>',
            'x',
            'cli',
            'Linux',
            '',
        );

        self::assertSame(
            '~/php.ini',
            $this->findTileByLabel($view, 'Loaded Configuration File')?->displayValue,
            'The POSIX home fallback must be returned and trimmed when both functions exist.',
        );
    }

    public function testFromOutputUsesNativePhpCreditsTableTitles(): void
    {
        $body = <<<HTML
        <h2>PHP Variables</h2>
        <h1>PHP Credits</h1>
        <table><tr class="h"><th>PHP Group</th></tr><tr><td>Contributors</td></tr></table>
        <table>
        <tr class="h"><th>PHP Authors</th></tr>
        <tr><td class="e">Zend Engine</td><td class="v">Authors</td></tr>
        </table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertSame(
            <<<HTML
            <section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="phpinfo-php-credits" data-section="PHP Credits"><header class="yii-debug-phpinfo-module-head"><h2 id="phpinfo-php-credits-heading">PHP Credits</h2></header>
            <div class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-note"><header class="yii-debug-phpinfo-table-section-head"><span>PHP Group</span><span class="yii-debug-phpinfo-table-section-count">1 note</span></header><div class="yii-debug-phpinfo-table-scroll"><table aria-label="PHP Group" class="yii-debug-table is-note"><tr><td>Contributors</td></tr></table></div></div>
            <div class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-facts"><header class="yii-debug-phpinfo-table-section-head"><span>PHP Authors</span><span class="yii-debug-phpinfo-table-section-count">1 value</span></header><div class="yii-debug-phpinfo-table-scroll"><table aria-label="PHP Authors" class="yii-debug-table is-facts">
            <tr class="yii-debug-phpinfo-fact"><th scope="row" class="e">Zend Engine</th><td class="v">Authors</td></tr>
            </table></div></div></section>
            HTML,
            $view->modulesHtml,
            'A one-cell phpinfo heading must become the table card title.',
        );
    }

    public function testFromOutputWrapsModulesHtmlWithSectionChrome(): void
    {
        $body = <<<HTML
        <h2>apcu</h2>
        <table>
        <tr><td>Version</td><td>5.1</td></tr>
        <tr><td>Debug</td><td>disabled</td></tr>
        <tr><td>MMAP</td><td>enabled</td></tr>
        </table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        self::assertSame(
            <<<HTML
            <section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="phpinfo-apcu" data-section="apcu"><header class="yii-debug-phpinfo-module-head"><h2 id="phpinfo-apcu-heading">apcu</h2></header>
            <div class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-facts"><header class="yii-debug-phpinfo-table-section-head"><span>Module information</span><span class="yii-debug-phpinfo-table-section-count">3 values</span></header><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Module information" class="yii-debug-table is-facts">
            <tr class="yii-debug-phpinfo-fact"><td>Version</td><td>5.1</td></tr>
            <tr class="yii-debug-phpinfo-fact"><td>Debug</td><td>disabled</td></tr>
            <tr class="yii-debug-phpinfo-fact"><td>MMAP</td><td>enabled</td></tr>
            </table></div></div></section>
            HTML,
            $view->modulesHtml,
            'Modules HTML must wrap blocks with the module class.',
        );
    }

    public function testResolveHomeDirectoryReturnsEmptyWhenEnvAndPosixUnavailable(): void
    {
        unset($_SERVER['HOME'], $_SERVER['USERPROFILE']);

        putenv('HOME');
        putenv('USERPROFILE');

        MockerState::addCondition(
            'PHPForge\Debug\PhpInfo',
            'function_exists',
            [],
            false,
            true,
        );

        $view = PhpInfoDataNormalizer::fromOutput(
            '<table><tr><td>Loaded Configuration File</td><td>/etc/php.ini</td></tr></table>',
            'x',
            'cli',
            'Linux',
            '',
        );

        $tile = $this->findTileByLabel($view, 'Loaded Configuration File');

        self::assertNotNull(
            $tile,
            'Loaded Configuration File tile must surface.',
        );
        self::assertSame(
            '/etc/php.ini',
            $tile->displayValue,
            "With no home directory resolved, paths must surface verbatim (empty '\$home' skips shortening).",
        );
        self::assertSame(
            [],
            MockerState::getTraces('PHPForge\Debug\PhpInfo', 'posix_getuid'),
            'POSIX lookup must not run when neither function exists.',
        );
    }

    /**
     * Captures the home-directory environment state before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::HOME_ENVIRONMENT_VARIABLES as $variable) {
            $this->processHomeEnvironment[$variable] = getenv($variable);
            $this->serverHomeEnvironment[$variable] = [
                'defined' => array_key_exists($variable, $_SERVER),
                'value' => $_SERVER[$variable] ?? null,
            ];
        }
    }

    /**
     * Restores the home-directory environment state after each test.
     */
    protected function tearDown(): void
    {
        foreach (self::HOME_ENVIRONMENT_VARIABLES as $variable) {
            $serverState = $this->serverHomeEnvironment[$variable];

            if ($serverState['defined']) {
                $_SERVER[$variable] = $serverState['value'];
            } else {
                unset($_SERVER[$variable]);
            }

            $processValue = $this->processHomeEnvironment[$variable];

            putenv($processValue === false ? $variable : "{$variable}={$processValue}");
        }

        parent::tearDown();
    }

    private function findTileByLabel(PhpInfoView $view, string $label): PhpInfoTile|null
    {
        foreach ($view->sections as $section) {
            foreach ($section->tiles as $tile) {
                if ($tile->label === $label) {
                    return $tile;
                }
            }
        }

        return null;
    }
}
