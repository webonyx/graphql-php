<?php declare(strict_types=1);

namespace GraphQL\Executor\Promise\Adapter;

/**
 * Child promise created via then() chaining.
 *
 * Waits for a parent promise to resolve before processing its callbacks.
 */
class ChildSyncPromise extends SyncPromise
{
    /**
     * Callback when parent fulfills.
     *
     * @var (callable(mixed): mixed)|null
     */
    public $onFulfilled;

    /**
     * Callback when parent rejects.
     *
     * @var (callable(\Throwable): mixed)|null
     */
    public $onRejected;

    /** Parent promise state when this child was enqueued. */
    public ?string $parentState = null;

    /**
     * Parent promise result when this child was enqueued.
     *
     * @var mixed
     */
    public $parentResult;

    /** @throws \Exception */
    public function runQueuedTask(): void
    {
        try {
            if ($this->parentState === self::FULFILLED) {
                $this->resolve($this->onFulfilled !== null
                    ? ($this->onFulfilled)($this->parentResult)
                    : $this->parentResult);
            } elseif ($this->parentState === self::REJECTED) {
                if ($this->onRejected === null) {
                    $this->reject($this->parentResult);
                } else {
                    $this->resolve(($this->onRejected)($this->parentResult));
                }
            }
        } catch (\Throwable $e) {
            $this->reject($e);
        }
    }
}
