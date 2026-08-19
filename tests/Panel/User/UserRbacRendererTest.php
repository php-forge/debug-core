<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\User;

use PHPForge\Debug\Panel\User\UserRbacRenderer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see UserRbacRenderer} freezing RBAC section ordering and missing-category behavior.
 */
#[Group('panel')]
#[Group('user')]
final class UserRbacRendererTest extends TestCase
{
    public function testRenderOmitsOnlyUncapturedSections(): void
    {
        self::assertSame(
            <<<HTML
            <h2>
            Permissions
            </h2>
            HTML,
            UserRbacRenderer::render(null, ''),
            'A null category must be omitted while a captured empty category keeps its heading.',
        );
    }
    public function testRenderReturnsBothSectionsInCanonicalOrder(): void
    {
        self::assertSame(
            <<<HTML
            <h2>
            Roles
            </h2><table id="roles"></table><h2>
            Permissions
            </h2><table id="permissions"></table>
            HTML,
            UserRbacRenderer::render(
                '<table id="roles"></table>',
                '<table id="permissions"></table>',
            ),
            'Roles must precede Permissions with the canonical headings.',
        );
    }
}
