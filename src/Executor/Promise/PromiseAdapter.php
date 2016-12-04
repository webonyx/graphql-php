<?php
namespace GraphQL\Executor\Promise;

interface PromiseAdapter
{
    /**
     * Return true if value is promise of underlying system
     *
     * @param mixed $value
     * @return bool
     */
    public function isThenable($value);

    /**
     * Converts thenable of underlying system into Promise instance
     *
     * @param object $thenable
     * @return Promise
     */
    public function convertThenable($thenable);

    /**
     * Accepts our Promise wrapper, extracts adopted promise out of it and executes actual `then` logic described
     * in Promises/A+ specs. Then returns new wrapped Promise instance.
     *
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
     * @param callable $resolver

     * @return Promise
     */
    public function create(callable $resolver);

    /**
     * Creates a fulfilled Promise for a value if the value is not a promise.
     *
     * @param mixed $value
     *
     * @return Promise
     */
    public function createFulfilled($value = null);

    /**
     * Creates a rejected promise for a reason if the reason is not a promise. If
     * the provided reason is a promise, then it is returned as-is.
     *
     * @param mixed $reason
     *
     * @return Promise
     */
    public function createRejected(\Exception $reason);

    /**
     * Given an array of promises (or values), returns a promise that is fulfilled when all the
     * items in the array are fulfilled.
     *
     * @param array $promisesOrValues Promises or values.
     *
     * @return Promise
     */
    public function all(array $promisesOrValues);
}
