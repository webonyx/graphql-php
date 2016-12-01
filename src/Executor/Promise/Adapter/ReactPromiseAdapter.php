<?php

namespace GraphQL\Executor\Promise\Adapter;

use GraphQL\Executor\Promise\PromiseAdapter;;
use React\Promise\FulfilledPromise;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

class ReactPromiseAdapter implements PromiseAdapter
{
    /**
     * Return true if value is promise
     *
     * @param  mixed $value
     *
     * @return bool
     */
    public function isPromise($value)
    {
        return $value instanceof PromiseInterface;
    }

    /**
     * Accepts value qualified by `isPromise` and returns other promise.
     *
     * @param Promise $promise
     * @param callable|null $onFullFilled
     * @param callable|null $onRejected
     * @return mixed
     */
    public function then($promise, callable $onFullFilled = null, callable $onRejected = null)
    {
        return $promise->then($onFullFilled, $onRejected);
    }

    /**
     * @inheritdoc
     *
     * @return PromiseInterface
     */
    public function createPromise(callable $resolver)
    {
        $promise = new Promise($resolver);

        return $promise;
    }

    /**
     * @inheritdoc
     *
     * @return FulfilledPromise
     */
    public function createResolvedPromise($promiseOrValue = null)
    {
        return \React\Promise\resolve($promiseOrValue);
    }

    /**
     * @inheritdoc
     *
     * @return \React\Promise\RejectedPromise
     */
    public function createRejectedPromise($reason)
    {
        return \React\Promise\reject($reason);
    }

    /**
     * Given an array of promises, return a promise that is fulfilled when all the
     * items in the array are fulfilled.
     *
     * @param mixed $promisesOrValues Promises or values.
     *
     * @return mixed a Promise
     */
    public function createPromiseAll($promisesOrValues)
    {
        return \React\Promise\all($promisesOrValues);
    }
}
