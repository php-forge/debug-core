<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\User;

/**
 * Identity, formats, and presentation text of the User panel, shared by every adapter that renders it.
 */
enum UserMessage: string
{
    /**
     * Header of the RBAC creation-time column.
     */
    case CREATED = 'Created';

    /**
     * Header of the RBAC business-data column.
     */
    case DATA = 'Data';

    /**
     * `date()` format of the absolute timestamps in the RBAC tables.
     */
    case DATE_FORMAT = 'M j, Y · H:i:s';

    /**
     * Header of the RBAC description column.
     */
    case DESCRIPTION = 'Description';

    /**
     * Overview field label of the identity email.
     */
    case EMAIL = 'Email';

    /**
     * Explanation of the empty state when the request ran as a guest.
     */
    case EMPTY_EXPLANATION = 'This request ran as a guest, so the debugger captured no identity to inspect.';

    /**
     * Headline of the empty state when the request captured no identity.
     */
    case EMPTY_HEADLINE = 'No authenticated user';

    /**
     * Identity accessor named in the empty state, whose resolution populates the panel.
     */
    case EMPTY_IDENTITY = 'Yii::$app->user->identity';

    /**
     * Closing sentence of the empty-state call to action.
     */
    case EMPTY_RESOLVES = ' resolves.';

    /**
     * Call to action of the empty state, preceding the identity accessor.
     */
    case EMPTY_SIGN_IN = 'Sign in and reload the page; the identity appears here as soon as ';

    /**
     * Stable identifier associating the panel with the captured payload, also used as its icon key.
     */
    case ID = 'user';

    /**
     * Header of the RBAC item name column.
     */
    case NAME = 'Name';

    /**
     * Header of the position column, which sorts by capture order.
     */
    case NUMBER = '#';

    /**
     * Section label of the granted permissions.
     */
    case PERMISSIONS = 'Permissions';

    /**
     * Placeholder shown wherever the capture left a field empty.
     */
    case PLACEHOLDER = '—';

    /**
     * `sprintf()` template of the note shown when the auth manager granted no item, naming the lowercased section.
     */
    case RBAC_EMPTY = 'The auth manager granted no %s to this identity.';

    /**
     * `sprintf()` template of an RBAC section heading, naming the section and its item count.
     */
    case RBAC_HEADING = '%s (%d)';

    /**
     * Section label of the granted roles.
     */
    case ROLES = 'Roles';

    /**
     * Header of the RBAC rule column.
     */
    case RULE = 'Rule';

    /**
     * Overview field label of the account status.
     */
    case STATUS = 'Status';

    /**
     * Badge label of an account whose status the capture did not resolve.
     */
    case STATUS_UNKNOWN = 'Unknown';

    /**
     * `sprintf()` template joining the absolute and relative forms of a captured timestamp.
     */
    case TIMESTAMP_JOIN = '%s · %s';

    /**
     * Panel title used in the debugger navigation, also the identity overview label and toolbar metric.
     */
    case TITLE = 'User';

    /**
     * Header of the RBAC update-time column.
     */
    case UPDATED = 'Updated';

    /**
     * Overview field label of the identity primary key.
     */
    case USER_ID = 'User ID';
}
