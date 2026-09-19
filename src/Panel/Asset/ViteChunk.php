<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Asset;

use PHPForge\Debug\Storage\{PanelRow, Payload};

/**
 * Typed entry of a Vite build manifest.
 */
final readonly class ViteChunk implements PanelRow
{
    public function __construct(
        /**
         * Chunk name as keyed in the manifest.
         */
        public string $name,
        /**
         * Built file emitted for the chunk.
         */
        public string $file,
        /**
         * Number of CSS files attached to the chunk.
         */
        public int $cssCount,
        /**
         * Number of chunks this one imports.
         */
        public int $imports,
        /**
         * Whether the chunk is a Vite entry point.
         */
        public bool $isEntry,
    ) {}

    /**
     * Narrows one persisted manifest entry into a typed chunk.
     *
     * @param mixed $data Persisted manifest entry, expected to be an object carrying the declared shape.
     * @param string $path JSON path of the entry, used to report a malformed payload.
     *
     * @return self Chunk carrying every persisted manifest field.
     */
    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)
            ->shape(
                [
                    'name',
                    'file',
                    'cssCount',
                    'imports',
                    'isEntry',
                ],
            );

        return new self(
            $payload->string('name'),
            $payload->string('file'),
            $payload->int('cssCount'),
            $payload->int('imports'),
            $payload->bool('isEntry'),
        );
    }

    /**
     * Returns the typed row for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'file' => $this->file,
            'cssCount' => $this->cssCount,
            'imports' => $this->imports,
            'isEntry' => $this->isEntry,
        ];
    }
}
