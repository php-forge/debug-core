<?php

declare(strict_types=1);

namespace PHPForge\Debug\Storage;

use RuntimeException;

/**
 * Reports an invalid storage configuration or a failed filesystem operation.
 */
final class StorageException extends RuntimeException {}
