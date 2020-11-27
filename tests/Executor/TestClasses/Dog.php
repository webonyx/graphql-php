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

    /** @var Dog[] */
    public $progeny;

    public function __construct(string $name, bool $woofs)
    {
        $this->name    = $name;
        $this->woofs   = $woofs;
        $this->mother  = NULL;
        $this->father  = NULL;
        $this->progeny = [];
    }
}
