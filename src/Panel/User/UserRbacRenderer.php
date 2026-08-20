<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\User;

use UIAwesome\Html\Heading\H2;

use function implode;

/**
 * Composes the shared Roles and Permissions section around adapter-owned grid markup.
 */
final class UserRbacRenderer
{
    /**
     * Renders role and permission grids in their canonical order.
     *
     * A `null` grid means that RBAC category was not captured and omits its heading. An empty string means the category
     * was captured with no rows and keeps the heading so the adapter grid may expose its native empty result state.
     *
     * @param string|null $rolesGrid Trusted adapter-rendered Roles grid markup.
     * @param string|null $permissionsGrid Trusted adapter-rendered Permissions grid markup.
     */
    public static function render(string|null $rolesGrid, string|null $permissionsGrid): string
    {
        $sections = [];

        if ($rolesGrid !== null) {
            $sections[] = H2::tag()
                ->content('Roles')
                ->render() . $rolesGrid;
        }

        if ($permissionsGrid !== null) {
            $sections[] = H2::tag()
                ->content('Permissions')
                ->render() . $permissionsGrid;
        }

        return implode('', $sections);
    }
}
