<?php

declare(strict_types=1);

use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Helper\LogLevel;
use PHPForge\Debug\Panel\Asset\AssetSnapshot;
use PHPForge\Debug\Panel\Config\ConfigSnapshot;
use PHPForge\Debug\Panel\Db\DbSnapshot;
use PHPForge\Debug\Panel\Dump\DumpSnapshot;
use PHPForge\Debug\Panel\Event\EventSnapshot;
use PHPForge\Debug\Panel\Inertia\InertiaSnapshot;
use PHPForge\Debug\Panel\Log\LogSnapshot;
use PHPForge\Debug\Panel\Mail\MailSnapshot;
use PHPForge\Debug\Panel\Profile\ProfilingSnapshot;
use PHPForge\Debug\Panel\Queue\QueueSnapshot;
use PHPForge\Debug\Panel\Request\RequestSnapshot;
use PHPForge\Debug\Panel\Router\RouterSnapshot;
use PHPForge\Debug\Panel\Timeline\TimelineSnapshot;
use PHPForge\Debug\Panel\User\UserSnapshot;
use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary, SnapshotStore};

require dirname(__DIR__) . '/vendor/autoload.php';

const PANEL_SNAPSHOT_CLASSES = [
    'asset' => AssetSnapshot::class,
    'config' => ConfigSnapshot::class,
    'db' => DbSnapshot::class,
    'dump' => DumpSnapshot::class,
    'event' => EventSnapshot::class,
    'inertia' => InertiaSnapshot::class,
    'log' => LogSnapshot::class,
    'mail' => MailSnapshot::class,
    'profiling' => ProfilingSnapshot::class,
    'queue' => QueueSnapshot::class,
    'request' => RequestSnapshot::class,
    'router' => RouterSnapshot::class,
    'timeline' => TimelineSnapshot::class,
    'user' => UserSnapshot::class,
];

/**
 * @return array<string, mixed>
 */
function readContract(): array
{
    $file = __DIR__ . '/quality/fixture-contract.json';
    $raw = file_get_contents($file);

    if ($raw === false) {
        throw new RuntimeException("Unable to read fixture contract: {$file}");
    }

    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($decoded)) {
        throw new RuntimeException('Fixture contract must decode to an object.');
    }

    $contract = [];

    foreach ($decoded as $key => $value) {
        if (!is_string($key)) {
            throw new RuntimeException('Fixture contract must use string keys.');
        }

        $contract[$key] = $value;
    }

    return $contract;
}

/**
 * @param array<string, mixed> $contract
 * @param array<string, string|false|list<mixed>> $options
 *
 * @return list<array{name: string, path: string, baseURL: string}>
 */
function resolveTargets(array $contract, array $options): array
{
    $configured = $contract['apps'] ?? null;

    if (!is_array($configured)) {
        throw new RuntimeException("Fixture contract field 'apps' must be a list.");
    }

    $overrides = $options['app'] ?? [];
    $overrides = is_array($overrides) ? $overrides : [$overrides];
    $targets = [];

    foreach (array_values($configured) as $index => $app) {
        if (!is_array($app)) {
            throw new RuntimeException("Fixture app at index {$index} must be an object.");
        }

        $name = $app['name'] ?? null;
        $path = $overrides[$index] ?? ($app['path'] ?? null);
        $baseURL = $app['baseURL'] ?? null;

        if (!is_string($name) || !is_string($path) || !is_string($baseURL)) {
            throw new RuntimeException("Fixture app at index {$index} is incomplete.");
        }

        if (!str_starts_with($path, '/')) {
            $path = dirname(__DIR__) . '/' . $path;
        }

        $resolved = realpath($path);

        if ($resolved === false || !is_file($resolved . '/composer.json') || !is_dir($resolved . '/runtime')) {
            throw new RuntimeException("Fixture application is unavailable: {$path}");
        }

        $targets[] = ['name' => $name, 'path' => $resolved, 'baseURL' => rtrim($baseURL, '/')];
    }

    return $targets;
}

/**
 * @param Closure(int): array<string, mixed> $factory
 *
 * @return list<array<string, mixed>>
 */
function rows(int $count, Closure $factory): array
{
    $result = [];

    for ($index = 0; $index < $count; ++$index) {
        $result[] = $factory($index);
    }

    return $result;
}

