<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

final class Cat
{
    /** @var string */
    public $name;

    /** @var bool */
    public $meows;

    /** @var Cat|null */
    public $mother = null;

    /** @var Cat|null */
    public $father = null;

    /** @var array<int, Cat> */
    public $progeny = [];

    public function __construct(string $name, bool $meows)
    {
        $this->name = $name;
        $this->meows = $meows;
    }
}
