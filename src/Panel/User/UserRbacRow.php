<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\User;

use PHPForge\Debug\Helper\Coerce;

/**
 * Represents one RBAC item row (role or permission) in the User panel detail view.
 */
final readonly class UserRbacRow
{
    /**
     * @param string $name Item name, unique within the hierarchy.
     * @param string $description Human-readable description of the item's purpose.
     * @param string $ruleName Name of the rule associated with the item, or an empty string when none.
     * @param string $data Serialized arbitrary data attached to the item, or an empty string when absent.
     * @param int|null $createdAt UNIX timestamp of item creation, or `null` when not recorded.
     * @param int|null $updatedAt UNIX timestamp of the last item update, or `null` when not recorded.
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $ruleName,
        public string $data,
        public int|null $createdAt,
        public int|null $updatedAt,
    ) {}

    /**
     * Builds a row from the normalized array shape produced by RBAC adapters.
     *
     * @param array<array-key, mixed> $row Associative array with keys `name`, `description`, `ruleName`, `data`,
     * `createdAt`, and `updatedAt`.
     */
    public static function fromArray(array $row): self
    {
        $name = $row['name'] ?? '';
        $description = $row['description'] ?? '';
        $ruleName = $row['ruleName'] ?? '';
        $data = $row['data'] ?? '';
        $createdAt = $row['createdAt'] ?? null;
        $updatedAt = $row['updatedAt'] ?? null;

        return new self(
            name: Coerce::string($name),
            description: Coerce::string($description),
            ruleName: Coerce::string($ruleName),
            data: Coerce::string($data),
            createdAt: Coerce::intOrNull($createdAt),
            updatedAt: Coerce::intOrNull($updatedAt),
        );
    }
}
