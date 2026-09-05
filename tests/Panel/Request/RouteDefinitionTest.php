<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Request;

use InvalidArgumentException;
use PHPForge\Debug\Panel\Request\Routing\RouteDefinition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the shared persistence-safe route definition.
 */
#[Group('panel')]
#[Group('request')]
#[Group('routing')]
final class RouteDefinitionTest extends TestCase
{
    public function testArrayRoundTripAppendsSupportedYiiTwoFields(): void
    {
        $definition = RouteDefinition::create(pattern: 'post/<id:\\d+>')
            ->withMethods(['GET', 'HEAD'])
            ->withTarget('post/view')
            ->withAction(null)
            ->withMiddlewares(null)
            ->withSuffix('.html')
            ->withMode('BOTH')
            ->withType('yii\\web\\UrlRule');

        self::assertEquals(
            $definition,
            RouteDefinition::fromArray($definition->toArray()),
            'Supported Yii 2 fields must survive strict persistence.',
        );
        self::assertSame(
            ['target', 'suffix', 'mode', 'type'],
            array_keys(array_slice($definition->toArray(), 6, null, true)),
            'Yii 2 fields must follow the compatible six-key base shape.',
        );
    }

    public function testArrayRoundTripKeepsExistingYiiThreeShapeCompact(): void
    {
        $data = [
            'name' => 'article/view',
            'pattern' => '/articles/{id}',
            'methods' => ['GET'],
            'hosts' => ['api.example.test'],
            'action' => 'App\\ArticleAction',
            'middlewares' => ['App\\Authentication'],
        ];

        self::assertSame(
            $data,
            RouteDefinition::fromArray($data)->toArray(),
            'Existing six-key Yii 3 route metadata must round-trip without schema expansion.',
        );
    }


    public function testEveryOptionReturnsAnIndependentCopy(): void
    {
        $definition = RouteDefinition::create('orders', '/orders');

        foreach (
            [
                $definition->withMethods(['GET']),
                $definition->withHosts(['example.test']),
                $definition->withAction('OrderAction'),
                $definition->withMiddlewares(['Auth']),
                $definition->withTarget('orders/index'),
                $definition->withSuffix('.json'),
                $definition->withMode('BOTH'),
                $definition->withType('rule'),
            ] as $copy) {
            self::assertNotSame(
                $definition,
                $copy,
                'Every route option must return a separate definition.',
            );
        }

        self::assertEquals(
            RouteDefinition::create('orders', '/orders'),
            $definition,
            'Configuring route options must not modify the original definition.',
        );
    }

    public function testFluentOptionsPreserveIdentityAndCanResetOptionalMetadata(): void
    {
        $definition = RouteDefinition::create('orders', '/orders')
            ->withMethods(['GET'])
            ->withHosts(['example.test'])
            ->withAction('OrderAction')
            ->withMiddlewares(['Auth'])
            ->withTarget('orders/index')
            ->withSuffix('.json')
            ->withMode('BOTH')
            ->withType('rule');

        $reset = $definition
            ->withMethods([])
            ->withHosts([])
            ->withAction(null)
            ->withMiddlewares(null)
            ->withTarget(null)
            ->withSuffix(null)
            ->withMode(null)
            ->withType(null);

        self::assertSame(
            'orders',
            $definition->getName(),
            'Fluent options must preserve the route name.',
        );
        self::assertSame(
            '/orders',
            $definition->getPattern(),
            'Fluent options must preserve the route pattern.',
        );
        self::assertSame(
            ['GET'],
            $definition->getMethods(),
            'Resetting a copy must preserve original methods.',
        );
        self::assertSame(
            ['example.test'],
            $definition->getHosts(),
            'Resetting a copy must preserve original hosts.',
        );
        self::assertSame(
            'OrderAction',
            $definition->getAction(),
            'Resetting a copy must preserve the original action.',
        );
        self::assertSame(
            ['Auth'],
            $definition->getMiddlewares(),
            'Resetting a copy must preserve middleware order.',
        );
        self::assertSame(
            'orders/index',
            $definition->getTarget(),
            'Later options must preserve the target.',
        );
        self::assertSame(
            '.json',
            $definition->getSuffix(),
            'Later options must preserve the suffix.',
        );
        self::assertSame(
            'BOTH',
            $definition->getMode(),
            'Later options must preserve the mode.',
        );
        self::assertSame(
            'rule',
            $definition->getType(),
            'The configured rule type must remain inspectable.',
        );
        self::assertEquals(
            RouteDefinition::create('orders', '/orders'),
            $reset,
            'Every optional route field must support resetting to its unavailable or unrestricted default.',
        );
        self::assertSame(
            [],
            $definition->withMiddlewares([])->getMiddlewares(),
            'An empty middleware list is supported.',
        );
        self::assertNull(
            $reset->getMiddlewares(),
            'Unavailable middleware metadata must remain distinct from no middleware.',
        );
    }

