<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

use GraphQL\Resolvable;

class ResolvableCat implements Resolvable
{
    /** @var string */
    public $name;

    /** @var bool */
    public $meows;

    public function __construct(string $name, bool $meows)
    {
        $this->name = $name;
        $this->meows = $meows;
    }

    public function resolveGraphQLType(): string
    {
        return 'Cat';
    }
}
