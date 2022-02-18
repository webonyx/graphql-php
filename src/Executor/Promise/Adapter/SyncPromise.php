<?php declare(strict_types=1);

namespace GraphQL\Executor\Promise\Adapter;

use Exception;
use GraphQL\Error\InvariantViolation;
use function is_object;
use function method_exists;
use SplQueue;
use Throwable;

/**
 * Simplistic (yet full-featured) implementation of Promises A+ spec for regular PHP `sync` mode
 * (using queue to defer promises execution).
 *
 * Note:
 * Library users are not supposed to use SyncPromise class in their resolvers.
 * Instead they should use GraphQL\Deferred which enforces $executor callback in the constructor.
 *
 * Root SyncPromise without explicit $executor will never resolve (actually throw while trying).
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
     *         (callable(Throwable): mixed)|null
     *     }
     * >
     */
    private array $waiting = [];

    public static function runQueue(): void
    {
        $q = self::getQueue();
        while (! $q->isEmpty()) {
            $task = $q->dequeue();
            $task();
        }
    }

    /**
     * @param Executor|null $executor
     */
    public function __construct(?callable $executor = null)
    {
        if (null === $executor) {
            return;
        }

        self::getQueue()->enqueue(function () use ($executor): void {
            try {
                $this->resolve($executor());
            } catch (Throwable $e) {
                $this->reject($e);
            }
        });
    }

    /**
     * @param mixed $value
     */
    public function resolve($value): self
    {
        switch ($this->state) {
            case self::PENDING:
                if ($value === $this) {
                    throw new Exception('Cannot resolve promise with self');
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
                    throw new Exception('Cannot change value of fulfilled promise');
                }

                break;
            case self::REJECTED:
                throw new Exception('Cannot resolve rejected promise');
        }

        return $this;
    }

    public function reject(Throwable $reason): self
    {
        switch ($this->state) {
            case self::PENDING:
                $this->state = self::REJECTED;
                $this->result = $reason;
                $this->enqueueWaitingPromises();
                break;
            case self::REJECTED:
                if ($reason !== $this->result) {
                    throw new Exception('Cannot change rejection reason');
                }

                break;
            case self::FULFILLED:
                throw new Exception('Cannot reject fulfilled promise');
        }

        return $this;
    }

    private function enqueueWaitingPromises(): void
    {
        if (self::PENDING === $this->state) {
            throw new InvariantViolation('Cannot enqueue derived promises when parent is still pending');
        }

        foreach ($this->waiting as $descriptor) {
            self::getQueue()->enqueue(function () use ($descriptor): void {
                [$promise, $onFulfilled, $onRejected] = $descriptor;

                if (self::FULFILLED === $this->state) {
                    try {
                        $promise->resolve(null === $onFulfilled ? $this->result : $onFulfilled($this->result));
                    } catch (Throwable $e) {
                        $promise->reject($e);
                    }
                } elseif (self::REJECTED === $this->state) {
                    try {
                        if (null === $onRejected) {
                            $promise->reject($this->result);
                        } else {
                            $promise->resolve($onRejected($this->result));
                        }
                    } catch (Throwable $e) {
                        $promise->reject($e);
                    }
                }
            });
        }

        $this->waiting = [];
    }

    /**
     * @return SplQueue<callable(): void>
     */
    public static function getQueue(): SplQueue
    {
        static $queue;

        return $queue ??= new SplQueue();
    }

    /**
     * @param (callable(mixed): mixed)|null $onFulfilled
     * @param (callable(Throwable): mixed)|null $onRejected
     */
    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): self
    {
        if (self::REJECTED === $this->state && null === $onRejected) {
            return $this;
        }

        if (self::FULFILLED === $this->state && null === $onFulfilled) {
            return $this;
        }

        $tmp = new self();
        $this->waiting[] = [$tmp, $onFulfilled, $onRejected];

        if (self::PENDING !== $this->state) {
            $this->enqueueWaitingPromises();
        }

        return $tmp;
    }

    /**
     * @param callable(Throwable): mixed $onRejected
     */
    public function catch(callable $onRejected): self
    {
        return $this->then(null, $onRejected);
    }
}
