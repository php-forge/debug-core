<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Router;

/**
 * Identity and presentation text of the Router panel, shared by every adapter that renders it.
 */
enum RouterMessage: string
{
    /**
     * Header of the action column, also used as the overview field label of the dispatched action.
     */
    case ACTION = 'Action';

    /**
     * `sprintf()` template of the heading above the discovered action routes.
     */
    case ACTION_ROUTES = 'Action routes (%d)';

    /**
     * Badge label of a disabled URL manager flag.
     */
    case DISABLED = 'disabled';

    /**
     * Badge label of an enabled URL manager flag.
     */
    case ENABLED = 'enabled';

    /**
     * Header of the first matching rule column.
     */
    case FIRST_RULE = 'First matching rule';

    /**
     * Overview field label of the global URL suffix.
     */
    case GLOBAL_SUFFIX = 'Global suffix';

    /**
     * Stable identifier associating the panel with the captured payload, also used as its icon key.
     */
    case ID = 'router';

    /**
     * Badge marking an inspected rule that matched the request.
     */
    case MATCH_RESULT = 'match';

    /**
     * Header of the rule mode column.
     */
    case MODE = 'Mode';

    /**
     * Header of the rule name column.
     */
    case NAME = 'Name';

    /**
     * Note shown when the adapter configured no action route.
     */
    case NO_ACTIONS = 'No actions are configured.';

    /**
     * Badge marking an inspected rule that did not match the request.
     */
    case NO_MATCH_RESULT = 'no match';

    /**
     * Note shown when the capture carried no rule trace.
     */
    case NO_TRACE = 'The router captured no rule trace for this request.';

    /**
     * Header of the position column, which sorts by inspection order.
     */
    case NUMBER = '#';

    /**
     * Header of the parent rule column.
     */
    case PARENT_RULE = 'Parent';

    /**
     * Placeholder shown wherever the capture left a field empty.
     */
    case PLACEHOLDER = '—';

    /**
     * Overview field label of the pretty-URL flag.
     */
    case PRETTY_URL = 'Pretty URL';

    /**
     * Header of the rule result column.
     */
    case RESULT = 'Result';

    /**
     * Header of the route column, also used as the overview field label and the toolbar metric of the resolved route.
     */
    case ROUTE = 'Route';

    /**
     * Header of the rule column.
     */
    case RULE = 'Rule';

    /**
     * Singular noun of the rules-tested heading.
     */
    case RULE_NOUN = 'rule';

    /**
     * Suffix appended to a single inspected rule in the summary header.
     */
    case RULE_TESTED_SUFFIX = ' rule tested';

    /**
     * Plural noun of the rules-tested heading.
     */
    case RULES_NOUN = 'rules';

    /**
     * Heading of the rules-tested trace when routing inspected no rule, also used as the count column header.
     */
    case RULES_TESTED = 'Rules tested';

    /**
     * Suffix appended to the inspected rule count in the summary header.
     */
    case RULES_TESTED_SUFFIX = ' rules tested';

    /**
     * Overview field label of the strict-parsing flag.
     */
    case STRICT_PARSING = 'Strict parsing';

    /**
     * Header of the rule suffix column.
     */
    case SUFFIX = 'Suffix';

    /**
     * Qualifier appended to the rules-tested heading when an inspected rule matched.
     */
    case TESTED_BEFORE_MATCH = ' before match';

    /**
     * `sprintf()` template of the rules-tested heading, naming the count, the noun, and the match qualifier.
     */
    case TESTED_HEADING = 'Tested %d %s%s';

    /**
     * Panel title used in the debugger navigation.
     */
    case TITLE = 'Router';

    /**
     * Header of the rule type column.
     */
    case TYPE = 'Type';

    /**
     * `sprintf()` template of the heading above the configured URL rules.
     */
    case URL_RULES = 'URL rules (%d)';

    /**
     * Note shown when the URL manager declares no rule.
     */
    case URL_RULES_EMPTY = 'The URL manager declares no rules.';

    /**
     * Header of the rule verb column.
     */
    case VERB = 'Verb';
}
