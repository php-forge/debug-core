<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Vite;

use PHPForge\Debug\Panel\Asset\ViteChunk;
use PHPForge\Debug\Storage\{HydrationException, PanelRow, Payload};

use function array_map;
use function in_array;
use function is_string;

/**
 * Describes one loaded Vite integration without coupling the debugger core to a framework adapter or Vite package.
 */
final readonly class ViteComponent implements PanelRow
{
    /**
     * Identifies the legacy Vite integration implementation.
     */
    public const string IMPLEMENTATION_LEGACY = 'legacy';
    /**
     * Identifies the modern Vite integration implementation.
     */
    public const string IMPLEMENTATION_MODERN = 'modern';
    /**
     * Identifies the Vite development mode.
     */
    public const string MODE_DEVELOPMENT = 'development';
    /**
     * Identifies the Vite production mode.
     */
    public const string MODE_PRODUCTION = 'production';
    /**
     * Identifies an unknown Vite mode.
     */
    public const string MODE_UNKNOWN = 'unknown';

    /**
     * @param list<string> $entrypoints Configured Vite entrypoints.
     * @param list<ViteChunk> $chunks Captured production-manifest chunks.
     */
    public function __construct(
        public string $id,
        public string $class,
        public string $implementation,
        public bool $inspectionAvailable,
        public string $mode,
        public array $entrypoints,
        public string $baseUrl,
        public string|null $devServerUrl,
        public string $manifestPath,
        public bool|null $includeViteClient,
        public bool|null $modulePreload,
        private array $chunks,
    ) {}

    /**
     * @return list<ViteChunk> Captured production-manifest chunks.
     */
    public function chunks(): array
    {
        return $this->chunks;
    }

    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)
            ->shape(
                [
                    'id',
                    'class',
                    'implementation',
                    'inspectionAvailable',
                    'mode',
                    'entrypoints',
                    'baseUrl',
                    'devServerUrl',
                    'manifestPath',
                    'includeViteClient',
                    'modulePreload',
                    'chunks',
                ],
            );

        $implementation = $payload->string('implementation');

        if (!in_array($implementation, [self::IMPLEMENTATION_MODERN, self::IMPLEMENTATION_LEGACY], true)) {
            throw HydrationException::at("{$path}.implementation", '`modern` or `legacy`');
        }

        $mode = $payload->string('mode');

        if (!in_array($mode, [self::MODE_DEVELOPMENT, self::MODE_PRODUCTION, self::MODE_UNKNOWN], true)) {
            throw HydrationException::at("{$path}.mode", '`development`, `production`, or `unknown`');
        }

        $entrypoints = [];

        foreach ($payload->list('entrypoints') as $index => $entrypoint) {
            if (!is_string($entrypoint)) {
                throw HydrationException::at("{$path}.entrypoints[{$index}]", 'a string');
            }

            $entrypoints[] = $entrypoint;
        }

        return new self(
            id: $payload->string('id'),
            class: $payload->string('class'),
            implementation: $implementation,
            inspectionAvailable: $payload->bool('inspectionAvailable'),
            mode: $mode,
            entrypoints: $entrypoints,
            baseUrl: $payload->string('baseUrl'),
            devServerUrl: $payload->nullableString('devServerUrl'),
            manifestPath: $payload->string('manifestPath'),
            includeViteClient: $payload->nullableBool('includeViteClient'),
            modulePreload: $payload->nullableBool('modulePreload'),
            chunks: $payload->mapList('chunks', ViteChunk::fromArray(...)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'class' => $this->class,
            'implementation' => $this->implementation,
            'inspectionAvailable' => $this->inspectionAvailable,
            'mode' => $this->mode,
            'entrypoints' => $this->entrypoints,
            'baseUrl' => $this->baseUrl,
            'devServerUrl' => $this->devServerUrl,
            'manifestPath' => $this->manifestPath,
            'includeViteClient' => $this->includeViteClient,
            'modulePreload' => $this->modulePreload,
            'chunks' => array_map(static fn(ViteChunk $chunk): array => $chunk->jsonSerialize(), $this->chunks),
        ];
    }
}
