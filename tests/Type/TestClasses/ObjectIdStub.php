<?php declare(strict_types=1);

namespace GraphQL\Tests\Type\TestClasses;

final class ObjectIdStub
{
    /** @var int */
    private $id;

    /** @param int $id */
    public function __construct($id)
    {
        $this->id = $id;
    }

    public function __toString()
    {
        return (string) $this->id;
    }
}
