<?php declare(strict_types=1);

namespace GraphQL\Executor\Promise\Adapter;

use GraphQL\Error\InvariantViolation;

/**
 * Base class for synchronous promises implementing Promises A+ spec.
 *
 * Library users should use @see \GraphQL\Deferred to create promises.
 *
 * @see RootSyncPromise for promises created with an executor
 * @see ChildSyncPromise for promises created via then() chaining
 */
abstract class SyncPromise
{
    public const PENDING = 'pending';
    public const FULFILLED = 'fulfilled';
    public const REJECTED = 'rejected';

    /** Current promise state: PENDING, FULFILLED, or REJECTED. */
    public string $state = self::PENDING;

    /**
     * Resolved value or rejection reason.
     *
     * @var mixed
     */
    public $result;

    /**
     * Child promises waiting for this promise to resolve.
     *
     * @var array<int, ChildSyncPromise>
     */
    protected array $waiting = [];

    /** Execute the queued task for this promise. */
    abstract public function runQueuedTask(): void;

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
                        function ($reason): void {
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
        if ($this->state === self::REJECTED
            && $onRejected === null
        ) {
            return $this;
        }

        if ($this->state === self::FULFILLED
            && $onFulfilled === null
        ) {
            return $this;
        }

        $child = new ChildSyncPromise();
        $child->onFulfilled = $onFulfilled;
        $child->onRejected = $onRejected;
        $this->waiting[] = $child;

        if ($this->state !== self::PENDING) {
            $this->enqueueWaitingPromises();
        }

        return $child;
    }

    /** @throws InvariantViolation */
    protected function enqueueWaitingPromises(): void
    {
        if ($this->state === self::PENDING) {
            throw new InvariantViolation('Cannot enqueue derived promises when parent is still pending');
        }

        $state = $this->state;
        $result = $this->result;
        $queue = SyncPromiseQueue::getInstance();

        foreach ($this->waiting as $child) {
            $child->parentState = $state;
            $child->parentResult = $result;
            $queue->enqueue($child);
        }

        $this->waiting = [];
    }
}
