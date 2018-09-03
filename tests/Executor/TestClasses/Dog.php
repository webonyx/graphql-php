<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

class Dog
{
    /** @var string */
    public $name;

    /** @var bool */
    public $woofs;

    public function __construct(string $name, bool $woofs)
    {
        $this->name  = $name;
        $this->woofs = $woofs;
    }
}
