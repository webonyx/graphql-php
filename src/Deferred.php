<?php declare(strict_types=1);

namespace GraphQL;

use GraphQL\Executor\Promise\Adapter\SyncPromise;

/**
 * @template V
 * @extends SyncPromise<V>
 */
class Deferred extends SyncPromise
{
    /**
     * @template T
     * @param callable(): T $executor
     * @return self<T>
     */
    public static function create(callable $executor): self
    {
        return new self($executor);
    }
}