/**
 * @template TValue
 *
 * @param non-empty-list<TValue> $values
 *
 * @return TValue
 */
function cyclicValue(array $values, int $index): mixed
{
    $count = count($values);
    $offset = $index % $count;
    $offset = $offset < 0 ? $offset + $count : $offset;

    if (!array_key_exists($offset, $values)) {
        throw new RuntimeException("Fixture cycle offset is unavailable: {$offset}");
    }

    return $values[$offset];
}

/**
 * @return list<array<string, mixed>>
 */
function trace(int $index): array
{
    return [
        [
            'file' => '/app/src/Quality/FixtureService.php',
            'line' => 40 + $index,
            'function' => 'loadFixture',
            'class' => 'app\\quality\\FixtureService',
            'type' => '->',
        ],
    ];
}

/**
 * @param array<string, mixed> $contract
 *
 * @return array{
 *     exactKey: string,
 *     exactSentinel: string,
 *     sensitiveKeyPrefix: string,
 *     prefixedKey: string,
 *     prefixedSentinel: string,
 *     controlKey: string,
 *     controlValue: string,
 *     placeholder: string
 * }
 */
function securityContract(array $contract): array
{
    $security = $contract['security'] ?? null;

    if (!is_array($security)) {
        throw new RuntimeException("Fixture contract field 'security' must be an object.");
    }

    $exactKey = requiredSecurityString($security, 'exactKey');
    $exactSentinel = requiredSecurityString($security, 'exactSentinel');
    $sensitiveKeyPrefix = requiredSecurityString($security, 'sensitiveKeyPrefix');
    $prefixedKey = requiredSecurityString($security, 'prefixedKey');
    $prefixedSentinel = requiredSecurityString($security, 'prefixedSentinel');
    $controlKey = requiredSecurityString($security, 'controlKey');
    $controlValue = requiredSecurityString($security, 'controlValue');
    $placeholder = requiredSecurityString($security, 'placeholder');

    if (!str_starts_with($prefixedKey, $sensitiveKeyPrefix)) {
        throw new RuntimeException('Fixture prefixed key must start with the configured sensitive prefix.');
    }

    if (count(array_unique([$exactKey, $prefixedKey, $controlKey])) !== 3) {
        throw new RuntimeException('Fixture security keys must be distinct.');
    }

    return [
        'exactKey' => $exactKey,
        'exactSentinel' => $exactSentinel,
        'sensitiveKeyPrefix' => $sensitiveKeyPrefix,
        'prefixedKey' => $prefixedKey,
        'prefixedSentinel' => $prefixedSentinel,
        'controlKey' => $controlKey,
        'controlValue' => $controlValue,
        'placeholder' => $placeholder,
    ];
}

/**
 * @param array<mixed> $security
 */
function requiredSecurityString(array $security, string $field): string
{
    $value = $security[$field] ?? null;

    if (!is_string($value) || $value === '') {
        throw new RuntimeException("Fixture security field '{$field}' must be a non-empty string.");
    }

    return $value;
}

/**
 * Builds an end-to-end redaction sentinel with one default exact key and one
 * explicitly configured prefix. Sentinel values are synthetic and must never
 * reach persistent fixtures.
 *
 * @param array<string, mixed> $contract
 *
 * @return array<string, string>
 */
function sensitiveFixture(array $contract): array
{
    $security = securityContract($contract);
    $policy = new CapturePolicy(sensitiveKeyPrefixes: [$security['sensitiveKeyPrefix']]);
    $redacted = $policy->redact(
        [
            $security['exactKey'] => $security['exactSentinel'],
            $security['prefixedKey'] => $security['prefixedSentinel'],
            $security['controlKey'] => $security['controlValue'],
        ],
    );
    if (
        ($redacted[$security['exactKey']] ?? null) !== $security['placeholder']
        || ($redacted[$security['prefixedKey']] ?? null) !== $security['placeholder']
        || ($redacted[$security['controlKey']] ?? null) !== $security['controlValue']
    ) {
        throw new RuntimeException('Fixture redaction policy did not produce the expected sentinel result.');
    }

    return [
        $security['exactKey'] => $security['placeholder'],
        $security['prefixedKey'] => $security['placeholder'],
        $security['controlKey'] => $security['controlValue'],
    ];
}

/**
 * @return array<string, array<string, mixed>>
 */