    public function testFromArrayRejectsEveryMissingRequiredField(): void
    {
        foreach (
            [
                'name' => 'a string',
                'pattern' => 'a string',
                'methods' => 'a list of strings',
                'hosts' => 'a list of strings',
                'action' => 'a string or null',
                'middlewares' => 'a list of strings or null',
            ] as $key => $expected
        ) {
            $data = self::validData();
            unset($data[$key]);

            try {
                RouteDefinition::fromArray($data);
                self::fail(
                    "Missing '{$key}' must be rejected.",
                );
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString(
                    "key '{$key}' must be {$expected}",
                    $exception->getMessage(),
                    "Missing '{$key}' must identify its required type.",
                );
            }
        }
    }

    public function testFromArrayRejectsInvalidRequiredAndOptionalValues(): void
    {
        foreach (
            [
                ['name', null, "key 'name' must be a string"],
                ['methods', ['GET', 2], "key 'methods' must be a list of strings"],
                ['hosts', ['host' => 'example.test'], "key 'hosts' must be a list of strings"],
                ['action', 2, "key 'action' must be a string or null"],
                ['middlewares', 'middleware', "key 'middlewares' must be a list of strings"],
                ['target', [], "key 'target' must be a string or null"],
            ] as [$key, $value, $message]
        ) {
            try {
                RouteDefinition::fromArray([...self::validData(), $key => $value]);

                self::fail(
                    "Invalid '{$key}' must be rejected.",
                );
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString(
                    $message,
                    $exception->getMessage(),
                    "Invalid '{$key}' must identify its expected type.",
                );
            }
        }
    }

    public function testFromArrayRejectsNumericFieldsWithADiagnosticException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "key '0' must be a declared field",
        );

        $data = self::validData();

        $data[0] = 'unexpected';

        RouteDefinition::fromArray($data);
    }

    public function testFromArrayRejectsUnknownFields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "key 'extra' must be a declared field",
        );

        RouteDefinition::fromArray(
            [
                ...self::validData(),
                'extra' => true,
            ],
        );
    }

    public function testListsDoNotExposeWritableArrayState(): void
    {
        $values = ['GET'];

        $definition = RouteDefinition::create()
            ->withMethods($values)
            ->withHosts($values)
            ->withMiddlewares($values);

        $values[] = 'POST';
        $methods = $definition->getMethods();
        $hosts = $definition->getHosts();
        $middlewares = $definition->getMiddlewares();
        $methods[] = 'HEAD';
        $hosts[] = 'example.test';
        $middlewares[] = 'Auth';

        self::assertSame(
            ['GET'],
            $definition->getMethods(),
            'Method lists must remain isolated from external edits.',
        );
        self::assertSame(
            ['GET'],
            $definition->getHosts(),
            'Host lists must remain isolated from external edits.',
        );
        self::assertSame(
            ['GET'],
            $definition->getMiddlewares(),
            'Middleware lists must remain isolated from external edits.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function validData(): array
    {
        return [
            'name' => 'home',
            'pattern' => '/',
            'methods' => ['GET'],
            'hosts' => [],
            'action' => null,
            'middlewares' => [],
        ];
    }
}
