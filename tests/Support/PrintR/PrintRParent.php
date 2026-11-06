<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Support\PrintR;

/**
 * Parent class whose private property `print_r()` prints with its declaring class.
 */
class PrintRParent
{
    protected string $role = 'admin';
    private string $secret = 'parent';

    /**
     * Returns the private property `print_r()` prints with this class as its declaring class.
     */
    public function parentSecret(): string
    {
        return $this->secret;
    }
}
