<?php declare(strict_types=1);

namespace GraphQL\Executor\Promise\Adapter;

use GraphQL\Error\InvariantViolation;

/**
 * Simplistic (yet full-featured) implementation of Promises A+ spec for regular PHP `sync` mode
 * (using queue to defer promises execution).
 *
 * Library users are not supposed to use SyncPromise class in their resolvers.
 * Instead, they should use @see \GraphQL\Deferred which enforces `$executor` callback in the constructor.
 *
 * Root SyncPromise without explicit `$executor` will never resolve (actually throw while trying).
 * The whole point of Deferred is to ensure it never happens and that any resolver creates
 * at least one $executor to start the promise chain.
 *
 * @phpstan-type Executor callable(): mixed
 */
class SyncPromise
{
    public const PENDING = 'pending';
    public const FULFILLED = 'fulfilled';
    public const REJECTED = 'rejected';

    public string $state = self::PENDING;

    /**
     * Stored executor for deferred execution.
     * Storing on the promise instead of in a closure reduces memory usage.
     *
     * @var (callable(): mixed)|null
     */
    private $executor;

    /** @var mixed */
    public $result;

    /**
     * Promises created in `then` method of this promise and awaiting resolution of this promise.
     *
     * @var array<int, self>
     */
    protected array $waiting = [];

    /**
     * Callback to execute when parent promise fulfills (for waiting promises).
     *
     * @var (callable(mixed): mixed)|null
     */
    private $waitingOnFulfilled;

    /**
     * Callback to execute when parent promise rejects (for waiting promises).
     *
     * @var (callable(\Throwable): mixed)|null
     */
    private $waitingOnRejected;

    /** State of the parent promise when this waiting promise was enqueued. */
    private ?string $waitingState = null;

    /**
     * Result of the parent promise when this waiting promise was enqueued.
     *
     * @var mixed
     */
    private $waitingResult;

    /** @param Executor|null $executor */
    public function __construct(?callable $executor = null)
    {
        if ($executor === null) {
            return;
        }

        $this->executor = $executor;

        $queue = SyncPromiseQueue::getInstance();
        $queue->enqueue($this);
    }

    /**
     * Execute the queued task for this promise.
     *
     * Handles both root promises (with executor) and child promises (waiting on parent).
     */
    public function runQueuedTask(): void
    {
        $executor = $this->executor;

        if ($executor !== null) {
            // Allow garbage collection to free memory during long-running queues
            $this->executor = null;
            try {
                $this->resolve($executor());
            } catch (\Throwable $e) {
                $this->reject($e); // @phpstan-ignore missingType.checkedException
            }
        } elseif ($this->waitingState !== null) {
            $this->processWaitingTask(); // @phpstan-ignore missingType.checkedException
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

    /** @throws InvariantViolation */
    private function enqueueWaitingPromises(): void
    {
        if ($this->state === self::PENDING) {
            throw new InvariantViolation('Cannot enqueue derived promises when parent is still pending');
        }

        $state = $this->state;
        $result = $this->result;
        $queue = SyncPromiseQueue::getInstance();

        foreach ($this->waiting as $promise) {
            $promise->waitingState = $state;
            $promise->waitingResult = $result;
            $queue->enqueue($promise);
        }

        $this->waiting = [];
    }

    /**
     * Process this promise as a waiting task (child promise awaiting parent resolution).
     *
     * @throws \Exception
     */
    private function processWaitingTask(): void
    {
        // Unpack and clear references to allow garbage collection
        $onFulfilled = $this->waitingOnFulfilled;
        $onRejected = $this->waitingOnRejected;
        $state = $this->waitingState;
        $result = $this->waitingResult;

        $this->waitingOnFulfilled = null;
        $this->waitingOnRejected = null;
        $this->waitingState = null;
        $this->waitingResult = null;

        try {
            if ($state === self::FULFILLED) {
                $this->resolve($onFulfilled !== null
                    ? $onFulfilled($result)
                    : $result);
            } elseif ($state === self::REJECTED) {
                if ($onRejected === null) {
                    $this->reject($result);
                } else {
                    $this->resolve($onRejected($result));
                }
            }
        } catch (\Throwable $e) {
            $this->reject($e);
        }
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

        $tmp = new self();
        $tmp->waitingOnFulfilled = $onFulfilled;
        $tmp->waitingOnRejected = $onRejected;
        $this->waiting[] = $tmp;

        if ($this->state !== self::PENDING) {
            $this->enqueueWaitingPromises();
        }

        return $tmp;
    }

    /**
     * @param callable(\Throwable): mixed $onRejected
     *
     * @throws InvariantViolation
     */
    public function catch(callable $onRejected): self
    {
        return $this->then(null, $onRejected);
    }
}