function emptyPanels(float $timestamp): array
{
    return normalizePanels(
        [
            'asset' => ['bundles' => [], 'vite' => null],
            'config' => ConfigSnapshot::capture([])->jsonSerialize(),
            'db' => ['entries' => []],
            'dump' => ['entries' => []],
            'event' => ['entries' => []],
            'inertia' => InertiaSnapshot::capture(null, null, [], [], 204)->jsonSerialize(),
            'log' => ['entries' => []],
            'mail' => ['entries' => []],
            'profiling' => ['memory' => 1_048_576, 'time' => 0.001, 'entries' => [], 'samples' => []],
            'queue' => ['entries' => []],
            'request' => RequestSnapshot::capture(
                [
                    'statusCode' => 204,
                    'general' => ['method' => 'GET'],
                    'requestHeaders' => [],
                    'responseHeaders' => [],
                    'requestBody' => [],
                    'GET' => [],
                    'POST' => [],
                    'FILES' => [],
                    'COOKIE' => [],
                    'SERVER' => [],
                ],
            )->jsonSerialize(),
            'router' => ['action' => null, 'route' => '', 'message' => null, 'entries' => []],
            'timeline' => ['start' => $timestamp, 'end' => $timestamp + 0.001, 'memory' => 1_048_576],
            'user' => UserSnapshot::capture(
                ['id' => null, 'identity' => null, 'attributes' => null, 'roles' => null, 'permissions' => null],
            )->jsonSerialize(),
        ],
    );
}

/**
 * @param array<string, string> $sensitiveFixture
 *
 * @return array<string, array<string, mixed>>
 */
