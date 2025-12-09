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
     * @var array<
     *     int,
     *     array{
     *         self,
     *         (callable(mixed): mixed)|null,
     *         (callable(\Throwable): mixed)|null
     *     }
     * >
     */
    protected array $waiting = [];

    public static function runQueue(): void
    {
        $q = self::getQueue();
        while (! $q->isEmpty()) {
            $task = $q->dequeue();

            if ($task instanceof self) {
                // Execute promise's stored executor
                $executor = $task->executor;
                $task->executor = null; // Clear reference before execution for GC
                assert(is_callable($executor));
                try {
                    $task->resolve($executor());
                } catch (\Throwable $e) {
                    $task->reject($e); // @phpstan-ignore missingType.checkedException (invariant violation - won't happen in normal operation)
                }
            } elseif (is_array($task)) {
                // Handle waiting promise callbacks (from enqueueWaitingPromises)
                /** @var array{self, (callable(mixed): mixed)|null, (callable(\Throwable): mixed)|null, string, mixed} $task */
                self::processWaitingTask($task);
            } else {
                // Backward compatibility: execute as callable
                $task();
            }

            unset($task);
        }
    }

    /** @param Executor|null $executor */
    public function __construct(?callable $executor = null)
    {
        if ($executor === null) {
            return;
        }

        // Store executor on the promise instead of in a closure to reduce memory usage
        $this->executor = $executor;
        // Queue the promise reference (smaller than a closure)
        self::getQueue()->enqueue($this);
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

        // Capture state and result as values (not $this reference) to reduce memory footprint
        $state = $this->state;
        $result = $this->result;
        $queue = self::getQueue();

        foreach ($this->waiting as $descriptor) {
            [$promise, $onFulfilled, $onRejected] = $descriptor;
            // Queue an array instead of a closure - smaller memory footprint
            $queue->enqueue([$promise, $onFulfilled, $onRejected, $state, $result]);
        }

        $this->waiting = [];
    }

    /**
     * Process a waiting promise task from the queue.
     *
     * @param array{self, (callable(mixed): mixed)|null, (callable(\Throwable): mixed)|null, string, mixed} $task
     */
    private static function processWaitingTask(array $task): void
    {
        [$promise, $onFulfilled, $onRejected, $state, $result] = $task;

        try {
            if ($state === self::FULFILLED) {
                $promise->resolve($onFulfilled === null ? $result : $onFulfilled($result));
            } elseif ($state === self::REJECTED) {
                if ($onRejected === null) {
                    $promise->reject($result);
                } else {
                    $promise->resolve($onRejected($result));
                }
            }
        } catch (\Throwable $e) {
            $promise->reject($e); // @phpstan-ignore missingType.checkedException (invariant violation - won't happen in normal operation)
        }
    }

    /** @return \SplQueue<self|array{self, (callable(mixed): mixed)|null, (callable(\Throwable): mixed)|null, string, mixed}|callable(): void> */
    public static function getQueue(): \SplQueue
    {
        static $queue;

        return $queue ??= new \SplQueue();
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
        $this->waiting[] = [$tmp, $onFulfilled, $onRejected];

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
