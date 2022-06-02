<?php

declare(strict_types=1);

namespace GraphQL\Executor\Promise;

use Throwable;

/**
 * Provides a means for integration of async PHP platforms ([related docs](data-fetching.md#async-php)).
 */
interface PromiseAdapter
{
    /**
     * Is the value a promise or a deferred of the underlying platform?
     *
     * @param mixed $value
     *
     * @api
     */
    public function isThenable($value): bool;

    /**
     * Converts thenable of the underlying platform into GraphQL\Executor\Promise\Promise instance.
     *
     * @param object $thenable
     *
     * @api
     */
    public function convertThenable($thenable): Promise;

    /**
     * Accepts our Promise wrapper, extracts adopted promise out of it and executes actual `then` logic described
     * in Promises/A+ specs. Then returns new wrapped instance of GraphQL\Executor\Promise\Promise.
     *
     * @api
     */
    public function then(Promise $promise, ?callable $onFulfilled = null, ?callable $onRejected = null): Promise;

    /**
     * Creates a Promise from the given resolver callable.
     *
     * @param callable(callable $resolve, callable $reject): void $resolver
     *
     * @api
     */
    public function create(callable $resolver): Promise;

    /**
     * Creates a fulfilled Promise for a value if the value is not a promise.
     *
     * @param mixed $value
     *
     * @api
     */
    public function createFulfilled($value = null): Promise;

    /**
     * Creates a rejected promise for a reason if the reason is not a promise.
     *
     * If the provided reason is a promise, then it is returned as-is.
     *
     * @api
     */
    public function createRejected(Throwable $reason): Promise;

    /**
     * Given an array of promises (or values), returns a promise that is fulfilled when all the
     * items in the array are fulfilled.
     *
     * @param array<Promise|mixed> $promisesOrValues
     *
     * @api
     */
    public function all(array $promisesOrValues): Promise;
}
