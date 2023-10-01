<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

final class MySharedContext
{
    /** @var list<string> */
    public array $path = [];
}
