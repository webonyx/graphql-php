<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

class Cat
{
    /** @var string */
    public $name;

    /** @var bool */
    public $meows;

    /** @var Cat|null */
    public $mother;

    /** @var Cat|null */
    public $father;

    /** @var array<int, Cat> */
    public $progeny;

    public function __construct(string $name, bool $meows)
    {
        $this->name = $name;
        $this->meows = $meows;
        $this->mother = null;
        $this->father = null;
        $this->progeny = [];
    }
}
