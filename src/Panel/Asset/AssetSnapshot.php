<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Asset;

use PHPForge\Debug\Storage\{PanelSnapshot, Payload};

use function array_map;

/**
 * Canonical Asset panel snapshot holding the registered bundles and the Vite manifest in their typed form.
 */
final readonly class AssetSnapshot implements PanelSnapshot
{
    /**
     * @param list<AssetBundleRow> $bundles
     */
    public function __construct(
        private array $bundles,
        /**
         * Captured Vite bridge snapshot, or `null` when no Vite bridge is registered.
         */
        private ViteManifest|null $vite,
    ) {}

    /**
     * Returns the asset bundles registered during the request.
     *
     * @return list<AssetBundleRow> Registered bundles in registration order.
     */
    public function bundles(): array
    {
        return $this->bundles;
    }

    /**
     * Narrows the persisted asset payload into a typed snapshot.
     *
     * @param mixed $data Persisted payload, expected to be an object carrying a `bundles` list and a `vite` entry.
     * @param string $path JSON path of the payload, used to report a malformed capture.
     *
     * @return self Snapshot carrying the registered bundles and the Vite manifest.
     */
    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)
            ->shape(
                [
                    'bundles',
                    'vite',
                ],
            );

        $bundles = [];

        foreach ($payload->list('bundles') as $index => $bundle) {
            $bundles[] = AssetBundleRow::fromArray($bundle, "{$path}.bundles[{$index}]");
        }

        $vite = $payload->raw('vite');

        return new self($bundles, $vite === null ? null : ViteManifest::fromArray($vite, "{$path}.vite"));
    }

    /**
     * Returns the panel snapshot for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'bundles' => array_map(static fn(AssetBundleRow $row): array => $row->jsonSerialize(), $this->bundles),
            'vite' => $this->vite?->jsonSerialize(),
        ];
    }

    /**
     * Returns the Vite manifest snapshot, or `null` when no Vite bridge is registered.
     */
    public function vite(): ViteManifest|null
    {
        return $this->vite;
    }
}
