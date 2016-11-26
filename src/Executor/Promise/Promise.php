<?php

namespace GraphQL\Executor\Promise;

/**
 * A simple Promise representation
 * this interface helps to document the code
 */
interface Promise
{
    /**
     * @param callable|null $onFullFilled
     * @param callable|null $onRejected
     *
     * @return Promise
     */
    public function then(callable $onFullFilled = null, callable $onRejected = null);
}
