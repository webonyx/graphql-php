<?php declare(strict_types=1);

namespace GraphQL;

use GraphQL\Executor\Promise\Adapter\RootSyncPromise;

/**
 * User-facing promise class for deferred field resolution.
 *
 * @phpstan-type Executor callable(): mixed
 */
class Deferred extends RootSyncPromise
{
    /** @param Executor $executor */
    public static function create(callable $executor): self
    {
        return new self($executor);
    }
}