function densePanels(
    float $timestamp,
    int $rowCount,
    array $sensitiveFixture,
): array {
    $levels = [LogLevel::INFO, LogLevel::WARNING, LogLevel::ERROR, LogLevel::TRACE];
    $queueTypes = ['push', 'exec', 'error'];
    $queryTypes = ['SELECT', 'INSERT', 'UPDATE', 'DELETE'];
    $profileCategories = ['app', 'db', 'view', 'cache', 'mail', 'queue'];
    $assetCount = min(12, $rowCount);
    $mailCount = min(16, $rowCount);
    $dumpCount = min(24, $rowCount);

    $panels = [
        'asset' => [
            'bundles' => rows(
                $assetCount,
                static fn(int $index): array => [
                    'name' => "app\\assets\\QualityBundle{$index}",
                    'sourcePath' => "/app/resources/quality/{$index}",
                    'basePath' => "/app/public/assets/quality-{$index}",
                    'baseUrl' => "/assets/quality-{$index}",
                    'css' => ["css/quality-{$index}.css"],
                    'js' => ["js/quality-{$index}.js"],
                    'depends' => $index === 0 ? [] : ['yii\\web\\YiiAsset'],
                ],
            ),
            'vite' => [
                'baseUrl' => '/build',
                'devMode' => false,
                'devServerUrl' => null,
                'manifestPath' => '/app/public/build/.vite/manifest.json',
                'chunks' => rows(
                    min(10, $rowCount),
                    static fn(int $index): array => [
                        'name' => "resources/js/quality-{$index}.js",
                        'file' => "assets/quality-{$index}.js",
                        'cssCount' => 1,
                        'imports' => $index % 3,
                        'isEntry' => $index === 0,
                    ],
                ),
            ],
        ],
        'config' => ConfigSnapshot::capture(
            [
                'phpVersion' => PHP_VERSION,
                'yiiVersion' => '22.0-quality-fixture',
                'application' => [
                    'yii' => '22.0-quality-fixture',
                    'name' => 'Debug quality fixture',
                    'version' => '1.0.0',
                    'language' => 'en-US',
                    'sourceLanguage' => 'en-US',
                    'charset' => 'UTF-8',
                    'env' => 'test',
                    'debug' => true,
                ],
                'php' => [
                    'version' => PHP_VERSION,
                    'xdebug' => false,
                    'apcu' => false,
                    'memcache' => false,
                    'memcached' => false,
                ],
                'extensions' => [
                    'php-forge/debug-core' => [
                        'name' => 'php-forge/debug-core',
                        'version' => 'quality-fixture',
                        'alias' => ['@phpForge/debug' => '/app/vendor/php-forge/debug-core/src'],
                    ],
                    'yii2-extensions/debug' => [
                        'name' => 'yii2-extensions/debug',
                        'version' => 'quality-fixture',
                        'alias' => ['@yii/debug' => '/app/vendor/yii2-extensions/debug/src'],
                    ],
                ],
            ],
        )->jsonSerialize(),
        'db' => [
            'entries' => rows(
                $rowCount,
                static function (int $index) use ($queryTypes, $timestamp): array {
                    $type = cyclicValue($queryTypes, $index);
                    $fixtureId = $index + 1;
                    $fixtureTimestamp = (int) $timestamp;

                    // Keep every statement valid for EXPLAIN while making synthetic DML a no-op if executed directly.
                    $query = match ($type) {
                        'SELECT' => "SELECT id, username FROM user WHERE id = {$fixtureId}",
                        'INSERT' => "INSERT INTO user (username, auth_key, password_hash, email, created_at, updated_at) "
                            . "SELECT 'quality-fixture-{$fixtureId}', 'quality-fixture-auth-key', "
                            . "'quality-fixture-password-hash', 'quality-fixture-{$fixtureId}@example.test', "
                            . "{$fixtureTimestamp}, {$fixtureTimestamp} WHERE 0",
                        'UPDATE' => "UPDATE user SET updated_at = updated_at "
                            . "WHERE id = {$fixtureId} AND id <> {$fixtureId}",
                        default => "DELETE FROM user WHERE id = {$fixtureId} AND id <> {$fixtureId}",
                    };

                    return [
                        'type' => $type,
                        'query' => $query,
                        'duration' => 0.25 + ($index % 12) * 1.75,
                        'trace' => trace($index),
                        'traceHash' => 'quality-trace-' . ($index % 8),
                        'timestamp' => ($timestamp * 1000) + $index,
                        'seq' => $index,
                        'duplicate' => 1 + ($index % 5),
                        'rows' => $index % 7 === 0 ? null : $index % 50,
                    ];
                },
            ),
        ],
        'dump' => [
            'entries' => rows(
                $dumpCount,
                static fn(int $index): array => [
                    'message' => "quality fixture value {$index}: [id => {$index}, enabled => true]",
                    'level' => LogLevel::INFO,
                    'category' => 'application.quality.fixture',
                    'time' => ($timestamp * 1000) + $index,
                    'trace' => trace($index),
                ],
            ),
        ],
        'event' => [
            'entries' => rows(
                $rowCount,
                static fn(int $index): array => [
                    'time' => $timestamp + ($index / 1000),
                    'name' => 'quality.fixture.event.' . ($index % 12),
                    'class' => 'yii\\base\\Event',
                    'isStatic' => $index % 4 === 0 ? '1' : '0',
                    'senderClass' => $index % 4 === 0 ? '' : 'app\\quality\\FixtureService',
                ],
            ),
        ],
        'inertia' => InertiaSnapshot::capture(
            null,
            [
                'component' => 'Quality/Fixture',
                'props' => [
                    'title' => 'Deterministic dense state',
                    'records' => rows(
                        min(40, $rowCount),
                        static fn(int $index): array => [
                            'id' => $index + 1,
                            'name' => "Fixture record {$index}",
                            'active' => $index % 3 !== 0,
                        ],
                    ),
                ],
                'url' => '/quality-fixture/dense',
                'version' => 'quality-fixture-v1',
            ],
            ['x-inertia' => 'true', 'accept' => 'text/html, application/xhtml+xml'],
            ['auth', 'appName', 'qualityFixture'],
            200,
        )->jsonSerialize(),
        'log' => [
            'entries' => rows(
                $rowCount,
                static function (int $index) use ($levels, $timestamp, $rowCount): array {
                    $id = $index + 1;
                    $time = ($timestamp * 1000) + ($index * 1.25);

                    return [
                        'id' => $id,
                        'message' => "Quality fixture log {$id} — unicode λ and a deliberately long diagnostic value "
                            . str_repeat('x', $index % 24),
                        'level' => cyclicValue($levels, $index),
                        'category' => 'application.quality.' . ($index % 10),
                        'time' => $time,
                        'timeOfPrevious' => $index === 0 ? $time : $time - 1.25,
                        'timeSincePrevious' => $index === 0 ? 0.0 : 0.00125,
                        'idOfPrevious' => $index === 0 ? null : $id - 1,
                        'idOfNext' => $id === $rowCount ? null : $id + 1,
                        'memory' => 2_097_152 + ($index * 4096),
                        'trace' => trace($index),
                    ];
                },
            ),
        ],
        'mail' => [
            'entries' => rows(
                $mailCount,
                static fn(int $index): array => [
                    'from' => 'Debug Fixture <fixture@example.test>',
                    'to' => ["recipient-{$index}@example.test"],
                    'cc' => $index % 3 === 0 ? ['copy@example.test'] : [],
                    'bcc' => [],
                    'replyTo' => ['no-reply@example.test'],
                    'subject' => "Quality fixture message {$index}",
                    'body' => "This is deterministic synthetic mail body {$index}.\nNo real recipient or secret is used.",
                    'headers' => "From: fixture@example.test\nX-Quality-Fixture: {$index}",
                    'charset' => 'UTF-8',
                    'file' => '',
                    'isSuccessful' => $index % 5 !== 0,
                    'time' => (int) $timestamp + $index,
                ],
            ),
        ],
        'profiling' => [
            'memory' => 16_777_216,
            'time' => 0.485,
            'entries' => rows(
                $rowCount,
                static fn(int $index): array => [
                    'timestamp' => ($timestamp * 1000) + ($index * 2),
                    'duration' => 0.5 + ($index % 18) * 1.4,
                    'category' => cyclicValue($profileCategories, $index),
                    'info' => "quality.fixture.profile.{$index}",
                    'level' => $index % 4,
                    'seq' => $index,
                    'memory' => 2_097_152 + ($index * 8192),
                    'memoryDiff' => ($index % 2 === 0 ? 1 : -1) * ($index * 256),
                    'trace' => trace($index),
                ],
            ),
            'samples' => rows(
                min(40, $rowCount),
                static fn(int $index): array => [
                    'time' => ($timestamp * 1000) + ($index * 10),
                    'memory' => 2_097_152 + ($index * 65_536),
                ],
            ),
        ],
        'queue' => [
            'entries' => rows(
                min(30, $rowCount),
                static function (int $index) use ($queueTypes, $timestamp): array {
                    $eventType = cyclicValue($queueTypes, $index);

                    return [
                        'eventType' => $eventType,
                        'componentId' => 'queue',
                        'driverName' => 'Database',
                        'driverClass' => 'yii\\queue\\db\\Queue',
                        'isAsync' => true,
                        'jobClass' => 'app\\jobs\\QualityFixtureJob',
                        'payloadFields' => [
                            'recordId' => $index + 1,
                            'label' => "Fixture job {$index}",
                            'options' => ['priority' => $index % 5, 'dryRun' => true],
                        ],
                        'time' => $timestamp + ($index / 100),
                        'jobId' => "quality-job-{$index}",
                        'ttr' => 300,
                        'delay' => $index % 10,
                        'priority' => $index % 5,
                        'attempt' => $eventType === 'push' ? null : 1 + ($index % 3),
                        'duration' => $eventType === 'push' ? null : 0.025 + ($index / 1000),
                        'error' => $eventType === 'error' ? "Synthetic fixture failure {$index}" : '',
                    ];
                },
            ),
        ],
        'request' => RequestSnapshot::capture(
            [
                'action' => 'app\\controllers\\SiteController::actionQualityFixture()',
                'actionParams' => ['state' => 'dense', 'page' => 1],
                'flashes' => ['notice' => 'Synthetic fixture only'],
                'general' => [
                    'isAjax' => false,
                    'isFlash' => false,
                    'isPjax' => false,
                    'isSecureConnection' => false,
                    'method' => 'GET',
                ],
                'requestBody' => [
                    'search' => 'quality fixture',
                    'redactionSentinel' => $sensitiveFixture,
                ],
                'requestHeaders' => [
                    'accept' => 'text/html',
                    'user-agent' => 'DebugQualityFixture/1.0',
                    'x-quality-fixture' => 'dense',
                ],
                'responseHeaders' => [
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'Cache-Control' => 'no-store',
                    'X-Quality-Fixture' => 'dense',
                ],
                'route' => 'site/quality-fixture',
                'statusCode' => 200,
                'COOKIE' => ['session' => '[redacted]'],
                'FILES' => [],
                'GET' => ['state' => 'dense', 'page' => '1'],
                'POST' => [],
                'SERVER' => [
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI' => '/quality-fixture/dense',
                    'SERVER_NAME' => 'localhost',
                    'APP_ENV' => 'test',
                ],
                'SESSION' => ['quality_fixture' => true],
            ],
        )->jsonSerialize(),
        'router' => [
            'action' => 'app\\controllers\\SiteController::actionQualityFixture()',
            'route' => 'site/quality-fixture',
            'message' => 'Matched deterministic quality fixture route.',
            'entries' => rows(
                min(18, $rowCount),
                static fn(int $index): array => [
                    'rule' => $index === 17 ? 'quality-fixture' : "route-rule-{$index}",
                    'parent' => $index % 4 === 0 ? 'application-routes' : '',
                    'match' => $index === min(18, $rowCount) - 1,
                ],
            ),
        ],
        'timeline' => [
            'start' => $timestamp,
            'end' => $timestamp + 0.485,
            'memory' => 16_777_216,
        ],
        'user' => UserSnapshot::capture(
            [
                'id' => 42,
                'identity' => [
                    'id' => '42',
                    'username' => "'quality-fixture'",
                    'email' => "'fixture@example.test'",
                    'status' => '10',
                    'auth_key' => '[redacted]',
                    'created_at' => (string) ((int) $timestamp - 86_400),
                    'locale' => "'en-US'",
                ],
                'attributes' => [
                    ['attribute' => 'id', 'label' => 'ID'],
                    ['attribute' => 'username', 'label' => 'Username'],
                    ['attribute' => 'email', 'label' => 'Email'],
                    ['attribute' => 'status', 'label' => 'Status'],
                    ['attribute' => 'created_at', 'label' => 'Created at'],
                ],
                'roles' => rows(
                    min(12, $rowCount),
                    static fn(int $index): array => [
                        'name' => "fixture-role-{$index}",
                        'description' => "Synthetic role {$index}",
                        'ruleName' => '',
                        'data' => 'null',
                        'createdAt' => (int) $timestamp - $index,
                        'updatedAt' => (int) $timestamp,
                    ],
                ),
                'permissions' => rows(
                    min(20, $rowCount),
                    static fn(int $index): array => [
                        'name' => "fixture.permission.{$index}",
                        'description' => "Synthetic permission {$index}",
                        'ruleName' => $index % 3 === 0 ? 'qualityRule' : '',
                        'data' => 'null',
                        'createdAt' => (int) $timestamp - $index,
                        'updatedAt' => (int) $timestamp,
                    ],
                ),
            ],
        )->jsonSerialize(),
    ];

    return normalizePanels($panels);
}

