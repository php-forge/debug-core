<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\User;

use PHPForge\Debug\Helper\EmptyState;
use UIAwesome\Html\Flow\P;

/**
 * Renders the framework-neutral Guest state shared by User panels.
 */
final class UserGuestRenderer
{
    /**
     * Renders the complete Guest empty-state card.
     */
    public static function render(): string
    {
        return EmptyState::card(
            'No user authenticated in this request',
            P::tag()
                ->content(
                    'The request was served to a guest, so there are no identity attributes, roles, or permissions to inspect.',
                ),
            P::tag()
                ->content(
                    'Sign in and reload to inspect the identity. User switching remains unavailable to guests.',
                ),
        );
    }
}
