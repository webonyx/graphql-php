<?php declare(strict_types=1);

namespace GraphQL\Executor;

use GraphQL\Executor\Promise\Promise;

class PromiseExecutor implements ExecutorImplementation
{
    private Promise $result;

    public function __construct(Promise $result)
    {
        $this->result = $result;
    }

    public function doExecute(): Promise
    {
        return $this->result;
    }
}
