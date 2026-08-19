<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\User;

use PHPForge\Debug\Panel\User\UserGuestRenderer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see UserGuestRenderer} freezing the cross-adapter Guest semantics and exact empty-state markup.
 */
#[Group('panel')]
#[Group('user')]
final class UserGuestRendererTest extends TestCase
{
    public function testRenderReturnsTheCompleteGuestState(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-empty-state">
            <h2>
            No user authenticated in this request
            </h2><p>
            The request was served to a guest, so there are no identity attributes, roles, or permissions to inspect.
            </p><p>
            Sign in and reload to inspect the identity. User switching remains unavailable to guests.
            </p>
            </div>
            HTML,
            UserGuestRenderer::render(),
            'Guest rendering must remain identical across framework adapters.',
        );
    }
}