/**
 * Hydrates and reserializes every payload through its production DTO. This makes
 * schema drift fail fixture generation before an invalid file reaches an app.
 *
 * @param array<string, array<string, mixed>> $panels
 *
 * @return array<string, array<string, mixed>>
 */
function normalizePanels(array $panels): array
{
    $normalized = [];

    foreach (PANEL_SNAPSHOT_CLASSES as $id => $class) {
        $payload = $panels[$id] ?? null;

        if (!is_array($payload)) {
            throw new RuntimeException("Fixture panel '{$id}' is missing.");
        }

        $snapshot = $class::fromArray($payload, "$.panels.{$id}");
        $normalized[$id] = $snapshot->jsonSerialize();
    }

    return $normalized;
}

/**
 * @param array<string, array<string, mixed>> $panels
 */
function snapshot(
    string $tag,
    string $url,
    float $timestamp,
    array $panels,
    int $statusCode,
): DebugSnapshot {
    $dbEntries = $panels['db']['entries'] ?? null;
    $mailEntries = $panels['mail']['entries'] ?? null;
    $summary = new RequestSummary(
        tag: $tag,
        url: $url,
        ajax: false,
        method: 'GET',
        ip: '127.0.0.1',
        time: $timestamp,
        statusCode: $statusCode,
        sqlCount: is_array($dbEntries) ? count($dbEntries) : 0,
        excessiveCallersCount: 0,
        mailCount: is_array($mailEntries) ? count($mailEntries) : 0,
        mailFiles: [],
        processingTime: $statusCode === 204 ? 0.001 : 0.485,
        peakMemory: $statusCode === 204 ? 1_048_576 : 16_777_216,
    );

    return new DebugSnapshot($summary, $panels, []);
}

