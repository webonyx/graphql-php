<?php
namespace GraphQL;

use GraphQL\Executor\Promise\Adapter\SyncPromise;

class Deferred
{
    /**
     * @var \SplQueue
     */
    private static $queue;

    /**
     * @var callable
     */
    private $callback;

    /**
     * @var SyncPromise
     */
    public $promise;

    public static function getQueue()
    {
        return self::$queue ?: self::$queue = new \SplQueue();
    }

    public static function runQueue()
    {
        $q = self::$queue;
        while ($q && !$q->isEmpty()) {
            /** @var self $dfd */
            $dfd = $q->dequeue();
            $dfd->run();
        }
    }

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
        $this->promise = new SyncPromise();
        self::getQueue()->enqueue($this);
    }

    public function then($onFulfilled = null, $onRejected = null)
    {
        return $this->promise->then($onFulfilled, $onRejected);
    }

    private function run()
    {
        try {
            $cb = $this->callback;
            $this->promise->resolve($cb());
        } catch (\Exception $e) {
            $this->promise->reject($e);
        } catch (\Throwable $e) {
            $this->promise->reject($e);
        }
    }
}
