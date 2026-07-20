<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

final class PetEntity
{
    /** @var 'dog'|'cat' */
    public string $type;

    public string $name;

    public bool $vocalizes;

    /** @param 'dog'|'cat' $type */
    public function __construct(string $type, string $name, bool $vocalizes)
    {
        $this->type = $type;
        $this->name = $name;
        $this->vocalizes = $vocalizes;
    }
}
