<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

class Cat
{
    /** @var string */
    public $name;

    /** @var bool */
    public $meows;

    public function __construct(string $name, bool $meows)
    {
        $this->name  = $name;
        $this->meows = $meows;
    }
}
