<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Support;

use PHPUnit\Event\Test\{PreparationStarted, PreparationStartedSubscriber};
use PHPUnit\Event\TestSuite\{Started, StartedSubscriber};
use PHPUnit\Runner\Extension\{Extension, Facade, ParameterCollection};
use PHPUnit\TextUI\Configuration\Configuration;
use ReflectionClass;
use Xepozz\InternalMocker\{Mocker, MockerState};

/**
 * Replaces filesystem functions inside the storage namespace for failure-path tests.
 */
final class MockerExtension implements Extension
{
    /**
     * Registers subscribers that initialize and reset filesystem function mocks.
     *
     * @param Configuration $configuration PHPUnit configuration.
     * @param Facade $facade PHPUnit extension facade.
     * @param ParameterCollection $parameters Extension parameters.
     */
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $facade->registerSubscribers(
            new class implements StartedSubscriber {
                /**
                 * Loads function mocks when the test suite starts.
                 *
                 * @param Started $event Test suite event.
                 */
                public function notify(Started $event): void
                {
                    MockerExtension::load();
                }
            },
            new class implements PreparationStartedSubscriber {
                /**
                 * Resets function mocks before each test.
                 *
                 * @param PreparationStarted $event Test preparation event.
                 */
                public function notify(PreparationStarted $event): void
                {
                    MockerState::resetState();
                    MockerExtension::resetDefaults();
                }
            },
        );
    }

    /**
     * Loads filesystem function stubs for the storage namespace.
     */
    public static function load(): void
    {
        $mocks = [];

        foreach (['file_put_contents', 'flock', 'fopen', 'mkdir', 'rename', 'tempnam'] as $name) {
            $mocks[] = [
                'namespace' => 'PHPForge\Debug\Storage',
                'name' => $name,
            ];
        }

        (new Mocker(stubPath: __DIR__ . '/mocker-stubs.php'))->load($mocks);

        MockerState::saveState();
    }

    /**
     * Clears mock defaults between tests.
     */
    public static function resetDefaults(): void
    {
        $defaults = (new ReflectionClass(MockerState::class))->getProperty('defaults');

        $defaults->setValue(null, []);
    }
}
