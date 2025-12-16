<?php declare(strict_types=1);

namespace GraphQL\Benchmarks;

use GraphQL\Deferred;
use GraphQL\Executor\Promise\Adapter\SyncPromise;

/**
 * @OutputTimeUnit("microseconds", precision=3)
 *
 * @Warmup(5)
 *
 * @Revs(200)
 *
 * @Iterations(10)
 */
class DeferredBench
{
    public function benchSingleDeferred(): void
    {
        new Deferred(static fn () => 'value');
        SyncPromise::runQueue();
    }

    public function benchNestedDeferred(): void
    {
        new Deferred(static fn () => new Deferred(static fn () => null));
        SyncPromise::runQueue();
    }

    public function benchChain5(): void
    {
        $deferred = new Deferred(static fn () => 'value');
        $deferred->then(static fn ($v) => $v)
            ->then(static fn ($v) => $v)
            ->then(static fn ($v) => $v)
            ->then(static fn ($v) => $v)
            ->then(static fn ($v) => $v);
        SyncPromise::runQueue();
    }

    public function benchChain100(): void
    {
        $deferred = new Deferred(static fn () => 'value');
        $promise = $deferred;
        for ($i = 0; $i < 100; ++$i) {
            $promise = $promise->then(static fn ($v) => $v);
        }
        SyncPromise::runQueue();
    }

    public function benchManyDeferreds(): void
    {
        $fn = static fn () => null;
        for ($i = 0; $i < 1000; ++$i) {
            new Deferred($fn);
        }
        SyncPromise::runQueue();
    }

    public function benchManyNestedDeferreds(): void
    {
        for ($i = 0; $i < 5000; ++$i) {
            new Deferred(static fn () => new Deferred(static fn () => null));
        }
        SyncPromise::runQueue();
    }

    public function bench1000Chains(): void
    {
        $promises = [];
        for ($i = 0; $i < 1000; ++$i) {
            $d = new Deferred(static fn () => $i);
            $promises[] = $d->then(static fn ($v) => $v)
                ->then(static fn ($v) => $v)
                ->then(static fn ($v) => $v);
        }
        SyncPromise::runQueue();
    }
}
