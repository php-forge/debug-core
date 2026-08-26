<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Vite;

use PHPForge\Debug\Storage\{PanelSnapshot, Payload};

use function array_map;

/**
 * Canonical Vite panel snapshot containing every loaded integration in adapter-defined registration order.
 */
final readonly class ViteSnapshot implements PanelSnapshot
{
    /**
     * @param list<ViteComponent> $components Loaded Vite integrations.
     */
    public function __construct(private array $components) {}

    /**
     * @return list<ViteComponent> Loaded Vite integrations.
     */
    public function components(): array
    {
        return $this->components;
    }

    public static function fromArray(mixed $data, string $path = '$'): self
    {
        $payload = Payload::object($data, $path)->shape(['components']);
        $components = [];

        foreach ($payload->list('components') as $index => $component) {
            $components[] = ViteComponent::fromArray($component, "{$path}.components[{$index}]");
        }

        return new self($components);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'components' => array_map(
                static fn(ViteComponent $component): array => $component->jsonSerialize(),
                $this->components,
            ),
        ];
    }
}
