<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\User;

use PHPForge\Debug\{ColumnStyle, PanelView, Tone};
use PHPForge\Debug\Panel\User\{UserPanel, UserSnapshot};
use PHPForge\Debug\Presenter\{BadgeInline, Block, FactEntry, OverviewBlock, TextInline, TextStyle, ToolbarMetric};
use PHPForge\Debug\Tests\Support\PanelViewAccessors;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function array_map;
use function date;
use function time;

/**
 * Unit tests for {@see UserPanel} covering the identity hero, attribute sections, and the RBAC sections.
 */
#[Group('panel')]
#[Group('user')]
final class UserPanelTest extends TestCase
{
    use PanelViewAccessors;

    /**
     * @var int Capture timestamp shared by the RBAC rows.
     */
    private const int TIME = 1_757_700_000;

    public function testAuthenticatedIdentityDescribesItsHeroAndAttributeSections(): void
    {
        $recent = time();

        $capture = [
            'identity' => [
                'id' => '1',
                'username' => 'admin',
                'name' => 'Administrator',
                'email' => 'admin@example.com',
                'status' => '10',
                'auth_key' => 'secret-auth-key',
                'created_at' => (string) self::TIME,
                'updated_at' => (string) $recent,
                'timezone' => 'UTC',
                'blank' => '',
                'logins' => 3,
            ],
            'attributes' => [
                'malformed',
                ['attribute' => 'timezone', 'label' => 'Time zone'],
                ['attribute' => 'blank', 'label' => 42],
                ['attribute' => 'auth_key', 'label' => 'Security key'],
            ],
            'roles' => null,
            'permissions' => null,
        ];

        $view = self::present($capture);

        self::assertSame(
            [],
            $view->summaryMetrics(),
            'The hero must replace the summary strip.',
        );
        self::assertEquals(
            [new ToolbarMetric('User', 'admin')],
            $view->toolbarMetrics(),
            'The toolbar must name the authenticated user.',
        );

        $hero = self::hero(self::blockAt($view, 0));

        self::assertSame(
            ['A', 'admin', 'admin@example.com'],
            [$hero->mark, $hero->title, $hero->subtitle],
            'The hero must carry the monogram, the username, and the email.',
        );
        self::assertEquals(
            new BadgeInline('Active', Tone::SUCCESS),
            $hero->status,
            'An active account must carry a success status.',
        );
        self::assertEquals(
            [new FactEntry('User ID', '1'), new FactEntry('Roles', '—'), new FactEntry('Permissions', '—')],
            $hero->metrics,
            'Metrics must keep their order and mark an RBAC lookup that was not captured.',
        );
        self::assertSame(
            [
                ['::', 'Identity', null],
                ['::', 'Security', null],
                ['::', 'Timestamps', null],
                ['::', 'Other attributes', null],
            ],
            array_map(
                static fn(int $index): array => [
                    self::section(self::blockAt($view, $index))->mark,
                    self::section(self::blockAt($view, $index))->title,
                    self::section(self::blockAt($view, $index))->count,
                ],
                [1, 2, 3, 4],
            ),
            'Every populated attribute bucket must open its own section, in order.',
        );
        self::assertSame(
            ['Name' => 'Administrator'],
            self::textFields(self::sectionOverview($view, 1)),
            'Identity must keep only what the hero does not show.',
        );
        self::assertTrue(
            self::sectionOverview($view, 1)->compact,
            'Each attribute section must use the compact presentation.',
        );
        self::assertEquals(
            new TextInline('secret-auth-key', TextStyle::PREVIEW),
            self::fields(self::sectionOverview($view, 2))['Security key']
                ?? self::fail('The security section must keep the auth key row.'),
            'The redacted key must carry the preview style.',
        );
        self::assertSame(
            date('M j, Y · H:i', self::TIME),
            self::textValue(
                self::fields(self::sectionOverview($view, 3))['Created At']
                    ?? self::fail('The timestamp section must keep the creation row.'),
            ),
            'Past the relative scale the absolute form must not be repeated.',
        );
        self::assertSame(
            date('M j, Y · H:i', $recent) . ' · just now',
            self::textValue(
                self::fields(self::sectionOverview(self::present($capture), 3))['Updated At']
                    ?? self::fail('The timestamp section must keep the update row.'),
            ),
            'Inside the relative scale both forms must be shown.',
        );

        $other = self::fields(self::sectionOverview($view, 4));

        self::assertSame(
            ['Time zone', 'Blank'],
            array_keys($other),
            'A value the collector did not render as text must be dropped.',
        );
        self::assertSame(
            'UTC',
            self::textValue($other['Time zone'] ?? self::fail('The other section must keep the time zone row.')),
            'A captured label must replace the computed one.',
        );
        self::assertSame(
            '—',
            self::textValue($other['Blank'] ?? self::fail('The other section must keep the blank row.')),
            'An empty attribute must show the placeholder.',
        );
    }

