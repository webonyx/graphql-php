<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

final class Dog
{
    public string $name;

    public bool $woofs;

    public ?Dog $mother = null;

    public ?Dog $father = null;

    /** @var array<int, Dog> */
    public array $progeny = [];

    public function __construct(string $name, bool $woofs)
    {
        $this->name = $name;
        $this->woofs = $woofs;
    }
}
