<?php


namespace GraphQL\Tests\Executor\Promise;


use GraphQL\Executor\Promise\Adapter\ReactPromiseAdapter;
use GraphQL\Executor\Promise\Promise;
use React\Promise\Deferred;
use React\Promise\FulfilledPromise;
use React\Promise\LazyPromise;
use React\Promise\Promise as ReactPromise;
use React\Promise\RejectedPromise;

/**
 * @group ReactPromise
 */
class ReactPromiseAdapterTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        if(! class_exists('React\Promise\Promise')) {
            $this->markTestSkipped('react/promise package must be installed to run GraphQL\Tests\Executor\Promise\ReactPromiseAdapterTest');
        }
    }

    public function testIsThenableReturnsTrueWhenAReactPromiseIsGiven()
    {
        $reactAdapter = new ReactPromiseAdapter();

        $this->assertSame(true, $reactAdapter->isThenable(new ReactPromise(function() {})));
        $this->assertSame(true, $reactAdapter->isThenable(new FulfilledPromise()));
        $this->assertSame(true, $reactAdapter->isThenable(new RejectedPromise()));
        $this->assertSame(true, $reactAdapter->isThenable(new LazyPromise(function() {})));
        $this->assertSame(false, $reactAdapter->isThenable(false));
        $this->assertSame(false, $reactAdapter->isThenable(true));
        $this->assertSame(false, $reactAdapter->isThenable(1));
        $this->assertSame(false, $reactAdapter->isThenable(0));
        $this->assertSame(false, $reactAdapter->isThenable('test'));
        $this->assertSame(false, $reactAdapter->isThenable(''));
        $this->assertSame(false, $reactAdapter->isThenable([]));
        $this->assertSame(false, $reactAdapter->isThenable(new \stdClass()));
    }

    public function testConvertsReactPromisesToGraphQlOnes()
    {
        $reactAdapter = new ReactPromiseAdapter();
        $reactPromise = new FulfilledPromise(1);

        $promise = $reactAdapter->convertThenable($reactPromise);

        $this->assertInstanceOf('GraphQL\Executor\Promise\Promise', $promise);
        $this->assertInstanceOf('React\Promise\FulfilledPromise', $promise->adoptedPromise);
    }

    public function testThen()
    {
        $reactAdapter = new ReactPromiseAdapter();
        $reactPromise = new FulfilledPromise(1);
        $promise = $reactAdapter->convertThenable($reactPromise);

        $result = null;

        $resultPromise = $reactAdapter->then($promise, function ($value) use (&$result) {
            $result = $value;
        });

        $this->assertSame(1, $result);
        $this->assertInstanceOf('GraphQL\Executor\Promise\Promise', $resultPromise);
        $this->assertInstanceOf('React\Promise\FulfilledPromise', $resultPromise->adoptedPromise);
    }

    public function testCreate()
    {
        $reactAdapter = new ReactPromiseAdapter();
        $resolvedPromise = $reactAdapter->create(function ($resolve) {
             $resolve(1);
        });

        $this->assertInstanceOf('GraphQL\Executor\Promise\Promise', $resolvedPromise);
        $this->assertInstanceOf('React\Promise\Promise', $resolvedPromise->adoptedPromise);

        $result = null;

        $resolvedPromise->then(function ($value) use (&$result) {
           $result = $value;
        });

        $this->assertSame(1, $result);
    }

    public function testCreateFulfilled()
    {
        $reactAdapter = new ReactPromiseAdapter();
        $fulfilledPromise = $reactAdapter->createFulfilled(1);

        $this->assertInstanceOf('GraphQL\Executor\Promise\Promise', $fulfilledPromise);
        $this->assertInstanceOf('React\Promise\FulfilledPromise', $fulfilledPromise->adoptedPromise);

        $result = null;

        $fulfilledPromise->then(function ($value) use (&$result) {
            $result = $value;
        });

        $this->assertSame(1, $result);
    }

    public function testCreateRejected()
    {
        $reactAdapter = new ReactPromiseAdapter();
        $rejectedPromise = $reactAdapter->createRejected(new \Exception('I am a bad promise'));

        $this->assertInstanceOf('GraphQL\Executor\Promise\Promise', $rejectedPromise);
        $this->assertInstanceOf('React\Promise\RejectedPromise', $rejectedPromise->adoptedPromise);

        $exception = null;

        $rejectedPromise->then(null, function ($error) use (&$exception) {
            $exception = $error;
        });

        $this->assertInstanceOf('\Exception', $exception);
        $this->assertEquals('I am a bad promise', $exception->getMessage());
    }

    public function testAll()
    {
        $reactAdapter = new ReactPromiseAdapter();
        $promises = [new FulfilledPromise(1), new FulfilledPromise(2), new FulfilledPromise(3)];

        $allPromise = $reactAdapter->all($promises);

        $this->assertInstanceOf('GraphQL\Executor\Promise\Promise', $allPromise);
        $this->assertInstanceOf('React\Promise\FulfilledPromise', $allPromise->adoptedPromise);

        $result = null;

        $allPromise->then(function ($values) use (&$result) {
           $result = $values;
        });

        $this->assertSame([1, 2, 3], $result);
    }

    public function testAllShouldPreserveTheOrderOfTheArrayWhenResolvingAsyncPromises()
    {
        $reactAdapter = new ReactPromiseAdapter();
        $deferred = new Deferred();
        $promises = [new FulfilledPromise(1), $deferred->promise(), new FulfilledPromise(3)];
        $result = null;

        $reactAdapter->all($promises)->then(function ($values) use (&$result) {
            $result = $values;
        });

        // Resolve the async promise
        $deferred->resolve(2);
        $this->assertSame([1, 2, 3], $result);
    }
}
