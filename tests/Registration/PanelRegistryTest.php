<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Registration;

use InvalidArgumentException;
use PHPForge\Debug\Registration\{PanelOverride, PanelRegistration, PanelRegistry};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * Unit tests for {@see PanelRegistry} and {@see PanelRegistration} covering override merging, display order, and
 * disabled entries.
 */
#[Group('registration')]
final class PanelRegistryTest extends TestCase
{
    public function testDisabledIdsAreSortedAlphabetically(): void
    {
        $registry = PanelRegistry::resolve(
            [
                PanelRegistration::extension('vite', 'Vite', 'brand-javascript'),
                PanelRegistration::extension('cache', 'Cache', 'db'),
            ],
            [
                'vite' => PanelOverride::fromArray(['enabled' => false]),
                'cache' => PanelOverride::fromArray(['enabled' => false]),
                'inertia' => PanelOverride::fromArray(['enabled' => false]),
            ],
        );

        self::assertSame(['cache', 'inertia', 'vite'], $registry->disabled(), 'Order must be alphabetical.');
    }

    public function testDisabledOverrideRemovesTheEntryFromTheDisplayOrder(): void
    {
        $registry = PanelRegistry::resolve(
            [
                PanelRegistration::builtIn('request', 'Request', 'request'),
                PanelRegistration::extension('vite', 'Vite', 'brand-javascript'),
                PanelRegistration::extension('cache', 'Cache', 'db'),
            ],
            ['vite' => PanelOverride::fromArray(['enabled' => false])],
        );

        self::assertSame(['request', 'cache'], self::ids($registry), 'Display order must skip the entry.');
        self::assertTrue($registry->isDisabled('vite'), 'Disabling must be readable by ID.');
        self::assertContains('vite', $registry->disabled(), 'Disabled ID must be listed.');
        self::assertFalse($registry->isDisabled('cache'), 'A sibling must stay enabled.');
    }

    public function testDisabledOverrideWithoutDefaultIsAcceptedAsAnUninstalledPackage(): void
    {
        $registry = PanelRegistry::resolve(
            [PanelRegistration::builtIn('request', 'Request', 'request')],
            ['inertia' => PanelOverride::fromArray(['enabled' => false])],
        );

        self::assertSame(['inertia'], $registry->disabled(), 'An absent package must still be listed.');
        self::assertTrue($registry->isDisabled('inertia'), 'Disabling must be readable by ID.');
        self::assertNull($registry->get('inertia'), 'An entry with no default must not resolve.');
        self::assertSame(['request'], self::ids($registry), 'Built-ins must stay untouched.');
    }

    public function testEnabledBreaksEqualExtensionPositionsByTitleThenId(): void
    {
        $registry = PanelRegistry::resolve(
            [
                PanelRegistration::extension('vite', 'Vite', 'brand-javascript'),
                PanelRegistration::extension('cache', 'Cache', 'db'),
                PanelRegistration::extension('queue', 'Cache', 'db'),
                PanelRegistration::extension('inertia', 'Inertia', 'inertia'),
            ],
            [
                'vite' => PanelOverride::fromArray(['position' => 1]),
                'cache' => PanelOverride::fromArray(['position' => 1]),
                'queue' => PanelOverride::fromArray(['position' => 1]),
                'inertia' => PanelOverride::fromArray(['position' => 1]),
            ],
        );

        self::assertSame(
            ['cache', 'queue', 'inertia', 'vite'],
            self::ids($registry),
            'Ties must break by title, then by ID.',
        );
    }

    public function testEnabledBreaksEqualTitlesByIdAgainstTheRegistrationOrder(): void
    {
        $registry = PanelRegistry::resolve(
            [
                PanelRegistration::extension('zulu', 'Cache', 'db'),
                PanelRegistration::extension('alpha', 'Cache', 'db'),
            ],
        );

        self::assertSame(['alpha', 'zulu'], self::ids($registry), 'ID must break an exact title tie.');
    }

    public function testEnabledComparesExtensionTitlesCaseInsensitively(): void
    {
        $registry = PanelRegistry::resolve(
            [
                PanelRegistration::extension('vite', 'Vite', 'brand-javascript'),
                PanelRegistration::extension('cache', 'cache', 'db'),
                PanelRegistration::extension('inertia', 'Inertia', 'inertia'),
            ],
        );

        self::assertSame(['cache', 'Inertia', 'Vite'], self::titles($registry), 'Letter case must not affect order.');
    }

