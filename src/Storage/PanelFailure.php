<?php

declare(strict_types=1);

namespace PHPForge\Debug\Storage;

use JsonSerializable;
use Throwable;

/**
 * Captures a panel failure without invalidating the remaining request snapshot.
 */
final readonly class PanelFailure implements JsonSerializable
{
    public const string CAPTURE = 'capture';
    public const string HYDRATE = 'hydrate';

    /**
     * Creates a failure record for a panel lifecycle stage.
     *
     * @param 'capture'|'hydrate' $stage Lifecycle stage the panel failed in.
     * @param ExceptionSnapshot $exception Captured panel exception.
     */
    public function __construct(public string $stage, public ExceptionSnapshot $exception) {}

    /**
     * Hydrates a panel failure from decoded JSON data.
     *
     * @param mixed $data Decoded panel failure payload.
     * @param string $path Payload path used in hydration errors.
     *
     * @return self Hydrated panel failure.
     */
    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)
            ->shape(
                [
                    'stage',
                    'exception',
                ],
            );

        $stage = $payload->string('stage');

        if ($stage !== self::CAPTURE && $stage !== self::HYDRATE) {
            throw HydrationException::at(
                "{$path}.stage",
                'capture or hydrate',
            );
        }

        return new self(
            $stage,
            ExceptionSnapshot::fromArray($payload->raw('exception'), "{$path}.exception"),
        );
    }

    /**
     * Captures a throwable raised during a panel lifecycle stage.
     *
     * @param 'capture'|'hydrate' $stage Lifecycle stage the panel failed in.
     * @param Throwable $throwable Panel exception to capture.
     *
     * @return self Captured panel failure.
     */
    public static function fromThrowable(string $stage, Throwable $throwable): self
    {
        return new self(
            $stage,
            ExceptionSnapshot::fromThrowable($throwable),
        );
    }

    /**
     * Returns the failure record for JSON serialization.
     *
     * @return array<string, mixed> Serialized failure stage and exception.
     */
    public function jsonSerialize(): array
    {
        return [
            'stage' => $this->stage,
            'exception' => $this->exception->jsonSerialize(),
        ];
    }
}