/**
 * Returns tagged-capture values persisted for an exact DebugArray key.
 *
 * @return list<mixed>
 */
function capturedValuesForKey(mixed $node, string $target): array
{
    if (!is_array($node)) {
        return [];
    }

    $values = [];

    if (($node['key'] ?? null) === $target && is_array($node['value'] ?? null)) {
        $values[] = $node['value']['value'] ?? null;
    }

    foreach ($node as $child) {
        array_push($values, ...capturedValuesForKey($child, $target));
    }

    return $values;
}

/**
 * Verifies the serialized fixture, not only the pre-storage object, so storage
 * or tagged-value regressions cannot expose the synthetic sentinels.
 *
 * @param array<string, mixed> $contract
 */
function assertPersistedRedaction(string $file, array $contract): void
{
    $raw = file_get_contents($file);
    $security = securityContract($contract);

    if ($raw === false) {
        throw new RuntimeException("Unable to inspect the persisted security fixture: {$file}");
    }

    foreach (['exactSentinel', 'prefixedSentinel'] as $field) {
        $sentinel = $security[$field];

        if (str_contains($raw, $sentinel)) {
            throw new RuntimeException("Persisted fixture contains forbidden sentinel '{$field}'.");
        }
    }

    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

    foreach (['exactKey', 'prefixedKey'] as $field) {
        $key = $security[$field];

        if (!in_array($security['placeholder'], capturedValuesForKey($decoded, $key), true)) {
            throw new RuntimeException("Persisted fixture key '{$field}' does not contain the redaction placeholder.");
        }
    }

    $controlKey = $security['controlKey'];

    if (!in_array($security['controlValue'], capturedValuesForKey($decoded, $controlKey), true)) {
        throw new RuntimeException('Persisted fixture does not retain its non-sensitive control value.');
    }
}

