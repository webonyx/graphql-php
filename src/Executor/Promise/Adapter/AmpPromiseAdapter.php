<?php

declare(strict_types=1);

namespace GraphQL\Executor\Promise\Adapter;

use Amp\Deferred;
use Amp\Failure;
use Amp\Promise as AmpPromise;
use Amp\Success;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use Throwable;
use function Amp\Promise\all;
use function array_replace;

class AmpPromiseAdapter implements PromiseAdapter
{
    /**
     * @inheritdoc
     */
    public function isThenable($value) : bool
    {
        return $value instanceof AmpPromise;
    }

    /**
     * @inheritdoc
     */
    public function convertThenable($thenable) : Promise
    {
        return new Promise($thenable, $this);
    }

    /**
     * @inheritdoc
     */
    public function then(Promise $promise, ?callable $onFulfilled = null, ?callable $onRejected = null) : Promise
    {
        $deferred  = new Deferred();
        $onResolve = static function (?Throwable $reason, $value) use ($onFulfilled, $onRejected, $deferred) : void {
            if ($reason === null && $onFulfilled !== null) {
                self::resolveWithCallable($deferred, $onFulfilled, $value);
            } elseif ($reason === null) {
                $deferred->resolve($value);
            } elseif ($onRejected !== null) {
                self::resolveWithCallable($deferred, $onRejected, $reason);
            } else {
                $deferred->fail($reason);
            }
        };

        /** @var AmpPromise $adoptedPromise */
        $adoptedPromise = $promise->adoptedPromise;
        $adoptedPromise->onResolve($onResolve);

        return new Promise($deferred->promise(), $this);
    }

    /**
     * @inheritdoc
     */
    public function create(callable $resolver) : Promise
    {
        $deferred = new Deferred();

        $resolver(
            static function ($value) use ($deferred) : void {
                $deferred->resolve($value);
            },
            static function (Throwable $exception) use ($deferred) : void {
                $deferred->fail($exception);
            }
        );

        return new Promise($deferred->promise(), $this);
    }

    /**
     * @inheritdoc
     */
    public function createFulfilled($value = null) : Promise
    {
        $promise = new Success($value);

        return new Promise($promise, $this);
    }

    /**
     * @inheritdoc
     */
    public function createRejected($reason) : Promise
    {
        $promise = new Failure($reason);

        return new Promise($promise, $this);
    }

    /**
     * @inheritdoc
     */
    public function all(array $promisesOrValues) : Promise
    {
        /** @var AmpPromise[] $promises */
        $promises = [];
        foreach ($promisesOrValues as $key => $item) {
            if ($item instanceof Promise) {
                $promises[$key] = $item->adoptedPromise;
            } elseif ($item instanceof AmpPromise) {
                $promises[$key] = $item;
            }
        }

        $deferred = new Deferred();

        $onResolve = static function (?Throwable $reason, ?array $values) use ($promisesOrValues, $deferred) : void {
            if ($reason === null) {
                $deferred->resolve(array_replace($promisesOrValues, $values));

                return;
            }

            $deferred->fail($reason);
        };

        all($promises)->onResolve($onResolve);

        return new Promise($deferred->promise(), $this);
    }

    private static function resolveWithCallable(Deferred $deferred, callable $callback, $argument) : void
    {
        try {
            $result = $callback($argument);
        } catch (Throwable $exception) {
            $deferred->fail($exception);

            return;
        }

        if ($result instanceof Promise) {
            $result = $result->adoptedPromise;
        }

        $deferred->resolve($result);
    }
}
