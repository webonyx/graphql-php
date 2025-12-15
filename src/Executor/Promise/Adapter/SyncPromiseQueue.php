<?php declare(strict_types=1);

namespace GraphQL\Executor\Promise\Adapter;

/** Queue for deferred execution of SyncPromise tasks. */
class SyncPromiseQueue
{
    protected static ?self $instance = null;

    /** @var \SplQueue<SyncPromise> */
    protected \SplQueue $queue;

    public function __construct()
    {
        $this->queue = new \SplQueue();
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function enqueue(SyncPromise $promise): void
    {
        $this->queue->enqueue($promise);
    }

    public function isEmpty(): bool
    {
        return $this->queue->isEmpty();
    }

    public function count(): int
    {
        return $this->queue->count();
    }

    /** Process all queued promises until the queue is empty. */
    public function run(): void
    {
        while (! $this->queue->isEmpty()) {
            $task = $this->queue->dequeue();
            $task->runQueuedTask();
            unset($task);
        }
    }
}
