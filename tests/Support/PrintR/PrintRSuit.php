<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Support\PrintR;

/**
 * String-backed enum dumped by `print_r()` with an `Enum:string` header.
 */
enum PrintRSuit: string
{
    case HEARTS = 'H';
}
