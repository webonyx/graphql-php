<?php

namespace GraphQL\Executor\Promise\Adapter;

use GraphQL\Executor\Promise\PromiseAdapter;

class GenericPromiseAdapter implements PromiseAdapter
{
    public function isPromise($value)
    {
        return false;
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
