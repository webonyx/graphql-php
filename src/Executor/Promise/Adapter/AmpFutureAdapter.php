<?php declare(strict_types=1);

namespace GraphQL\Executor\Promise\Adapter;

use Amp\DeferredFuture;
use Amp\Future;
use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;

/**
 * Allows integration with amphp/amp v3 (fiber-based futures).
 *
 * @see https://amphp.org/amp
 *
 * @implements PromiseAdapter<Future<mixed>>
 */
class AmpFutureAdapter implements PromiseAdapter
{
    public function isThenable($value): bool
    {
        return $value instanceof Future;
    }

    /**
     * @throws InvariantViolation
     *
     * @phpstan-return Promise<Future<mixed>>
     */
    public function convertThenable($thenable): Promise
    {
        return new Promise($thenable, $this);
    }

    /**
     * @phpstan-param Promise<covariant Future<mixed>> $promise
     *
     * @throws InvariantViolation
     *
     * @phpstan-return Promise<Future<mixed>>
     */
    public function then(Promise $promise, ?callable $onFulfilled = null, ?callable $onRejected = null): Promise
    {
        $future = $promise->adoptedPromise;

        $deferred = new DeferredFuture();

        static::observeFuture(
            $future,
            static function ($value) use ($deferred, $onFulfilled): void {
                try {
                    static::resolveDeferred(
                        $deferred,
                        $onFulfilled === null
                            ? $value
                            : $onFulfilled($value)
                    );
                } catch (\Throwable $fulfillmentException) {
                    $deferred->error($fulfillmentException);
                }
            },
            static function (\Throwable $exception) use ($deferred, $onRejected): void {
                if ($onRejected === null) {
                    $deferred->error($exception);

                    return;
                }

                try {
                    static::resolveDeferred($deferred, $onRejected($exception));
                } catch (\Throwable $rejectionException) {
                    $deferred->error($rejectionException);
                }
            }
        );

        return new Promise($deferred->getFuture(), $this);
    }

    /** @throws InvariantViolation */
    public function create(callable $resolver): Promise
    {
        $deferred = new DeferredFuture();

        try {
            $resolver(
                static function ($value) use ($deferred): void {
                    static::resolveDeferred($deferred, $value);
                },
                static function (\Throwable $exception) use ($deferred): void {
                    $deferred->error($exception);
                }
            );
        } catch (\Throwable $exception) {
            $deferred->error($exception);
        }

        return new Promise($deferred->getFuture(), $this);
    }

    /**
     * @throws \Error
     * @throws InvariantViolation
     *
     * @phpstan-return Promise<Future<mixed>>
     */
    public function createFulfilled($value = null): Promise
    {
        if ($value instanceof Promise) {
            return $value;
        }

        if ($value instanceof Future) {
            return new Promise($value, $this);
        }

        return new Promise(Future::complete($value), $this);
    }

    /**
     * @throws InvariantViolation
     *
     * @phpstan-return Promise<Future<mixed>>
     */
    public function createRejected(\Throwable $reason): Promise
    {
        return new Promise(Future::error($reason), $this);
    }

    /**
     * @throws \Error
     * @throws InvariantViolation
     */
    public function all(iterable $promisesOrValues): Promise
    {
        $items = is_array($promisesOrValues)
            ? $promisesOrValues
            : iterator_to_array($promisesOrValues);

        $deferredFuture = new DeferredFuture();
        $future = $deferredFuture->getFuture();
        $remaining = 0;

        foreach ($items as $key => $item) {
            if ($item instanceof Promise) {
                $item = $item->adoptedPromise;
            }

            if ($item instanceof Future) {
                ++$remaining;
                static::observeFuture(
                    $item,
                    static function ($value) use (&$items, $key, $deferredFuture, $future, &$remaining): void {
                        if ($future->isComplete()) {
                            return;
                        }

                        $items[$key] = $value;
                        --$remaining;
                        if ($remaining !== 0) {
                            return;
                        }

                        $deferredFuture->complete($items);
                    },
                    static function (\Throwable $exception) use ($deferredFuture, $future): void {
                        if ($future->isComplete()) {
                            return;
                        }

                        $deferredFuture->error($exception);
                    }
                );
            }
        }

        if ($remaining === 0 && ! $future->isComplete()) {
            $deferredFuture->complete($items);
        }

        return new Promise($future, $this);
    }

    /**
     * @param DeferredFuture<mixed> $deferred
     * @param mixed $value
     */
    protected static function resolveDeferred(DeferredFuture $deferred, $value): void
    {
        if ($value instanceof Promise) {
            $value = $value->adoptedPromise;
        }

        if ($value instanceof Future) {
            static::observeFuture(
                $value,
                static function ($value) use ($deferred): void {
                    $deferred->complete($value);
                },
                static function (\Throwable $exception) use ($deferred): void {
                    $deferred->error($exception);
                }
            );

            return;
        }

        $deferred->complete($value);
    }

    /**
     * @template T
     *
     * @param Future<T> $future
     * @param \Closure(T): void $onFulfilled
     * @param \Closure(\Throwable): void $onRejected
     */
    protected static function observeFuture(Future $future, \Closure $onFulfilled, \Closure $onRejected): void
    {
        $future
            ->map(static function ($value) use ($onFulfilled): void {
                $onFulfilled($value);
            })
            ->catch(static function (\Throwable $exception) use ($onRejected): void {
                $onRejected($exception);
            })
            ->ignore();
    }
}
