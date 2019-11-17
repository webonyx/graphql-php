<?php

declare(strict_types=1);

namespace GraphQL;

use GraphQL\Executor\Promise\Adapter\SyncPromise;

class Deferred extends SyncPromise
{
    public static function create(callable $executor) : self
    {
        return new self($executor);
    }

    public function __construct(callable $executor)
    {
        parent::__construct($executor);
    }
}