    public function testGuestRequestExplainsTheMissingIdentity(): void
    {
        $view = self::present(['identity' => null, 'attributes' => null, 'roles' => null, 'permissions' => null]);

        self::assertSame(
            [],
            $view->summaryMetrics(),
            'A guest request must carry no summary metric.',
        );

        $state = self::emptyState(self::blockAt($view, 0));

        self::assertCount(
            1,
            $view->blocks(),
            'A guest request must replace every section.',
        );
        self::assertSame(
            'No authenticated user',
            $state->title,
            'The empty state must keep its heading.',
        );
        self::assertSame(
            ['This request ran as a guest, so the debugger captured no identity to inspect.'],
            self::inlineValues($state->paragraphs[0] ?? self::fail('The empty state must explain itself.')),
            'The first paragraph must describe the guest request.',
        );
        self::assertSame(
            ['Sign in and reload the page; the identity appears here once the application resolves it.'],
            self::inlineValues($state->paragraphs[1] ?? self::fail('The empty state must end with a call to action.')),
            'The call to action must name no framework API.',
        );
    }

    public function testMetadataMatchesTheBuiltInUserPanel(): void
    {
        $panel = new UserPanel();

        self::assertSame(
            'user',
            $panel->id(),
            'The persisted panel identifier must stay stable.',
        );
        self::assertSame(
            'User',
            $panel->name(),
            'The navigation title must stay stable.',
        );
        self::assertSame(
            'user',
            $panel->icon(),
            'The panel must reuse the existing icon.',
        );
    }

    public function testRbacItemsAreListedWithTheirRuleDataAndTimestamps(): void
    {
        $view = self::present(
            [
                'identity' => ['username' => 'admin'],
                'attributes' => null,
                'roles' => [
                    [
                        'name' => 'admin',
                        'description' => 'Administrator',
                        'ruleName' => 'isAuthor',
                        'data' => '{"level":1}',
                        'createdAt' => self::TIME,
                        'updatedAt' => self::TIME,
                    ],
                    'malformed',
                ],
                'permissions' => [['name' => 'viewUsers']],
            ],
        );

        self::assertEquals(
            [new FactEntry('User ID', '—'), new FactEntry('Roles', 'admin'), new FactEntry('Permissions', '1')],
            self::hero(self::blockAt($view, 0))->metrics,
            'Metrics must name the granted roles and count the permissions.',
        );

        $rolesSection = self::section(self::blockAt($view, 1));

        self::assertSame(
            ['::', 'Roles', 2],
            [$rolesSection->mark, $rolesSection->title, $rolesSection->count],
            'The roles section must report the item count.',
        );

        $roles = self::table(self::sectionBlock($view, 1));

        self::assertSame(
            ['#', 'Name', 'Description', 'Rule', 'Data', 'Created', 'Updated'],
            $roles->headers,
            'The RBAC column order must stay stable.',
        );
        self::assertSame(
            [
                0 => ColumnStyle::NUMBER,
                1 => ColumnStyle::IDENTIFIER,
                3 => ColumnStyle::IDENTIFIER,
                4 => ColumnStyle::MONOSPACE,
                5 => ColumnStyle::IDENTIFIER,
                6 => ColumnStyle::IDENTIFIER,
            ],
            $roles->styles,
            'Each style must stay attached to the column it formats.',
        );
        self::assertTrue(
            $roles->collapsible,
            'A long role list must stay collapsible.',
        );
        self::assertSame(
            [
                '1',
                'admin',
                'Administrator',
                'isAuthor',
                '{"level":1}',
                date('M j, Y · H:i:s', self::TIME),
                date('M j, Y · H:i:s', self::TIME),
            ],
            self::textValues(self::row($roles, 0)),
            'Every RBAC column must survive the migration.',
        );
        self::assertSame(
            ['2', '—', '—', '—', '—', '—', '—'],
            self::textValues(self::row($roles, 1)),
            'A malformed RBAC row must fall back to placeholders.',
        );
        self::assertSame(
            ['Permissions', 1],
            [self::section(self::blockAt($view, 2))->title, self::section(self::blockAt($view, 2))->count],
            'The permissions section must report the item count.',
        );
        self::assertSame(
            'viewUsers',
            self::textValue(
                self::row(self::table(self::sectionBlock($view, 2)), 0)[1]
                    ?? self::fail('Every permission must be listed.'),
            ),
            'Permissions must be listed separately from roles.',
        );
    }

