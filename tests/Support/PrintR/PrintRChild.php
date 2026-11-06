<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Support\PrintR;

/**
 * Object with public, protected, and private properties, an inherited private property, an enum, a nested array, and
 * a slot for a reference to itself, dumped through real `print_r()` calls.
 */
final class PrintRChild extends PrintRParent
{
    public string $empty = '';
    public bool $flag = false;
    public string $name = 'Ada';
    public mixed $self = null;
    public PrintRSuit $suit = PrintRSuit::HEARTS;
    /**
     * @var list<string>
     */
    public array $tags = ['a', 'b'];
    protected int|null $parent = null;
    private string $secret = 'child';

    /**
     * Returns the private property that shadows the one declared by {@see PrintRParent}.
     */
    public function secret(): string
    {
        return $this->secret;
    }
}
