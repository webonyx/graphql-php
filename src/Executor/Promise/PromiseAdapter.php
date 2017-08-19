<?php
namespace GraphQL\Executor\Promise;

/**
 * Provides a means for integration of async PHP platforms ([related docs](data-fetching.md#async-php))
 */
interface PromiseAdapter
{
    /**
     * Return true if the value is a promise or a deferred of the underlying platform
     *
     * @api
     * @param mixed $value
     * @return bool
     */
    public function isThenable($value);

    /**
     * Converts thenable of the underlying platform into GraphQL\Executor\Promise\Promise instance
     *
     * @api
     * @param object $thenable
     * @return Promise
     */
    public function convertThenable($thenable);

    /**
     * Accepts our Promise wrapper, extracts adopted promise out of it and executes actual `then` logic described
     * in Promises/A+ specs. Then returns new wrapped instance of GraphQL\Executor\Promise\Promise.
     *
     * @api
     * @param Promise $promise
     * @param callable|null $onFulfilled
     * @param callable|null $onRejected
     *
     * @return Promise
     */
    public function then(Promise $promise, callable $onFulfilled = null, callable $onRejected = null);

    /**
     * Creates a Promise
     *
     * Expected resolver signature:
     *     function(callable $resolve, callable $reject)
     *
     * @api
     * @param callable $resolver
     * @return Promise
     */
    public function create(callable $resolver);

    /**
     * Creates a fulfilled Promise for a value if the value is not a promise.
     *
     * @api
     * @param mixed $value
     * @return Promise
     */
    public function createFulfilled($value = null);

    /**
     * Creates a rejected promise for a reason if the reason is not a promise. If
     * the provided reason is a promise, then it is returned as-is.
     *
     * @api
     * @param \Throwable $reason
     * @return Promise
     */
    public function createRejected($reason);

    /**
     * Given an array of promises (or values), returns a promise that is fulfilled when all the
     * items in the array are fulfilled.
     *
     * @api
     * @param array $promisesOrValues Promises or values.
     * @return Promise
     */
    public function all(array $promisesOrValues);
}
