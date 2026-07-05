<?php declare(strict_types=1);

namespace GraphQL\Executor\Promise;

use GraphQL\Error\InvariantViolation;

/**
 * Convenience wrapper for promises represented by Promise Adapter.
 *
 * The adopted promise is whatever the configured adapter produces (e.g.
 * {@see \GraphQL\Executor\Promise\Adapter\SyncPromise}, a ReactPHP promise or an
 * amphp Future). It is kept as a generic so the concrete platform type never has
 * to be named in this class, which would otherwise require importing a class
 * that may not exist for the installed platform.
 *
 * @template TAdopted = mixed
 */
class Promise
{
    /**
     * @phpstan-var TAdopted
     *
     * @readonly
     */
    public $adoptedPromise;

    /** @phpstan-var PromiseAdapter<TAdopted> */
    private PromiseAdapter $adapter;

    /**
     * @phpstan-param TAdopted $adoptedPromise
     * @phpstan-param PromiseAdapter<TAdopted> $adapter
     *
     * @throws InvariantViolation
     */
    public function __construct($adoptedPromise, PromiseAdapter $adapter)
    {
        if ($adoptedPromise instanceof self) {
            $selfClass = self::class;
            throw new InvariantViolation("Expected promise from adapted system, got {$selfClass}.");
        }

        $this->adoptedPromise = $adoptedPromise;
        $this->adapter = $adapter;
    }

    /** @phpstan-return Promise<TAdopted> */
    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): Promise
    {
        return $this->adapter->then($this, $onFulfilled, $onRejected);
    }
}
