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
        $onFulfilled = $this->onFulfilled;
        $onRejected = $this->onRejected;
        $state = $this->parentState;
        $result = $this->parentResult;

        $this->onFulfilled = null;
        $this->onRejected = null;
        $this->parentState = null;
        $this->parentResult = null;

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
}
