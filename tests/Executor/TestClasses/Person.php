<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

final class Person
{
    /** @var string */
    public $name;

    /** @var (Dog|Cat)[]|null */
    public $pets;

    /** @var (Dog|Cat|Person)[]|null */
    public $friends;

    /**
     * @param (Cat|Dog)[]|null        $pets
     * @param (Cat|Dog|Person)[]|null $friends
     */
    public function __construct(string $name, $pets = null, $friends = null)
    {
        $this->name = $name;
        $this->pets = $pets;
        $this->friends = $friends;
    }
}
