<?php

namespace GraphQL\Executor\Promise\Adapter;

use GraphQL\Executor\Promise\PromiseAdapter;

class GenericPromiseAdapter implements PromiseAdapter
{
    public function isPromise($value)
    {
        return false;
    }

    /**
     * Accepts value qualified by `isPromise` and returns other promise.
     *
     * @param $promise
     * @param callable|null $onFullFilled
     * @param callable|null $onRejected
     * @return mixed
     */
    public function then($promise, callable $onFullFilled = null, callable $onRejected = null)
    {
        try {
            if (null !== $onFullFilled) {
                $promise = $onFullFilled($promise);
            }

            return $this->createResolvedPromise($promise);
        } catch (\Exception $e) {
            if (null !== $onRejected) {
                $onRejected($e);
            }
            return $this->createRejectedPromise($e);
        }
    }
    
    public function createPromise(callable $resolver)
    {
        return $resolver(function ($value) {
            return $value;
        });
    }

    public function createResolvedPromise($promiseOrValue = null)
    {
        return $promiseOrValue;
    }

    public function createRejectedPromise($reason)
    {
    }

    public function createPromiseAll($promisesOrValues)
    {
    }
}
