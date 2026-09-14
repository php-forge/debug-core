<?php

declare(strict_types=1);

namespace PHPForge\Debug\PhpInfo;

/**
 * Typed view-model for one tile in a phpinfo Overview section.
 *
 * The `$kind` discriminator drives the renderer's branch: enabled/disabled pill, comma-separated path or token list,
 * single shortened path, or plain code text.
 */
final readonly class PhpInfoTile
{
    /**
     * Renders a single shortened path as `<code>`, with the full value as its hover title.
     */
    public const string KIND_PATH = 'path';
    /**
     * Renders every entry of {@see $tokens} as a `<code>` chip, each carrying its full path as a hover title.
     */
    public const string KIND_PATH_LIST = 'path-list';
    /**
     * Renders a pill in its muted variant, used for an off or unavailable status.
     */
    public const string KIND_PILL_MUTED = 'pill-muted';
    /**
     * Renders a pill in its success variant, used for an on or available status.
     */
    public const string KIND_PILL_SUCCESS = 'pill-success';
    /**
     * Renders the display value as plain `<code>` text, without a hover title.
     */
    public const string KIND_TEXT = 'text';
    /**
     * Renders every entry of {@see $tokens} as a `<code>` chip; unlike {@see KIND_PATH_LIST}, the tokens are not paths.
     */
    public const string KIND_TOKEN_LIST = 'token-list';

    public function __construct(
        /**
         * Tile label rendered in the `<dt>` cell ('SAPI', 'Memory limit', ...).
         */
        public string $label,
        /**
         * Display value already prepared for the rendering branch indicated by `$kind`.
         */
        public string $displayValue,
        /**
         * Full original value (used as the title tooltip when the display value was shortened).
         */
        public string $rawValue,
        /**
         * Rendering kind. See the `KIND_*` constants.
         */
        public string $kind,
        /**
         * Path tokens for {@see KIND_PATH_LIST} (already with {@see basename()} applied for display) or short tokens
         * for {@see KIND_TOKEN_LIST}. Empty list for the other kinds.
         *
         * @var list<PhpInfoToken>
         */
        public array $tokens = [],
    ) {}
}
