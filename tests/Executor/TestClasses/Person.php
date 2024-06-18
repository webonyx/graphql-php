<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

final class Person
{
    /** @var string */
    public $name;

    /** @var array<Dog|Cat>|null */
    public ?array $pets;

    /** @var array<Dog|Cat|Person>|null */
    public ?array $friends;

    /**
     * @param array<Cat|Dog>|null $pets
     * @param array<Cat|Dog|Person>|null $friends
     */
    public function __construct(string $name, ?array $pets = null, ?array $friends = null)
    {
        $this->name = $name;
        $this->pets = $pets;
        $this->friends = $friends;
    }
}
