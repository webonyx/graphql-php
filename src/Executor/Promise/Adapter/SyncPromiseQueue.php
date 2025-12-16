<?php declare(strict_types=1);

namespace GraphQL\Executor\Promise\Adapter;

/**
 * Queue for deferred execution of SyncPromise tasks.
 *
 * Owns the shared queue and provides the run loop for processing promises.
 */
class SyncPromiseQueue
{
    /** @param callable(): void $task */
    public static function enqueue(callable $task): void
    {
        self::queue()->enqueue($task);
    }

    /** Process all queued promises until the queue is empty. */
    public static function run(): void
    {
        $queue = self::queue();
        while (! $queue->isEmpty()) {
            $task = $queue->dequeue();
            $task();
        }
    }

    public static function isEmpty(): bool
    {
        return self::queue()->isEmpty();
    }

    public static function count(): int
    {
        return self::queue()->count();
    }

    /** @return \SplQueue<callable(): void> */
    private static function queue(): \SplQueue
    {
        /** @var \SplQueue<callable(): void>|null $queue */
        static $queue;

        return $queue ??= new \SplQueue();
    }
}
