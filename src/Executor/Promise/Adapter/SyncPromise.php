<?php declare(strict_types=1);

namespace GraphQL\Executor\Promise\Adapter;

use GraphQL\Error\InvariantViolation;

/**
 * Synchronous promise implementation following Promises A+ spec.
 *
 * Uses a hybrid approach for optimal memory and performance:
 * - Lightweight closures in queue (fast execution)
 * - Heavy payload (callbacks) stored on promise objects and cleared after use
 *
 * Library users should use @see \GraphQL\Deferred to create promises.
 *
 * @phpstan-type Executor callable(): mixed
 */
class SyncPromise
{
    public const PENDING = 'pending';
    public const FULFILLED = 'fulfilled';
    public const REJECTED = 'rejected';

    /**
     * Current promise state: PENDING, FULFILLED, or REJECTED.
     *
     * @var 'pending'|'fulfilled'|'rejected'
     */
    public string $state = self::PENDING;

    /**
     * Resolved value or rejection reason.
     *
     * @var mixed
     */
    public $result;

    /**
     * Executor for deferred promises.
     *
     * @var (callable(): mixed)|null
     */
    protected $executor;

    /**
     * Promises created in `then` method awaiting resolution.
     *
     * @var array<
     *     int,
     *     array{
     *         self,
     *         (callable(mixed): mixed)|null,
     *         (callable(\Throwable): mixed)|null,
     *     },
     * >
     */
    protected array $waiting = [];

    /**
     * Process all queued promises until the queue is empty.
     *
     * TODO remove before merging, not part of public API
     */
    public static function runQueue(): void
    {
        SyncPromiseQueue::run();
    }

    /** @param Executor|null $executor */
    public function __construct(?callable $executor = null)
    {
        if ($executor === null) {
            return;
        }

        $this->executor = $executor;
        SyncPromiseQueue::enqueue(function (): void {
            $this->runExecutor();
        });
    }

    /** Execute the deferred callback and clear it for garbage collection. */
    protected function runExecutor(): void
    {
        $executor = $this->executor;
        $this->executor = null; // Clear for garbage collection

        try {
            $this->resolve($executor());
        } catch (\Throwable $e) {
            $this->reject($e);
        }
    }

    /**
     * @param mixed $value
     *
     * @throws \Exception
     */
    public function resolve($value): self
    {
        switch ($this->state) {
            case self::PENDING:
                if ($value === $this) {
                    throw new \Exception('Cannot resolve promise with self');
                }

                if (is_object($value) && method_exists($value, 'then')) {
                    $value->then(
                        function ($resolvedValue): void {
                            $this->resolve($resolvedValue);
                        },
                        function (\Throwable $reason): void {
                            $this->reject($reason);
                        }
                    );

                    return $this;
                }

                $this->state = self::FULFILLED;
                $this->result = $value;
                $this->enqueueWaitingPromises();
                break;
            case self::FULFILLED:
                if ($this->result !== $value) {
                    throw new \Exception('Cannot change value of fulfilled promise');
                }

                break;
            case self::REJECTED:
                throw new \Exception('Cannot resolve rejected promise');
        }

        return $this;
    }

    /**
     * @throws \Exception
     *
     * @return $this
     */
    public function reject(\Throwable $reason): self
    {
        switch ($this->state) {
            case self::PENDING:
                $this->state = self::REJECTED;
                $this->result = $reason;
                $this->enqueueWaitingPromises();
                break;
            case self::REJECTED:
                if ($reason !== $this->result) {
                    throw new \Exception('Cannot change rejection reason');
                }

                break;
            case self::FULFILLED:
                throw new \Exception('Cannot reject fulfilled promise');
        }

        return $this;
    }

    /**
     * @param (callable(mixed): mixed)|null $onFulfilled
     * @param (callable(\Throwable): mixed)|null $onRejected
     *
     * @throws InvariantViolation
     */
    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): self
    {
        if ($this->state === self::REJECTED && $onRejected === null) {
            return $this;
        }

        if ($this->state === self::FULFILLED && $onFulfilled === null) {
            return $this;
        }

        $child = new self();
        $this->waiting[] = [$child, $onFulfilled, $onRejected];

        if ($this->state !== self::PENDING) {
            $this->enqueueWaitingPromises();
        }

        return $child;
    }

    /** @throws InvariantViolation */
    private function enqueueWaitingPromises(): void
    {
        if ($this->state === self::PENDING) {
            throw new InvariantViolation('Cannot enqueue derived promises when parent is still pending');
        }

        $result = $this->result;

        foreach ($this->waiting as $entry) {
            SyncPromiseQueue::enqueue(static function () use ($entry, $result): void {
                [$child, $onFulfilled, $onRejected] = $entry;

                try {
                    if ($result instanceof \Throwable) {
                        if ($onRejected === null) {
                            $child->reject($result);
                        } else {
                            $child->resolve($onRejected($result));
                        }
                    } else {
                        $child->resolve(
                            $onFulfilled === null
                                ? $result
                                : $onFulfilled($result)
                        );
                    }
                } catch (\Throwable $e) {
                    $child->reject($e);
                }
            });
        }

        $this->waiting = [];
    }

    /**
     * @param callable(\Throwable): mixed $onRejected
     *
     * @throws InvariantViolation
     *
     * @deprecated TODO remove in next major version, use ->then(null, $onRejected) instead
     */
    public function catch(callable $onRejected): self
    {
        return $this->then(null, $onRejected);
    }
}
