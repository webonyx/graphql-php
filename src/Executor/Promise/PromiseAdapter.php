<?php

namespace GraphQL\Executor\Promise;

interface PromiseAdapter
{
    /**
     * Return true if value is promise
     *
     * @param  mixed $value
     *
     * @return bool
     */
    public function isPromise($value);

    /**
     * Creates a Promise
     *
     * @param callable $resolver
     *
     * @return Promise
     */
    public function createPromise(callable $resolver);

    /**
     * Creates a full filed Promise for a value if the value is not a promise.
     *
     * @param mixed $promiseOrValue
     *
     * @return Promise a full filed Promise
     */
    public function createResolvedPromise($promiseOrValue = null);

    /**
     * Creates a rejected promise for a reason if the reason is not a promise. If
     * the provided reason is a promise, then it is returned as-is.
     *
     * @param mixed $reason
     *
     * @return Promise a rejected promise
     */
    public function createRejectedPromise($reason);

    /**
     * Given an array of promises, return a promise that is fulfilled when all the
     * items in the array are fulfilled.
     *
     * @param mixed $promisesOrValues Promises or values.
     *
     * @return Promise equivalent to Promise.all result
     */
    public function createPromiseAll($promisesOrValues);
}
