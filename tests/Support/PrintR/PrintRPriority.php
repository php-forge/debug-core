<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Support\PrintR;

/**
 * Int-backed enum dumped by `print_r()` with an `Enum:int` header.
 */
enum PrintRPriority: int
{
    case HIGH = 1;
}
