<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

class Dog
{
    /** @var string */
    public $name;

    /** @var bool */
    public $woofs;

    /** @var Dog|null */
    public $mother;

    /** @var Dog|null */
    public $father;

    /** @var array<int, Dog> */
    public $progeny;

    public function __construct(string $name, bool $woofs)
    {
        $this->name    = $name;
        $this->woofs   = $woofs;
        $this->mother  = null;
        $this->father  = null;
        $this->progeny = [];
    }
}
