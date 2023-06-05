<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

use GraphQL\Executor\ScopedContext;

final class MyScopedContext implements ScopedContext
{
    /** @var list<string> */
    public array $path = [];

    public function clone(): ScopedContext
    {
        return clone $this;
    }
}
