<?php
namespace GraphQL\Executor\Promise\Adapter;

use GraphQL\Deferred;
use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Utils;

/**
 * Class SyncPromiseAdapter
 *
 * Allows changing order of field resolution even in sync environments
 * (by leveraging queue of deferreds and promises)
 *
 * @package GraphQL\Executor\Promise\Adapter
 */
class SyncPromiseAdapter implements PromiseAdapter
{
    /**
     * @inheritdoc
     */
    public function isThenable($value)
    {
        return $value instanceof Deferred;
    }

    /**
     * @inheritdoc
     */
    public function convertThenable($thenable)
    {
        if (!$thenable instanceof Deferred) {
            throw new InvariantViolation('Expected instance of GraphQL\Deferred, got ' . Utils::printSafe($thenable));
        }
        return new Promise($thenable->promise, $this);
    }

    /**
     * @inheritdoc
     */
    public function then(Promise $promise, callable $onFulfilled = null, callable $onRejected = null)
    {
        /** @var SyncPromise $promise */
        $promise = $promise->adoptedPromise;
        return new Promise($promise->then($onFulfilled, $onRejected), $this);
    }

    /**
     * @inheritdoc
     */
    public function create(callable $resolver)
    {
        $promise = new SyncPromise();

        try {
            $resolver(
                [$promise, 'resolve'],
                [$promise, 'reject']
            );
        } catch (\Throwable $e) {
            $promise->reject($e);
        }

        return new Promise($promise, $this);
    }

    /**
     * @inheritdoc
     */
    public function createFulfilled($value = null)
    {
        $promise = new SyncPromise();
        return new Promise($promise->resolve($value), $this);
    }

    /**
     * @inheritdoc
     */
    public function createRejected(\Throwable $reason)
    {
        $promise = new SyncPromise();
        return new Promise($promise->reject($reason), $this);
    }

    /**
     * @inheritdoc
     */
    public function all(array $promisesOrValues)
    {
        $all = new SyncPromise();

        $total = count($promisesOrValues);
        $count = 0;
        $result = [];

        foreach ($promisesOrValues as $index => $promiseOrValue) {
            if ($promiseOrValue instanceof Promise) {
                $result[$index] = null;
                $promiseOrValue->then(
                    function($value) use ($index, &$count, $total, &$result, $all) {
                        $result[$index] = $value;
                        $count++;
                        if ($count >= $total) {
                            $all->resolve($result);
                        }
                    },
                    [$all, 'reject']
                );
            } else {
                $result[$index] = $promiseOrValue;
                $count++;
            }
        }
        if ($count === $total) {
            $all->resolve($result);
        }
        return new Promise($all, $this);
    }

    /**
     * Synchronously wait when promise completes
     *
     * @param Promise $promise
     * @return mixed
     */
    public function wait(Promise $promise)
    {
        $dfdQueue = Deferred::getQueue();
        $promiseQueue = SyncPromise::getQueue();

        while (
            $promise->adoptedPromise->state === SyncPromise::PENDING &&
            !($dfdQueue->isEmpty() && $promiseQueue->isEmpty())
        ) {
            Deferred::runQueue();
            SyncPromise::runQueue();
        }

        /** @var SyncPromise $syncPromise */
        $syncPromise = $promise->adoptedPromise;

        if ($syncPromise->state === SyncPromise::FULFILLED) {
            return $syncPromise->result;
        } else if ($syncPromise->state === SyncPromise::REJECTED) {
            throw $syncPromise->result;
        }

        throw new InvariantViolation("Could not resolve promise");
    }
}
