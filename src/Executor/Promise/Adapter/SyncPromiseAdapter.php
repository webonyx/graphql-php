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

    /**
     * @template T
     * @template TFulfilled of mixed
     * @template TRejected of mixed
     *
     * @param Promise<T> $promise
     * @param (callable(T): (Promise<TFulfilled>|TFulfilled))|null $onFulfilled
     * @param (callable(mixed): (Promise<TRejected>|TRejected))|null $onRejected
     *
     * @return Promise<(
     *   $onFulfilled is not null
     *     ? ($onRejected is not null ? TFulfilled|TRejected : TFulfilled)
     *     : ($onRejected is not null ? TRejected : T)
     * )>
     */
    public function then(Promise $promise, ?callable $onFulfilled = null, ?callable $onRejected = null): Promise
    {
        $adoptedPromise = $promise->adoptedPromise;
        \assert($adoptedPromise instanceof SyncPromise);

        return new Promise($adoptedPromise->then($onFulfilled, $onRejected), $this);
    }

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
     * @template V
     *
     * @param V $value
     *
     * @return Promise<V>
     */
    public function createFulfilled($value = null): Promise
    {
        $promise = new SyncPromise();

        return new Promise($promise->resolve($value), $this);
    }

    /**
     * @template V of \Throwable
     *
     * @param V $reason
     *
     * @return Promise<V>
     */
    public function createRejected(\Throwable $reason): Promise
    {
        $promise = new SyncPromise();

        return new Promise($promise->reject($reason), $this);
    }

    /**
     * @template V
     *
     * @param iterable<Promise<V>|V> $promisesOrValues
     *
     * @return Promise<V[]>
     */
    public function all(iterable $promisesOrValues): Promise
    {
        \assert(
            \is_array($promisesOrValues),
            'SyncPromiseAdapter::all(): Argument #1 ($promisesOrValues) must be of type array'
        );

        $all = new SyncPromise();

        $total = \count($promisesOrValues);
        $count = 0;
        $result = [];

        foreach ($promisesOrValues as $index => $promiseOrValue) {
            if ($promiseOrValue instanceof Promise) {
                $result[$index] = null;
                $promiseOrValue->then(
                    static function ($value) use ($index, &$count, $total, &$result, $all): void {
                        $result[$index] = $value;
                        ++$count;
                        if ($count < $total) {
                            return;
                        }

                        $all->resolve($result);
                    },
                    [$all, 'reject']
                );
            } else {
                $result[$index] = $promiseOrValue;
                ++$count;
            }
        }

        if ($count === $total) {
            $all->resolve($result);
        }

        return new Promise($all, $this);
    }

    /**
     * Synchronously wait when promise completes.
     *
     * @template V
     *
     * @param Promise<V> $promise
     *
     * @throws InvariantViolation
     *
     * @return V
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

    /**
     * Execute just before starting to run promise completion.
     *
     * @template V
     *
     * @param Promise<V> $promise
     */
    protected function beforeWait(Promise $promise): void
    {
    }

    /**
     * Execute while running promise completion.
     *
     * @template V
     *
     * @param Promise<V> $promise
     */
    protected function onWait(Promise $promise): void
    {
    }
}
