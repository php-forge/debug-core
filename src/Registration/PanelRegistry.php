<?php

declare(strict_types=1);

namespace PHPForge\Debug\Registration;

use InvalidArgumentException;
use PHPForge\Debug\Exception\Message;

use function array_values;
use function in_array;
use function sort;
use function strcasecmp;
use function strcmp;
use function usort;

/**
 * Resolves provider defaults against the application configuration into one effective panel catalog.
 *
 * Built-ins keep their registration order and every extension follows, ordered by position ascending, then by
 * effective title case-insensitively, then by ID.
 */
final readonly class PanelRegistry
{
    /**
     * @param array<string, PanelRegistration> $panels Effective registrations in display order, indexed by ID.
     * @param list<string> $disabled IDs removed by configuration, sorted alphabetically.
     */
    private function __construct(private array $panels, private array $disabled) {}

    /**
     * @return list<string> IDs removed by configuration, sorted alphabetically, including entries with no default.
     */
    public function disabled(): array
    {
        return $this->disabled;
    }

    /**
     * @return list<PanelRegistration> Effective registrations in display order.
     */
    public function enabled(): array
    {
        return array_values($this->panels);
    }

    /**
     * Returns the effective registration of a panel.
     *
     * @param string $id Stable panel identifier.
     *
     * @return PanelRegistration|null Effective registration, or `null` when the ID is unknown or disabled.
     */
    public function get(string $id): PanelRegistration|null
    {
        return $this->panels[$id] ?? null;
    }

    /**
     * Returns whether the configuration removed a panel.
     *
     * @param string $id Stable panel identifier.
     *
     * @return bool Whether the ID is disabled.
     */
    public function isDisabled(string $id): bool
    {
        return in_array($id, $this->disabled, true);
    }

    /**
     * Merges provider defaults with the application configuration and computes the display order.
     *
     * An override targeting an ID with no default is accepted only when it disables the entry, which covers an
     * optional package that is not installed; any other override for an unknown ID is a typo and is rejected.
     *
     * @param iterable<PanelRegistration> $defaults Host built-ins first, then providers, with provider defaults.
     * @param iterable<string, PanelOverride> $overrides Application configuration indexed by panel ID.
     *
     * @throws InvalidArgumentException When a default ID is registered twice, an override targets an unknown ID
     * without disabling it, or an override sets a position on a built-in panel.
     *
     * @return self Resolved catalog.
     */
    public static function resolve(iterable $defaults, iterable $overrides = []): self
    {
        $registrations = [];

        foreach ($defaults as $default) {
            if (isset($registrations[$default->id])) {
                throw new InvalidArgumentException(
                    Message::PANEL_ID_DUPLICATE->getMessage($default->id),
                );
            }

            $registrations[$default->id] = $default;
        }

        $configured = [];

        foreach ($overrides as $id => $override) {
            $configured[$id] = $override;
        }

        $disabled = [];
        $effective = [];

        foreach ($configured as $id => $override) {
            if (isset($registrations[$id])) {
                continue;
            }

            if ($override->enabled !== false) {
                throw new InvalidArgumentException(
                    Message::PANEL_OVERRIDE_UNREGISTERED->getMessage($id),
                );
            }

            $disabled[] = $id;
        }

        foreach ($registrations as $id => $registration) {
            $override = $configured[$id] ?? null;

            if ($override === null) {
                $effective[] = $registration;

                continue;
            }

            $merged = $registration->withOverride($override);

            if ($override->enabled === false) {
                $disabled[] = $id;

                continue;
            }

            $effective[] = $merged;
        }

        sort($disabled, SORT_STRING);

        return new self(self::order($effective), $disabled);
    }

    /**
     * Compares two extensions by position ascending, then by title case-insensitively, then by ID.
     *
     * An entry with no position sorts after every positioned entry.
     *
     * @param PanelRegistration $left First extension.
     * @param PanelRegistration $right Second extension.
     *
     * @return int Negative, zero, or positive ordering result.
     */
    private static function compare(PanelRegistration $left, PanelRegistration $right): int
    {
        $leftUnpositioned = $left->position === null;
        $rightUnpositioned = $right->position === null;
        $byPosition = $leftUnpositioned <=> $rightUnpositioned;

        if ($byPosition === 0) {
            $byPosition = $left->position <=> $right->position;
        }

        if ($byPosition !== 0) {
            return $byPosition;
        }

        $byTitle = strcasecmp($left->title, $right->title);

        return $byTitle !== 0 ? $byTitle : strcmp($left->id, $right->id);
    }

    /**
     * Places every built-in in registration order ahead of every sorted extension.
     *
     * @param list<PanelRegistration> $registrations Effective registrations in registration order.
     *
     * @return array<string, PanelRegistration> Effective registrations in display order, indexed by ID.
     */
    private static function order(array $registrations): array
    {
        $builtIns = [];
        $extensions = [];

        foreach ($registrations as $registration) {
            if ($registration->extension) {
                $extensions[] = $registration;

                continue;
            }

            $builtIns[] = $registration;
        }

        usort($extensions, self::compare(...));

        $ordered = [];

        foreach ([...$builtIns, ...$extensions] as $registration) {
            $ordered[$registration->id] = $registration;
        }

        return $ordered;
    }
}
