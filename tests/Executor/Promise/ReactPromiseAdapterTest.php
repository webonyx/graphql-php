<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor\Promise;

use Exception;
use GraphQL\Executor\Promise\Adapter\ReactPromiseAdapter;
use PHPUnit\Framework\TestCase;
use React\Promise\Deferred;
use React\Promise\FulfilledPromise;
use React\Promise\LazyPromise;
use React\Promise\Promise as ReactPromise;
use React\Promise\RejectedPromise;
use stdClass;
use function class_exists;

/**
 * @group ReactPromise
 */
class ReactPromiseAdapterTest extends TestCase
{
    public function setUp() : void
    {
        if (class_exists('React\Promise\Promise')) {
            return;
        }

        self::markTestSkipped('react/promise package must be installed to run GraphQL\Tests\Executor\Promise\ReactPromiseAdapterTest');
    }

    public function testIsThenableReturnsTrueWhenAReactPromiseIsGiven() : void
    {
        $reactAdapter = new ReactPromiseAdapter();

        self::assertTrue(
            $reactAdapter->isThenable(new ReactPromise(static function () : void {
            }))
        );
        self::assertTrue($reactAdapter->isThenable(new FulfilledPromise()));
        self::assertTrue($reactAdapter->isThenable(new RejectedPromise()));
        self::assertTrue(
            $reactAdapter->isThenable(new LazyPromise(static function () : void {
            }))
        );
        self::assertFalse($reactAdapter->isThenable(false));
        self::assertFalse($reactAdapter->isThenable(true));
        self::assertFalse($reactAdapter->isThenable(1));
        self::assertFalse($reactAdapter->isThenable(0));
        self::assertFalse($reactAdapter->isThenable('test'));
        self::assertFalse($reactAdapter->isThenable(''));
        self::assertFalse($reactAdapter->isThenable([]));
        self::assertFalse($reactAdapter->isThenable(new stdClass()));
    }

    public function testConvertsReactPromisesToGraphQlOnes() : void
    {
        $reactAdapter = new ReactPromiseAdapter();
        $reactPromise = new FulfilledPromise(1);

        $promise = $reactAdapter->convertThenable($reactPromise);

        self::assertInstanceOf('GraphQL\Executor\Promise\Promise', $promise);
        self::assertInstanceOf('React\Promise\FulfilledPromise', $promise->adoptedPromise);
    }

    public function testThen() : void
    {
        $reactAdapter = new ReactPromiseAdapter();
        $reactPromise = new FulfilledPromise(1);
        $promise      = $reactAdapter->convertThenable($reactPromise);

        $result = null;

        $resultPromise = $reactAdapter->then(
            $promise,
            static function ($value) use (&$result) : void {
                $result = $value;
            }
        );

        self::assertSame(1, $result);
        self::assertInstanceOf('GraphQL\Executor\Promise\Promise', $resultPromise);
        self::assertInstanceOf('React\Promise\FulfilledPromise', $resultPromise->adoptedPromise);
    }

    public function testCreate() : void
    {
        $reactAdapter    = new ReactPromiseAdapter();
        $resolvedPromise = $reactAdapter->create(static function ($resolve) : void {
            $resolve(1);
        });

        self::assertInstanceOf('GraphQL\Executor\Promise\Promise', $resolvedPromise);
        self::assertInstanceOf('React\Promise\Promise', $resolvedPromise->adoptedPromise);

        $result = null;

        $resolvedPromise->then(static function ($value) use (&$result) : void {
            $result = $value;
        });

        self::assertSame(1, $result);
    }

    public function testCreateFulfilled() : void
    {
        $reactAdapter     = new ReactPromiseAdapter();
        $fulfilledPromise = $reactAdapter->createFulfilled(1);

        self::assertInstanceOf('GraphQL\Executor\Promise\Promise', $fulfilledPromise);
        self::assertInstanceOf('React\Promise\FulfilledPromise', $fulfilledPromise->adoptedPromise);

        $result = null;

        $fulfilledPromise->then(static function ($value) use (&$result) : void {
            $result = $value;
        });

        self::assertSame(1, $result);
    }

    public function testCreateRejected() : void
    {
        $reactAdapter    = new ReactPromiseAdapter();
        $rejectedPromise = $reactAdapter->createRejected(new Exception('I am a bad promise'));

        self::assertInstanceOf('GraphQL\Executor\Promise\Promise', $rejectedPromise);
        self::assertInstanceOf('React\Promise\RejectedPromise', $rejectedPromise->adoptedPromise);

        $exception = null;

        $rejectedPromise->then(
            null,
            static function ($error) use (&$exception) : void {
                $exception = $error;
            }
        );

        self::assertInstanceOf('\Exception', $exception);
        self::assertEquals('I am a bad promise', $exception->getMessage());
    }

    public function testAll() : void
    {
        $reactAdapter = new ReactPromiseAdapter();
        $promises     = [new FulfilledPromise(1), new FulfilledPromise(2), new FulfilledPromise(3)];

        $allPromise = $reactAdapter->all($promises);

        self::assertInstanceOf('GraphQL\Executor\Promise\Promise', $allPromise);
        self::assertInstanceOf('React\Promise\FulfilledPromise', $allPromise->adoptedPromise);

        $result = null;

        $allPromise->then(static function ($values) use (&$result) : void {
            $result = $values;
        });

        self::assertSame([1, 2, 3], $result);
    }

    public function testAllShouldPreserveTheOrderOfTheArrayWhenResolvingAsyncPromises() : void
    {
        $reactAdapter = new ReactPromiseAdapter();
        $deferred     = new Deferred();
        $promises     = [new FulfilledPromise(1), $deferred->promise(), new FulfilledPromise(3)];
        $result       = null;

        $reactAdapter->all($promises)->then(static function ($values) use (&$result) : void {
            $result = $values;
        });

        // Resolve the async promise
        $deferred->resolve(2);
        self::assertSame([1, 2, 3], $result);
    }
}
