<?php declare(strict_types=1);

namespace GraphQL;

use GraphQL\Executor\Promise\Adapter\SyncPromise;

/**
 * User-facing promise class for deferred field resolution.
 *
 * @phpstan-type Executor callable(): mixed
 */
class Deferred extends SyncPromise
{
    /** @param Executor $executor */
    public static function create(callable $executor): self
    {
        return new self($executor);
    }

    /**
     * Create a new Deferred promise and enqueue its execution.
     *
     * @api
     *
     * @param Executor $executor
     */
    public function __construct(callable $executor)
    {
        parent::__construct($executor);
    }
}