    public function testEnabledKeepsBuiltInOrderBeforeEveryExtension(): void
    {
        $registry = PanelRegistry::resolve(
            [
                ...self::builtIns(),
                PanelRegistration::extension('vite', 'Vite', 'brand-javascript'),
                PanelRegistration::extension('cache', 'Cache', 'db'),
            ],
        );

        self::assertSame(
            ['request', 'log', 'event', 'profiling', 'cache', 'vite'],
            self::ids($registry),
            'Built-ins must keep their registration order.',
        );
        self::assertSame(
            [false, false, false, false, true, true],
            array_map(static fn(PanelRegistration $panel): bool => $panel->extension, $registry->enabled()),
            'Every extension must follow every built-in.',
        );
    }

    public function testEnabledOrdersExtensionsByTitleBeforeId(): void
    {
        $registry = PanelRegistry::resolve(
            [
                PanelRegistration::extension('alpha', 'Zulu', 'db'),
                PanelRegistration::extension('zulu', 'Alpha', 'db'),
            ],
        );

        self::assertSame(['zulu', 'alpha'], self::ids($registry), 'Title must outrank ID.');
    }

    public function testEnabledOrdersPositionedExtensionsByPositionBeforeTitle(): void
    {
        $registry = PanelRegistry::resolve(
            [
                PanelRegistration::extension('alpha', 'Alpha', 'db'),
                PanelRegistration::extension('zulu', 'Zulu', 'db'),
            ],
            [
                'alpha' => PanelOverride::fromArray(['position' => 2]),
                'zulu' => PanelOverride::fromArray(['position' => 1]),
            ],
        );

        self::assertSame(['zulu', 'alpha'], self::ids($registry), 'Position must outrank title.');
    }

    public function testEnabledPlacesPositionedExtensionsBeforeTheAlphabeticalRest(): void
    {
        $registry = PanelRegistry::resolve(
            [
                PanelRegistration::extension('vite', 'Vite', 'brand-javascript'),
                PanelRegistration::extension('inertia', 'Inertia', 'inertia'),
                PanelRegistration::extension('cache', 'Cache', 'db'),
            ],
            [
                'vite' => PanelOverride::fromArray(['position' => 2]),
                'inertia' => PanelOverride::fromArray(['position' => 1]),
            ],
        );

        self::assertSame(
            ['Inertia', 'Vite', 'Cache'],
            self::titles($registry),
            'Positioned entries must lead, ascending.',
        );
    }

    public function testEnabledSortsExtensionsAlphabeticallyByTitleWithoutOverrides(): void
    {
        $registry = PanelRegistry::resolve(
            [
                PanelRegistration::extension('vite', 'Vite', 'brand-javascript'),
                PanelRegistration::extension('cache', 'Cache', 'db'),
                PanelRegistration::extension('inertia', 'Inertia', 'inertia'),
            ],
        );

        self::assertSame(
            ['Cache', 'Inertia', 'Vite'],
            self::titles($registry),
            'Registration order must not leak into the display order.',
        );
    }

    public function testExtensionAcceptsAnEmptyIconKeyAndAValidOne(): void
    {
        $iconless = PanelRegistration::extension('vite', 'Vite', '');
        $iconed = PanelRegistration::extension('cache', 'Cache', 'db');

        self::assertSame('', $iconless->icon, 'An icon-less panel must be accepted.');
        self::assertSame('db', $iconed->icon, 'A valid key must survive verbatim.');
    }

    public function testGetReturnsNullForAnUnknownId(): void
    {
        $registry = PanelRegistry::resolve([PanelRegistration::builtIn('request', 'Request', 'request')]);

        self::assertNull($registry->get('unknown'), 'An unregistered ID must resolve to `null`.');
        self::assertFalse($registry->isDisabled('unknown'), 'An unregistered ID must not count as disabled.');
        self::assertSame([], $registry->disabled(), 'Nothing must be listed without disabling configuration.');
    }

    public function testResolveAppliesTitleAndIconOverridesAndPreservesTheId(): void
    {
        $registry = PanelRegistry::resolve(
            [
                PanelRegistration::extension('vite', 'Vite', 'brand-javascript'),
                PanelRegistration::extension('cache', 'Cache', 'db'),
            ],
            ['vite' => PanelOverride::fromArray(['title' => 'Vite assets', 'icon' => 'asset'])],
        );

        $vite = $registry->get('vite');
        $cache = $registry->get('cache');

        self::assertInstanceOf(PanelRegistration::class, $vite, 'The overridden entry must stay registered.');
        self::assertSame('vite', $vite->id, 'The stable key must survive a rename.');
        self::assertSame('Vite assets', $vite->title, 'The configured title must win.');
        self::assertSame('asset', $vite->icon, 'The configured icon must win.');
        self::assertInstanceOf(PanelRegistration::class, $cache, 'The untouched entry must stay registered.');
        self::assertSame('Cache', $cache->title, 'The provider default title must survive.');
        self::assertSame('db', $cache->icon, 'The provider default icon must survive.');
    }

