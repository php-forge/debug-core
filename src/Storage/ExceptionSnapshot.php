<?php

declare(strict_types=1);

namespace PHPForge\Debug\Storage;

use JsonSerializable;
use PHPForge\Debug\Capture\CapturePolicy;
use SensitiveParameter;
use Stringable;
use Throwable;

use function array_map;
use function is_int;
use function is_string;
use function strrpos;
use function substr;

/**
 * Represents a captured throwable without executable state.
 */
final readonly class ExceptionSnapshot implements JsonSerializable, Stringable
{
    /**
     * Frame arguments stay in their tagged form so a snapshot read back from disk re-serializes byte for byte; newly
     * captured snapshots deliberately persist an empty argument list.
     *
     * @param string $class Captured throwable class.
     * @param string $message Captured throwable message.
     * @param int|string $code Captured throwable code.
     * @param string $file File where the throwable originated.
     * @param int $line Line where the throwable originated.
     * @param list<array{
     *   namespace: string,
     *   short_class: string,
     *   class: string,
     *   type: string,
     *   function: string|null,
     *   file: string|null,
     *   line: int|null,
     *   args: DebugArray,
     * }> $trace Captured trace frames.
     * @param string $toString Original throwable text.
     * @param self|null $previous Previous throwable snapshot or `null`.
     */
    public function __construct(
        private string $class,
        private string $message,
        private int|string $code,
        private string $file,
        private int $line,
        private array $trace,
        private string $toString,
        private self|null $previous,
    ) {}

    /**
     * Returns the original throwable text captured in the snapshot.
     *
     * @return string Original throwable text.
     */
    public function __toString(): string
    {
        return $this->toString;
    }

    /**
     * Hydrates a throwable snapshot from decoded JSON data.
     *
     * Usage example:
     *
     * ```php
     * $captured = \PHPForge\Debug\Storage\ExceptionSnapshot::fromThrowable(new \RuntimeException('Failed.'));
     * $snapshot = \PHPForge\Debug\Storage\ExceptionSnapshot::fromArray($captured->jsonSerialize());
     * ```
     *
     * @param mixed $data Decoded throwable payload.
     * @param string $path Payload path used in hydration errors.
     *
     * @return self Hydrated throwable snapshot.
     */
    public static function fromArray(mixed $data, string $path = '$.exception'): self
    {
        $payload = Payload::object($data, $path)
            ->shape(
                [
                    'class',
                    'message',
                    'code',
                    'file',
                    'line',
                    'trace',
                    'toString',
                    'previous',
                ],
            );

        $rawCode = $payload->raw('code');

        if (!is_int($rawCode) && !is_string($rawCode)) {
            throw HydrationException::at(
                "{$path}.code",
                'an integer or string',
            );
        }

        $trace = [];

        foreach ($payload->list('trace') as $index => $rawFrame) {
            $framePath = "{$path}.trace[{$index}]";

            $frame = Payload::object($rawFrame, $framePath)
                ->shape(
                    [
                        'namespace',
                        'short_class',
                        'class',
                        'type',
                        'function',
                        'file',
                        'line',
                        'args',
                    ],
                );

            $trace[] = [
                'namespace' => $frame->string('namespace'),
                'short_class' => $frame->string('short_class'),
                'class' => $frame->string('class'),
                'type' => $frame->string('type'),
                'function' => $frame->nullableString('function'),
                'file' => $frame->nullableString('file'),
                'line' => $frame->nullableInt('line'),
                'args' => DebugArray::fromArray($frame->raw('args'), "{$framePath}.args"),
            ];
        }

        $previous = $payload->raw('previous');

        return new self(
            class: $payload->string('class'),
            message: $payload->string('message'),
            code: $rawCode,
            file: $payload->string('file'),
            line: $payload->int('line'),
            trace: $trace,
            toString: $payload->string('toString'),
            previous: $previous === null ? null : self::fromArray($previous, "{$path}.previous"),
        );
    }

    /**
     * Captures a throwable and its previous-exception chain without executable state.
     *
     * Usage example:
     *
     * ```php
     * $snapshot = \PHPForge\Debug\Storage\ExceptionSnapshot::fromThrowable(
     *     new \RuntimeException('Capture failed.'),
     * );
     * ```
     *
     * @param Throwable $throwable Throwable to capture.
     *
     * @return self Captured throwable snapshot.
     */
    public static function fromThrowable(#[SensitiveParameter] Throwable $throwable): self
    {
        $capturePolicy = new CapturePolicy();
        $trace = [];

        foreach ($throwable->getTrace() as $entry) {
            $class = is_string($entry['class'] ?? null) ? $entry['class'] : '';

            $trace[] = [
                'namespace' => Json::safeString(self::namespacePart($class)),
                'short_class' => Json::safeString(self::shortName($class)),
                'class' => Json::safeString($class),
                'type' => Json::safeString(is_string($entry['type'] ?? null) ? $entry['type'] : ''),
                'function' => Json::safeString($entry['function']),
                'file' => is_string($entry['file'] ?? null) ? Json::safeString($entry['file']) : null,
                'line' => is_int($entry['line'] ?? null) ? $entry['line'] : null,
                'args' => DebugArray::capture([]),
            ];
        }

        $code = $throwable->getCode();

        try {
            $toString = (string) $throwable;
        } catch (Throwable) {
            $toString = $throwable::class . ': ' . $throwable->getMessage();
        }

        return new self(
            class: Json::safeString($throwable::class),
            message: $capturePolicy->redactText(Json::safeString($throwable->getMessage())),
            code: $code,
            file: Json::safeString($throwable->getFile()),
            line: $throwable->getLine(),
            trace: $trace,
            toString: $capturePolicy->redactText(Json::safeString($toString)),
            previous: $throwable->getPrevious() !== null ? self::fromThrowable($throwable->getPrevious()) : null,
        );
    }

    /**
     * Returns the captured throwable class.
     *
     * Usage example:
     *
     * ```php
     * $class = \PHPForge\Debug\Storage\ExceptionSnapshot::fromThrowable(new \RuntimeException())->getClass();
     * ```
     *
     * @return string Captured throwable class.
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Returns the captured throwable code.
     *
     * Usage example:
     *
     * ```php
     * $code = \PHPForge\Debug\Storage\ExceptionSnapshot::fromThrowable(new \RuntimeException('', 42))->getCode();
     * ```
     *
     * @return int|string Captured throwable code.
     */
    public function getCode(): int|string
    {
        return $this->code;
    }

    /**
     * Returns the file where the throwable originated.
     *
     * Usage example:
     *
     * ```php
     * $file = \PHPForge\Debug\Storage\ExceptionSnapshot::fromThrowable(new \RuntimeException())->getFile();
     * ```
     *
     * @return string Origin file path.
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * Returns the line where the throwable originated.
     *
     * Usage example:
     *
     * ```php
     * $line = \PHPForge\Debug\Storage\ExceptionSnapshot::fromThrowable(new \RuntimeException())->getLine();
     * ```
     *
     * @return int Origin line number.
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * Returns the captured throwable message.
     *
     * Usage example:
     *
     * ```php
     * $message = \PHPForge\Debug\Storage\ExceptionSnapshot::fromThrowable(
     *     new \RuntimeException('Capture failed.'),
     * )->getMessage();
     * ```
     *
     * @return string Captured throwable message.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Returns the previous throwable snapshot or `null`.
     *
     * Usage example:
     *
     * ```php
     * $snapshot = \PHPForge\Debug\Storage\ExceptionSnapshot::fromThrowable(
     *     new \RuntimeException('Outer.', 0, new \LogicException('Inner.')),
     * );
     * $previous = $snapshot->getPrevious();
     * ```
     *
     * @return self|null Previous throwable snapshot or `null`.
     */
    public function getPrevious(): self|null
    {
        return $this->previous;
    }

    /**
     * Returns the trace frames with their arguments projected to plain display values.
     *
     * Usage example:
     *
     * ```php
     * $trace = \PHPForge\Debug\Storage\ExceptionSnapshot::fromThrowable(new \RuntimeException())->getTrace();
     * ```
     *
     * @return list<array<string, mixed>> Display-safe trace frames.
     */
    public function getTrace(): array
    {
        return array_map(
            static fn(array $frame): array => [...$frame, 'args' => $frame['args']->values()],
            $this->trace,
        );
    }

    /**
     * Returns the throwable snapshot for JSON serialization.
     *
     * Usage example:
     *
     * ```php
     * $data = \PHPForge\Debug\Storage\ExceptionSnapshot::fromThrowable(
     *     new \RuntimeException('Capture failed.'),
     * )->jsonSerialize();
     * ```
     *
     * @return array<string, mixed> Serialized throwable snapshot.
     */
    public function jsonSerialize(): array
    {
        return [
            'class' => $this->class,
            'message' => $this->message,
            'code' => $this->code,
            'file' => $this->file,
            'line' => $this->line,
            'trace' => array_map(
                static fn(array $frame): array => [...$frame, 'args' => $frame['args']->jsonSerialize()],
                $this->trace,
            ),
            'toString' => $this->toString,
            'previous' => $this->previous?->jsonSerialize(),
        ];
    }

    /**
     * Returns the namespace portion of a fully qualified class name.
     *
     * @param string $class Fully qualified class name.
     *
     * @return string Namespace portion or an empty string.
     */
    private static function namespacePart(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? '' : substr($class, 0, $position);
    }

    /**
     * Returns the short portion of a fully qualified class name.
     *
     * @param string $class Fully qualified class name.
     *
     * @return string Short class name.
     */
    private static function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }
}
