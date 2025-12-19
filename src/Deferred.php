<?php declare(strict_types=1);

namespace GraphQL;

use GraphQL\Executor\Promise\Adapter\SyncPromise;

/**
 * @phpstan-import-type Executor from SyncPromise
 */
class Deferred extends SyncPromise
{
    /** @param Executor $executor */
    public static function create(callable $executor): self
    {
        return new self($executor);
    }

    /** @param Executor $executor */
    public function __construct(callable $executor)
    {
        parent::__construct($executor);
    }
}