    public function testThrowInvalidArgumentExceptionForDuplicateDefaultId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('vite');

        PanelRegistry::resolve(
            [
                PanelRegistration::extension('vite', 'Vite', 'brand-javascript'),
                PanelRegistration::extension('vite', 'Vite assets', 'asset'),
            ],
        );
    }

    public function testThrowInvalidArgumentExceptionForEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PanelRegistration::extension('', 'X', 'db');
    }

    public function testThrowInvalidArgumentExceptionForEmptyTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PanelRegistration::extension('x', '', 'db');
    }

    public function testThrowInvalidArgumentExceptionForIdWithSurroundingWhitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not have surrounding whitespace');

        PanelRegistration::extension(' vite ', 'X', 'db');
    }

    public function testThrowInvalidArgumentExceptionForInvalidIconKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bad Key.svg');

        PanelRegistration::extension('vite', 'Vite', 'Bad Key.svg');
    }

    public function testThrowInvalidArgumentExceptionForOverrideWithoutDefault(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ghost');

        PanelRegistry::resolve(
            [PanelRegistration::builtIn('request', 'Request', 'request')],
            ['ghost' => PanelOverride::fromArray(['title' => 'X'])],
        );
    }

    public function testThrowInvalidArgumentExceptionForPositionOnBuiltIn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('position');

        PanelRegistry::resolve(self::builtIns(), ['log' => PanelOverride::fromArray(['position' => 1])]);
    }

    public function testThrowInvalidArgumentExceptionForWhitespaceOnlyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not be empty');

        PanelRegistration::extension(' ', 'X', 'db');
    }

    public function testWithOverrideReplacesTheDefaultPositionOnlyWhenConfigured(): void
    {
        $positioned = new PanelRegistration('vite', 'Vite', 'brand-javascript', true, 5);

        self::assertSame(
            2,
            $positioned->withOverride(PanelOverride::fromArray(['position' => 2]))->position,
            'A configured position must replace the default.',
        );
        self::assertSame(
            5,
            $positioned->withOverride(PanelOverride::fromArray(['title' => 'Vite assets']))->position,
            'An absent position must keep the default.',
        );
    }

    public function testWithOverrideReturnsANewInstanceHoldingTheMergedValues(): void
    {
        $original = PanelRegistration::extension('vite', 'Vite', 'brand-javascript');

        $merged = $original->withOverride(
            PanelOverride::fromArray(['title' => 'Vite assets', 'icon' => 'asset', 'position' => 2]),
        );

        self::assertNotSame($original, $merged, 'Merging must not mutate in place.');
        self::assertSame('Vite', $original->title, 'The source title must survive.');
        self::assertSame('brand-javascript', $original->icon, 'The source icon must survive.');
        self::assertNull($original->position, 'The source position must stay `null`.');
        self::assertSame('vite', $merged->id, 'The stable key must survive the merge.');
        self::assertSame('Vite assets', $merged->title, 'The merged title must win.');
        self::assertSame('asset', $merged->icon, 'The merged icon must win.');
        self::assertSame(2, $merged->position, 'The merged position must win.');
        self::assertTrue($merged->extension, 'The grouping flag must survive the merge.');
    }

    /**
     * Creates the host built-ins in their fixed display order.
     *
     * @return list<PanelRegistration> Built-in registrations with provider defaults.
     */
    private static function builtIns(): array
    {
        return [
            PanelRegistration::builtIn('request', 'Request', 'request'),
            PanelRegistration::builtIn('log', 'Logs', 'log'),
            PanelRegistration::builtIn('event', 'Events', 'event'),
            PanelRegistration::builtIn('profiling', 'Profiling', 'profiling'),
        ];
    }

    /**
     * Extracts the stable keys from the resolved display order.
     *
     * @param PanelRegistry $registry Resolved registry.
     *
     * @return list<string> Panel IDs in display order.
     */
    private static function ids(PanelRegistry $registry): array
    {
        return array_map(static fn(PanelRegistration $panel): string => $panel->id, $registry->enabled());
    }

    /**
     * Extracts the effective titles from the resolved display order.
     *
     * @param PanelRegistry $registry Resolved registry.
     *
     * @return list<string> Panel titles in display order.
     */
    private static function titles(PanelRegistry $registry): array
    {
        return array_map(static fn(PanelRegistration $panel): string => $panel->title, $registry->enabled());
    }
}
