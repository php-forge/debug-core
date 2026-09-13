<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\User;

use PHPForge\Debug\{ColumnStyle, PanelView, Tone};
use PHPForge\Debug\Panel\User\{UserPanel, UserSnapshot};
use PHPForge\Debug\Tests\Support\PanelViewAccessors;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function date;
use function time;

/**
 * Unit tests for {@see UserPanel} covering the identity overview, attribute sections, and the RBAC tables.
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
                'email' => 'admin@example.com',
                'status' => '10',
                'auth_key' => 'secret-auth-key',
                'created_at' => (string) self::TIME,
                'updated_at' => (string) $recent,
                'timezone' => 'UTC',
                'blank' => '',
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

        self::assertTrue(
            $view->isActive(),
            'A captured identity must activate navigation.',
        );
        self::assertSame(
            ['admin'],
            self::metricValues($view->summaryMetrics()),
            'The summary must name the authenticated user.',
        );
        self::assertSame(
            [['label' => 'User', 'value' => ['kind' => 'text', 'value' => 'admin', 'style' => 'plain']]],
            $view->toolbarMetrics(),
            'The toolbar must name the authenticated user.',
        );

        $hero = self::overview(self::blockAt($view, 0));

        self::assertTrue(
            $hero['compact'],
            'The identity overview must use the compact presentation.',
        );
        self::assertSame(
            ['User', 'Email', 'User ID', 'Status'],
            array_keys(self::fields($hero)),
            'The identity row order must stay stable.',
        );

        $status = self::badge(self::fields($hero)['Status'] ?? self::fail('The overview must keep the status row.'));

        self::assertSame(
            'Active',
            $status['label'],
            'A known status must be labeled.',
        );
        self::assertSame(
            Tone::SUCCESS,
            $status['tone'],
            'An active account must use the success tone.',
        );
        self::assertSame(
            ['Identity', 'Security', 'Timestamps', 'Other attributes'],
            [
                self::heading(self::blockAt($view, 1))['title'],
                self::heading(self::blockAt($view, 3))['title'],
                self::heading(self::blockAt($view, 5))['title'],
                self::heading(self::blockAt($view, 7))['title'],
            ],
            'Every populated attribute section must keep its heading, in order.',
        );
        self::assertTrue(
            self::heading(self::blockAt($view, 1))['section'],
            'Each attribute section must open a section-level heading.',
        );
        self::assertTrue(
            self::overview(self::blockAt($view, 2))['compact'],
            'Each attribute section must use the compact presentation.',
        );
        self::assertSame(
            ['kind' => 'text', 'value' => 'secret-auth-key', 'style' => 'preview'],
            self::fields(self::overview(self::blockAt($view, 4)))['Security key']
                ?? self::fail('The security section must keep the auth key row.'),
            'A sensitive attribute must stay clamped behind the standard expand control.',
        );
        self::assertSame(
            date('M j, Y · H:i', self::TIME),
            self::textValue(
                self::fields(self::overview(self::blockAt($view, 6)))['Created At']
                    ?? self::fail('The timestamp section must keep the creation row.'),
            ),
            'Past the relative scale the absolute form must not be repeated.',
        );
        self::assertSame(
            date('M j, Y · H:i', $recent) . ' · just now',
            self::textValue(
                self::fields(self::overview(self::blockAt(self::present($capture), 6)))['Updated At']
                    ?? self::fail('The timestamp section must keep the update row.'),
            ),
            'Inside the relative scale both forms must be shown.',
        );

        $other = self::fields(self::overview(self::blockAt($view, 8)));

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

    public function testGuestRequestDeactivatesThePanelAndExplainsTheMissingIdentity(): void
    {
        $view = self::present(['identity' => null, 'attributes' => null, 'roles' => null, 'permissions' => null]);

        self::assertFalse(
            $view->isActive(),
            'A guest request must not activate navigation.',
        );
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
            $state['title'],
            'The empty state must keep its heading.',
        );
        self::assertSame(
            ['This request ran as a guest, so the debugger captured no identity to inspect.'],
            self::inlineValues($state['paragraphs'][0] ?? self::fail('The empty state must explain itself.')),
            'The first paragraph must describe the guest request.',
        );
        self::assertSame(
            ['Sign in and reload the page; the identity appears here as soon as ', 'Yii::$app->user->identity', ' resolves.'],
            self::inlineValues($state['paragraphs'][1] ?? self::fail('The empty state must name the resolver.')),
            'The resolver explanation must stay complete and ordered.',
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
        $rolesHeading = self::heading(self::blockAt($view, 3));

        self::assertSame(
            'Roles (2)',
            $rolesHeading['title'],
            'The roles heading must report the item count.',
        );
        self::assertTrue(
            $rolesHeading['section'],
            'The roles section must open a section-level heading.',
        );

        $roles = self::table(self::blockAt($view, 4));

        self::assertSame(
            ['#', 'Name', 'Description', 'Rule', 'Data', 'Created', 'Updated'],
            $roles['headers'],
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
            $roles['styles'],
            'Each style must stay attached to the column it formats.',
        );
        self::assertTrue(
            $roles['collapsible'],
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
            'Permissions (1)',
            self::heading(self::blockAt($view, 5))['title'],
            'The permissions heading must report the item count.',
        );
        self::assertSame(
            'viewUsers',
            self::textValue(
                self::row(self::table(self::blockAt($view, 6)), 0)[1]
                    ?? self::fail('Every permission must be listed.'),
            ),
            'Permissions must be listed separately from roles.',
        );
    }

    public function testUnknownStatusAndMissingRbacFallBackToNeutralCopy(): void
    {
        $view = self::present(
            ['identity' => ['username' => 'ghost'], 'attributes' => null, 'roles' => null, 'permissions' => null],
        );
        $status = self::badge(
            self::fields(self::overview(self::blockAt($view, 0)))['Status']
                ?? self::fail('The overview must keep the status row.'),
        );
        $hero = self::fields(self::overview(self::blockAt($view, 0)));

        self::assertSame(
            'Unknown',
            $status['label'],
            'An identity without status must read as unknown.',
        );
        self::assertSame(
            ['—', '—'],
            [
                self::textValue($hero['Email'] ?? self::fail('The overview must keep the email row.')),
                self::textValue($hero['User ID'] ?? self::fail('The overview must keep the identifier row.')),
            ],
            'An identity without email or id must show the placeholder.',
        );
        self::assertSame(
            Tone::MUTED,
            $status['tone'],
            'An unknown status must stay de-emphasized.',
        );
        self::assertSame(
            'Roles (0)',
            self::heading(self::blockAt($view, 3))['title'],
            'The roles heading must report an empty grant.',
        );
        self::assertSame(
            ['The auth manager granted no roles to this identity.'],
            self::inlineValues(self::paragraph(self::blockAt($view, 4))),
            'An empty role grant must be explained.',
        );
        self::assertSame(
            ['The auth manager granted no permissions to this identity.'],
            self::inlineValues(self::paragraph(self::blockAt($view, 6))),
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
}
