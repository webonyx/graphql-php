<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

class Human
{
    /** @var string */
    public $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
