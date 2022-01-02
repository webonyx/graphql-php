<?php

declare(strict_types=1);

namespace GraphQL\Executor\Promise\Adapter;

use Amp\Deferred;
use Amp\Failure;
use function Amp\Promise\all;
use Amp\Promise as AmpPromise;
use Amp\Success;
use function array_replace;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use Throwable;

class AmpPromiseAdapter implements PromiseAdapter
{
    public function isThenable($value): bool
    {
        return $value instanceof AmpPromise;
    }

    public function convertThenable($thenable): Promise
    {
        return new Promise($thenable, $this);
    }

    public function then(Promise $promise, ?callable $onFulfilled = null, ?callable $onRejected = null): Promise
    {
        $deferred = new Deferred();
        $onResolve = static function (?Throwable $reason, $value) use ($onFulfilled, $onRejected, $deferred): void {
            if (null === $reason && null !== $onFulfilled) {
                self::resolveWithCallable($deferred, $onFulfilled, $value);
            } elseif (null === $reason) {
                $deferred->resolve($value);
            } elseif (null !== $onRejected) {
                self::resolveWithCallable($deferred, $onRejected, $reason);
            } else {
                $deferred->fail($reason);
            }
        };

        /** @var AmpPromise<mixed> $adoptedPromise */
        $adoptedPromise = $promise->adoptedPromise;
        $adoptedPromise->onResolve($onResolve);

        return new Promise($deferred->promise(), $this);
    }

    public function create(callable $resolver): Promise
    {
        $deferred = new Deferred();

        $resolver(
            static function ($value) use ($deferred): void {
                $deferred->resolve($value);
            },
            static function (Throwable $exception) use ($deferred): void {
                $deferred->fail($exception);
            }
        );

        return new Promise($deferred->promise(), $this);
    }

    public function createFulfilled($value = null): Promise
    {
        $promise = new Success($value);

        return new Promise($promise, $this);
    }

    public function createRejected(Throwable $reason): Promise
    {
        $promise = new Failure($reason);

        return new Promise($promise, $this);
    }

    public function all(array $promisesOrValues): Promise
    {
        /** @var array<AmpPromise<mixed>> $promises */
        $promises = [];
        foreach ($promisesOrValues as $key => $item) {
            if ($item instanceof Promise) {
                /** @var AmpPromise<mixed> $ampPromise */
                $ampPromise = $item->adoptedPromise;
                $promises[$key] = $ampPromise;
            } elseif ($item instanceof AmpPromise) {
                $promises[$key] = $item;
            }
        }

        $deferred = new Deferred();

        $onResolve = static function (?Throwable $reason, ?array $values) use ($promisesOrValues, $deferred): void {
            if (null === $reason) {
                $deferred->resolve(array_replace($promisesOrValues, $values));

                return;
            }

            $deferred->fail($reason);
        };

        all($promises)->onResolve($onResolve);

        return new Promise($deferred->promise(), $this);
    }

    /**
     * @template TArgument
     * @template TResult
     *
     * @param Deferred<TResult> $deferred
     * @param callable(TArgument): TResult $callback
     * @param TArgument $argument
     */
    private static function resolveWithCallable(Deferred $deferred, callable $callback, $argument): void
    {
        try {
            $result = $callback($argument);
        } catch (Throwable $exception) {
            $deferred->fail($exception);

            return;
        }

        if ($result instanceof Promise) {
            /** @var TResult $result */
            $result = $result->adoptedPromise;
        }

        $deferred->resolve($result);
    }
}
