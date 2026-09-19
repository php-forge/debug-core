<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Asset;

use PHPForge\Debug\Storage\Payload;

use function array_map;

/**
 * Typed snapshot of the Vite bridge configuration and its build manifest.
 */
final readonly class ViteManifest
{
    /**
     * @param list<ViteChunk> $chunks
     */
    public function __construct(
        /**
         * Public base URL the built assets are served from.
         */
        public string $baseUrl,
        /**
         * Whether the bridge serves assets from the Vite dev server instead of the build manifest.
         */
        public bool $devMode,
        /**
         * Dev server URL, or `null` when the bridge serves built assets.
         */
        public string|null $devServerUrl,
        /**
         * Filesystem path of the build manifest the bridge reads.
         */
        public string $manifestPath,
        public array $chunks,
    ) {}

    /**
     * Narrows the persisted Vite payload into a typed manifest.
     *
     * @param mixed $data Persisted manifest, expected to be an object carrying the declared shape.
     * @param string $path JSON path of the manifest, used to report a malformed payload.
     *
     * @return self Manifest carrying the bridge configuration and its build chunks.
     */
    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)
            ->shape(
                [
                    'baseUrl',
                    'devMode',
                    'devServerUrl',
                    'manifestPath',
                    'chunks',
                ],
            );

        $chunks = [];

        foreach ($payload->list('chunks') as $index => $chunk) {
            $chunks[] = ViteChunk::fromArray($chunk, "{$path}.chunks[{$index}]");
        }

        return new self(
            $payload->string('baseUrl'),
            $payload->bool('devMode'),
            $payload->nullableString('devServerUrl'),
            $payload->string('manifestPath'),
            $chunks,
        );
    }

    /**
     * Returns the typed manifest for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'baseUrl' => $this->baseUrl,
            'devMode' => $this->devMode,
            'devServerUrl' => $this->devServerUrl,
            'manifestPath' => $this->manifestPath,
            'chunks' => array_map(static fn(ViteChunk $chunk): array => $chunk->jsonSerialize(), $this->chunks),
        ];
    }
}