$options = getopt('', ['app::', 'rows::', 'quiet']);

if ($options === false) {
    throw new RuntimeException('Unable to parse fixture generator options.');
}
$contract = readContract();
$targets = resolveTargets($contract, $options);
$states = $contract['states'] ?? null;

if (!is_array($states) || !is_array($states['empty'] ?? null) || !is_array($states['dense'] ?? null)) {
    throw new RuntimeException("Fixture contract field 'states' is incomplete.");
}

$emptyTag = $states['empty']['tag'] ?? null;
$emptyTime = $states['empty']['timestamp'] ?? null;
$denseTag = $states['dense']['tag'] ?? null;
$denseTime = $states['dense']['timestamp'] ?? null;
$rowCount = $options['rows'] ?? ($states['dense']['rows'] ?? null);
$rowCount = is_string($rowCount) && ctype_digit($rowCount) ? (int) $rowCount : $rowCount;

if (
    !is_string($emptyTag)
    || !is_numeric($emptyTime)
    || !is_string($denseTag)
    || !is_numeric($denseTime)
    || !is_int($rowCount)
    || $rowCount < 1
    || $rowCount > 5_000
) {
    throw new RuntimeException('Fixture state contract contains invalid values.');
}

$sensitiveFixture = sensitiveFixture($contract);

foreach ($targets as $target) {
    $store = new SnapshotStore($target['path'] . '/runtime/debug', 0o700, 0o600);
    $manifest = $store->loadManifestResult();

    if ($manifest->error !== null) {
        throw new RuntimeException(
            "Unable to preserve existing captures in {$target['path']}/runtime/debug.",
            0,
            $manifest->error,
        );
    }

    $historySize = max(200, count($manifest->entries) + 2);
    $empty = snapshot(
        $emptyTag,
        $target['baseURL'] . '/quality-fixture/empty',
        (float) $emptyTime,
        emptyPanels((float) $emptyTime),
        204,
    );
    $dense = snapshot(
        $denseTag,
        $target['baseURL'] . '/quality-fixture/dense',
        (float) $denseTime,
        densePanels((float) $denseTime, $rowCount, $sensitiveFixture),
        200,
    );

    // Upsert the stable tags without deleting developers' live snapshots.
    $store->writeSnapshot($empty, $historySize);
    $store->writeSnapshot($dense, $historySize);
    assertPersistedRedaction($target['path'] . "/runtime/debug/{$denseTag}.json", $contract);

    if (!isset($options['quiet'])) {
        printf(
            "%s: seeded %s and %s (%d dense rows) in %s\n",
            $target['name'],
            $emptyTag,
            $denseTag,
            $rowCount,
            $target['path'] . '/runtime/debug',
        );
    }
}
