<?php declare(strict_types=1);

namespace GraphQL\Executor\Promise;

use Amp\Promise as AmpPromise;
use GraphQL\Executor\Promise\Adapter\SyncPromise;
use React\Promise\PromiseInterface as ReactPromise;

/**
 * Convenience wrapper for promises represented by Promise Adapter.
 *
 * @template T
 */
class Promise
{
    /** @var SyncPromise<T>|ReactPromise|AmpPromise<T> */
    public $adoptedPromise;

    private PromiseAdapter $adapter;

    /**
     * @param SyncPromise<T>|ReactPromise|AmpPromise<T> $adoptedPromise
     */
    public function __construct($adoptedPromise, PromiseAdapter $adapter)
    {
        $this->adoptedPromise = $adoptedPromise;
        $this->adapter = $adapter;
    }

    /**
     * @template TFulfilled
     * @template TRejected
     *
     * @param (callable(T): (Promise<TFulfilled>|TFulfilled))|null $onFulfilled
     * @param (callable(mixed): (Promise<TRejected>|TRejected))|null $onRejected
     *
     * @return Promise<(
     *   $onFulfilled is not null
     *     ? ($onRejected is not null ? TFulfilled|TRejected : TFulfilled)
     *     : ($onRejected is not null ? TRejected : T)
     * )>
     */
    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): Promise
    {
        return $this->adapter->then($this, $onFulfilled, $onRejected);
    }
}
