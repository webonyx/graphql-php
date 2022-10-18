<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

class CatWithTypename
{
    /** @var string */
    public $name;

    /** @var bool */
    public $meows;

    /** @var string */
    public $__typename = 'Cat';

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
