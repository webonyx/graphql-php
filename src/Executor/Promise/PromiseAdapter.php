<?php declare(strict_types=1);

namespace GraphQL\Executor\Promise;

/**
 * Provides a means for integration of async PHP platforms ([related docs](data-fetching.md#async-php)).
 */
interface PromiseAdapter
{
    /**
     * Is the value a promise or a deferred of the underlying platform?
     *
     * @param mixed $value
     *
     * @api
     */
    public function isThenable($value): bool;

    /**
     * Converts thenable of the underlying platform into GraphQL\Executor\Promise\Promise instance.
     *
     * @param mixed $thenable
     *
     * @api
     */
    public function convertThenable($thenable): Promise;

    /**
     * Accepts our Promise wrapper, extracts adopted promise out of it and executes actual `then` logic described
     * in Promises/A+ specs. Then returns new wrapped instance of GraphQL\Executor\Promise\Promise.
     *
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
     *
     * @api
     */
    public function then(Promise $promise, ?callable $onFulfilled = null, ?callable $onRejected = null): Promise;

    /**
     * Creates a Promise from the given resolver callable.
     *
     * @template V
     *
     * @param callable(callable $resolve, callable $reject): void $resolver
     *
     * @api
     */
    public function create(callable $resolver): Promise;

    /**
     * Creates a fulfilled Promise for a value if the value is not a promise.
     *
     * @template V
     *
     * @param V $value
     *
     * @return Promise<V>
     *
     * @api
     */
    public function createFulfilled($value = null): Promise;

    /**
     * Creates a rejected promise for a reason if the reason is not a promise.
     *
     * If the provided reason is a promise, then it is returned as-is.
     *
     * @api
     */
    public function createRejected(\Throwable $reason): Promise;

    /**
     * Given an iterable of promises (or values), returns a promise that is fulfilled when all the
     * items in the iterable are fulfilled.
     *
     * @template V
     *
     * @param iterable<Promise<V>|V> $promisesOrValues
     *
     * @return Promise<TODO>
     *
     * @api
     */
    public function all(iterable $promisesOrValues): Promise;
}
