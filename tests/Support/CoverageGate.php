<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

use function array_map;
use function array_slice;
use function dirname;
use function escapeshellarg;
use function file_exists;
use function fprintf;
use function fwrite;
use function implode;
use function is_array;
use function is_string;
use function passthru;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

use const LIBXML_NONET;
use const PHP_BINARY;
use const STDERR;
use const STDOUT;

final class CoverageGate
{
    private const COVERAGE_OPTION = '--coverage-clover=';

    public static function run(mixed $arguments): int
    {
        $arguments = self::cliArguments($arguments);

        if ($arguments === null) {
            fwrite(STDERR, "The coverage gate received invalid command arguments.\n");

            return 2;
        }

        $coverageFile = self::coverageFile($arguments);

        if ($coverageFile === null) {
            fwrite(STDERR, "The coverage gate requires --coverage-clover=<file>.\n");

            return 2;
        }

        $command = [PHP_BINARY, dirname(__DIR__, 2) . '/vendor/bin/phpunit', ...array_slice($arguments, 1)];

        passthru(implode(' ', array_map(escapeshellarg(...), $command)), $exitCode);

        if ($exitCode !== 0) {
            return $exitCode;
        }

        return self::verify($coverageFile);
    }

    /**
     * @return list<string>|null
     */
    private static function cliArguments(mixed $arguments): array|null
    {
        if (!is_array($arguments)) {
            return null;
        }

        $result = [];

        foreach ($arguments as $argument) {
            if (!is_string($argument)) {
                return null;
            }

            $result[] = $argument;
        }

        return $result;
    }

    /**
     * @param list<string> $arguments
     * @return non-empty-string|null
     */
    private static function coverageFile(array $arguments): string|null
    {
        foreach ($arguments as $argument) {
            if (str_starts_with($argument, self::COVERAGE_OPTION)) {
                $coverageFile = substr($argument, strlen(self::COVERAGE_OPTION));

                return $coverageFile !== '' ? $coverageFile : null;
            }
        }

        return null;
    }

    /**
     * @return array{classes: int, methods: int, coveredMethods: int, lines: int, coveredLines: int}
     */
    private static function metrics(DOMElement $metrics): array
    {
        return [
            'classes' => (int) $metrics->getAttribute('classes'),
            'methods' => (int) $metrics->getAttribute('methods'),
            'coveredMethods' => (int) $metrics->getAttribute('coveredmethods'),
            'lines' => (int) $metrics->getAttribute('statements'),
            'coveredLines' => (int) $metrics->getAttribute('coveredstatements'),
        ];
    }

    /**
     * @param non-empty-string $coverageFile
     */
    private static function verify(string $coverageFile): int
    {
        if (!file_exists($coverageFile)) {
            fprintf(STDERR, "Coverage report %s was not generated.\n", $coverageFile);

            return 2;
        }

        $document = new DOMDocument();

        if (!$document->load($coverageFile, LIBXML_NONET)) {
            fprintf(STDERR, "Coverage report %s is not valid XML.\n", $coverageFile);

            return 2;
        }

        $nodes = (new DOMXPath($document))->query('/coverage/project/metrics');
        $metrics = $nodes === false ? null : $nodes->item(0);

        if (!$metrics instanceof DOMElement) {
            fprintf(STDERR, "Coverage report %s has no project metrics.\n", $coverageFile);

            return 2;
        }

        $totals = self::metrics($metrics);
        $complete = $totals['classes'] > 0
            && $totals['methods'] > 0
            && $totals['lines'] > 0
            && $totals['methods'] === $totals['coveredMethods']
            && $totals['lines'] === $totals['coveredLines'];
        $coveredClasses = $complete ? $totals['classes'] : 0;

        fwrite(
            $complete ? STDOUT : STDERR,
            sprintf(
                "Coverage gate: %d/%d classes, %d/%d methods, %d/%d lines.\n",
                $coveredClasses,
                $totals['classes'],
                $totals['coveredMethods'],
                $totals['methods'],
                $totals['coveredLines'],
                $totals['lines'],
            ),
        );

        return $complete ? 0 : 1;
    }
}
