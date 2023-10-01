<?php declare(strict_types=1);

namespace GraphQL\Executor\Promise\Adapter;

use GraphQL\Deferred;
use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Utils\Utils;

/**
 * Allows changing order of field resolution even in sync environments
 * (by leveraging queue of deferreds and promises).
 */
class SyncPromiseAdapter implements PromiseAdapter
{
    public function isThenable($value): bool
    {
        return $value instanceof SyncPromise;
    }

    /** @throws InvariantViolation */
    public function convertThenable($thenable): Promise
    {
        if (! $thenable instanceof SyncPromise) {
            // End-users should always use Deferred (and don't use SyncPromise directly)
            $deferred = Deferred::class;
            $safeThenable = Utils::printSafe($thenable);
            throw new InvariantViolation("Expected instance of {$deferred}, got {$safeThenable}");
        }

        return new Promise($thenable, $this);
    }

    /** @throws InvariantViolation */
    public function then(Promise $promise, callable $onFulfilled = null, callable $onRejected = null): Promise
    {
        $adoptedPromise = $promise->adoptedPromise;
        \assert($adoptedPromise instanceof SyncPromise);

        return new Promise($adoptedPromise->then($onFulfilled, $onRejected), $this);
    }

    /**
     * @throws \Exception
     * @throws InvariantViolation
     */
    public function create(callable $resolver): Promise
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
     * @throws \Exception
     * @throws InvariantViolation
     */
    public function createFulfilled($value = null): Promise
    {
        $promise = new SyncPromise();

        return new Promise($promise->resolve($value), $this);
    }

    /**
     * @throws \Exception
     * @throws InvariantViolation
     */
    public function createRejected(\Throwable $reason): Promise
    {
        $promise = new SyncPromise();

        return new Promise($promise->reject($reason), $this);
    }

    /** @throws InvariantViolation */
    public function all(iterable $promisesOrValues): Promise
    {
        $all = new SyncPromise();

        $total = \is_array($promisesOrValues)
            ? \count($promisesOrValues)
            : \iterator_count($promisesOrValues);
        $count = 0;
        $result = [];

        $resolveAllWhenFinished = function () use (&$count, &$total, $all, &$result): void {
            if ($count === $total) {
                $all->resolve($result);
            }
        };

        foreach ($promisesOrValues as $index => $promiseOrValue) {
            if ($promiseOrValue instanceof Promise) {
                $result[$index] = null;
                $promiseOrValue->then(
                    static function ($value) use (&$result, $index, &$count, &$resolveAllWhenFinished): void {
                        $result[$index] = $value;
                        ++$count;
                        $resolveAllWhenFinished();
                    },
                    [$all, 'reject']
                );
            } else {
                $result[$index] = $promiseOrValue;
                ++$count;
            }
        }

        $resolveAllWhenFinished();

        return new Promise($all, $this);
    }

    /**
     * Synchronously wait when promise completes.
     *
     * @throws InvariantViolation
     *
     * @return mixed
     */
    public function wait(Promise $promise)
    {
        $this->beforeWait($promise);
        $taskQueue = SyncPromise::getQueue();

        $syncPromise = $promise->adoptedPromise;
        \assert($syncPromise instanceof SyncPromise);

        while (
            $syncPromise->state === SyncPromise::PENDING
            && ! $taskQueue->isEmpty()
        ) {
            SyncPromise::runQueue();
            $this->onWait($promise);
        }

        if ($syncPromise->state === SyncPromise::FULFILLED) {
            return $syncPromise->result;
        }

        if ($syncPromise->state === SyncPromise::REJECTED) {
            throw $syncPromise->result;
        }

        throw new InvariantViolation('Could not resolve promise');
    }

    /** Execute just before starting to run promise completion. */
    protected function beforeWait(Promise $promise): void {}

    /** Execute while running promise completion. */
    protected function onWait(Promise $promise): void {}
}
