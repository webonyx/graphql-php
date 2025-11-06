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
            $task();
            // Explicitly clear the task reference to help garbage collection
            unset($task);
        }
    }

    /** @param Executor|null $executor */
    public function __construct(?callable $executor = null)
    {
        if ($executor === null) {
            return;
        }

        $queue = self::getQueue();
        $queue->enqueue(function () use (&$executor): void {
            try {
                assert(is_callable($executor));
                $this->resolve($executor());
            } catch (\Throwable $e) {
                $this->reject($e);
            } finally {
                // Clear the executor reference to allow garbage collection
                // of the closure and its captured context
                $executor = null;
            }
        });

        // Trigger incremental processing if queue grows too large
        if ($queue->count() >= self::QUEUE_BATCH_SIZE) {
            self::processBatch();
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
        $queue = self::getQueue();

        foreach ($this->waiting as $descriptor) {
            $queue->enqueue(static function () use ($descriptor, $state, $result): void {
                [$promise, $onFulfilled, $onRejected] = $descriptor;

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
                    $promise->reject($e);
                }
            });

            // Trigger incremental processing if queue grows too large
            if ($queue->count() >= self::QUEUE_BATCH_SIZE) {
                self::processBatch();
            }
        }

        $this->waiting = [];
    }

    /**
     * Maximum queue size before triggering incremental processing.
     * Helps reduce peak memory usage with large numbers of Deferred objects.
     */
    private const QUEUE_BATCH_SIZE = 500;

    /** @return \SplQueue<callable(): void> */
    public static function getQueue(): \SplQueue
    {
        static $queue;

        return $queue ??= new \SplQueue();
    }

    /**
     * Process a batch of queued tasks to reduce memory usage.
     * Called automatically when queue exceeds threshold.
     */
    private static function processBatch(): void
    {
        $q = self::getQueue();
        $batchSize = min(self::QUEUE_BATCH_SIZE, $q->count());

        for ($i = 0; $i < $batchSize; ++$i) {
            if ($q->isEmpty()) {
                break;
            }

            $task = $q->dequeue();
            $task();
            unset($task);
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
