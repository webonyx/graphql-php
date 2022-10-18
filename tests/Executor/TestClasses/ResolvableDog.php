<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

use GraphQL\Resolvable;

class ResolvableDog implements Resolvable
{
    /** @var string */
    public $name;

    /** @var bool */
    public $woofs;

    public function __construct(string $name, bool $woofs)
    {
        $this->name = $name;
        $this->woofs = $woofs;
    }

    public function resolveGraphQLType(): string
    {
        return 'Dog';
    }
}
