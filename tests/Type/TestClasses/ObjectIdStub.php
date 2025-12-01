<?php declare(strict_types=1);

namespace GraphQL\Tests\Type\TestClasses;

final class ObjectIdStub implements \Stringable
{
    private int $id;

    public function __construct(int $id)
    {
        $this->id = $id;
    }

    public function __toString()
    {
        return (string) $this->id;
    }
}
