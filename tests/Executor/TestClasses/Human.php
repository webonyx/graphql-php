<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

final class Human
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
