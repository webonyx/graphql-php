<?php declare(strict_types=1);

namespace GraphQL\Tests\Type\TestClasses;

final class ObjectIdStub
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
