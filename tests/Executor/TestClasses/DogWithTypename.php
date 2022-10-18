<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

use GraphQL\Resolvable;

class DogWithTypename implements Resolvable
{
    /** @var string */
    public $name;

    /** @var bool */
    public $woofs;

    /** @var string */
    public $__typename = 'Dog';

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
