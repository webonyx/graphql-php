<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

final class Cat
{
    public string $name;

    public bool $meows;

    public ?Cat $mother = null;

    public ?Cat $father = null;

    /** @var array<int, Cat> */
    public array $progeny = [];

    public function __construct(string $name, bool $meows)
    {
        $this->name = $name;
        $this->meows = $meows;
    }
}