    public function testUnknownStatusAndMissingRbacFallBackToNeutralCopy(): void
    {
        $view = self::present(
            ['identity' => ['username' => 'ghost'], 'attributes' => null, 'roles' => [], 'permissions' => null],
        );
        $hero = self::hero(self::blockAt($view, 0));

        self::assertEquals(
            new BadgeInline('Unknown', Tone::MUTED),
            $hero->status,
            'An identity without status must read as a de-emphasized unknown.',
        );
        self::assertSame(
            '',
            $hero->subtitle,
            'An identity without email must omit the subtitle.',
        );
        self::assertEquals(
            [new FactEntry('User ID', '—'), new FactEntry('Roles', '—'), new FactEntry('Permissions', '—')],
            $hero->metrics,
            'Missing values must show the placeholder.',
        );
        self::assertSame(
            ['Roles', 0],
            [self::section(self::blockAt($view, 1))->title, self::section(self::blockAt($view, 1))->count],
            'The roles section must report an empty grant.',
        );
        self::assertSame(
            ['The auth manager granted no roles to this identity.'],
            self::inlineValues(self::paragraph(self::sectionBlock($view, 1))),
            'An empty role grant must be explained.',
        );
        self::assertSame(
            ['The auth manager granted no permissions to this identity.'],
            self::inlineValues(self::paragraph(self::sectionBlock($view, 2))),
            'An empty permission grant must be explained.',
        );
    }

    /**
     * Presents a plain identity payload through the panel under test.
     *
     * @param array<string, mixed> $payload Identity captured for the request.
     *
     * @return PanelView Description built by the panel.
     */
    private static function present(array $payload): PanelView
    {
        return (new UserPanel())->present(UserSnapshot::capture($payload)->jsonSerialize());
    }

    /**
     * Returns the only block a section of the view wraps.
     *
     * @param PanelView $view Description built by the panel.
     * @param int $index Position of the section in the view.
     *
     * @return Block Block the section wraps.
     */
    private static function sectionBlock(PanelView $view, int $index): Block
    {
        return self::section(self::blockAt($view, $index))->content->blocks()[0]
            ?? self::fail('The section must wrap a block.');
    }

    /**
     * Returns the overview an attribute section of the view wraps.
     *
     * @param PanelView $view Description built by the panel.
     * @param int $index Position of the section in the view.
     *
     * @return OverviewBlock Overview the section wraps.
     */
    private static function sectionOverview(PanelView $view, int $index): OverviewBlock
    {
        return self::overview(self::sectionBlock($view, $index));
    }
}
