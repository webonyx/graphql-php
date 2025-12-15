<?php declare(strict_types=1);

namespace GraphQL\Executor\Promise\Adapter;

/**
 * Root promise created with an executor callback.
 *
 * Used by Deferred to create promises that will be resolved when the executor runs.
 */
class RootSyncPromise extends SyncPromise
{
    /**
     * Executor for deferred execution.
     *
     * @var (callable(): mixed)|null
     */
    protected $executor;

    /** @param callable(): mixed $executor */
    public function __construct(callable $executor)
    {
        $this->executor = $executor;

        $queue = SyncPromiseQueue::getInstance();
        $queue->enqueue($this);
    }

    /** @throws \Exception */
    public function runQueuedTask(): void
    {
        $executor = $this->executor;
        $this->executor = null;

        assert($executor !== null);

        try {
            $this->resolve($executor());
        } catch (\Throwable $e) {
            $this->reject($e);
        }
    }
}
