<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

final class Dog
{
    /** @var string */
    public $name;

    /** @var bool */
    public $woofs;

    /** @var Dog|null */
    public $mother = null;

    /** @var Dog|null */
    public $father = null;

    /** @var array<int, Dog> */
    public $progeny = [];

    public function __construct(string $name, bool $woofs)
    {
        $this->name = $name;
        $this->woofs = $woofs;
    }
}
