<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor\Promise;

use Amp\Deferred;
use Amp\Delayed;
use Amp\Failure;
use Amp\LazyPromise;
use Amp\Promise;
use Amp\Success;
use Exception;
use GraphQL\Executor\Promise\Adapter\AmpPromiseAdapter;
use PHPUnit\Framework\TestCase;
use stdClass;
use function Amp\call;
use function interface_exists;

/**
 * @group AmpPromise
 */
class AmpPromiseAdapterTest extends TestCase
{
    public function setUp()
    {
        if (interface_exists(Promise::class)) {
            return;
        }

        self::markTestSkipped('amphp/amp package must be installed to run GraphQL\Tests\Executor\Promise\AmpPromiseAdapterTest');
    }

    public function testIsThenableReturnsTrueWhenAnAmpPromiseIsGiven() : void
    {
        $ampAdapter = new AmpPromiseAdapter();

        self::assertTrue(
            $ampAdapter->isThenable(call(static function () {
                yield from [];
            }))
        );
        self::assertTrue($ampAdapter->isThenable(new Success()));
        self::assertTrue($ampAdapter->isThenable(new Failure(new Exception())));
        self::assertTrue($ampAdapter->isThenable(new Delayed(0)));
        self::assertTrue(
            $ampAdapter->isThenable(new LazyPromise(static function () {
            }))
        );
        self::assertFalse($ampAdapter->isThenable(false));
        self::assertFalse($ampAdapter->isThenable(true));
        self::assertFalse($ampAdapter->isThenable(1));
        self::assertFalse($ampAdapter->isThenable(0));
        self::assertFalse($ampAdapter->isThenable('test'));
        self::assertFalse($ampAdapter->isThenable(''));
        self::assertFalse($ampAdapter->isThenable([]));
        self::assertFalse($ampAdapter->isThenable(new stdClass()));
    }

    public function testConvertsReactPromisesToGraphQlOnes() : void
    {
        $ampAdapter = new AmpPromiseAdapter();
        $ampPromise = new Success(1);

        $promise = $ampAdapter->convertThenable($ampPromise);

        self::assertInstanceOf('GraphQL\Executor\Promise\Promise', $promise);
        self::assertInstanceOf(Success::class, $promise->adoptedPromise);
    }

    public function testThen() : void
    {
        $ampAdapter = new AmpPromiseAdapter();
        $ampPromise = new Success(1);
        $promise    = $ampAdapter->convertThenable($ampPromise);

        $result = null;

        $resultPromise = $ampAdapter->then(
            $promise,
            static function ($value) use (&$result) {
                $result = $value;
            }
        );

        self::assertSame(1, $result);
        self::assertInstanceOf('GraphQL\Executor\Promise\Promise', $resultPromise);
        self::assertInstanceOf(Promise::class, $resultPromise->adoptedPromise);
    }

    public function testCreate() : void
    {
        $ampAdapter      = new AmpPromiseAdapter();
        $resolvedPromise = $ampAdapter->create(static function ($resolve) {
            $resolve(1);
        });

        self::assertInstanceOf('GraphQL\Executor\Promise\Promise', $resolvedPromise);
        self::assertInstanceOf(Promise::class, $resolvedPromise->adoptedPromise);

        $result = null;

        $resolvedPromise->then(static function ($value) use (&$result) {
            $result = $value;
        });

        self::assertSame(1, $result);
    }

    public function testCreateFulfilled() : void
    {
        $ampAdapter       = new AmpPromiseAdapter();
        $fulfilledPromise = $ampAdapter->createFulfilled(1);

        self::assertInstanceOf('GraphQL\Executor\Promise\Promise', $fulfilledPromise);
        self::assertInstanceOf(Success::class, $fulfilledPromise->adoptedPromise);

        $result = null;

        $fulfilledPromise->then(static function ($value) use (&$result) {
            $result = $value;
        });

        self::assertSame(1, $result);
    }

    public function testCreateRejected() : void
    {
        $ampAdapter      = new AmpPromiseAdapter();
        $rejectedPromise = $ampAdapter->createRejected(new Exception('I am a bad promise'));

        self::assertInstanceOf('GraphQL\Executor\Promise\Promise', $rejectedPromise);
        self::assertInstanceOf(Failure::class, $rejectedPromise->adoptedPromise);

        $exception = null;

        $rejectedPromise->then(
            null,
            static function ($error) use (&$exception) {
                $exception = $error;
            }
        );

        self::assertInstanceOf('\Exception', $exception);
        self::assertEquals('I am a bad promise', $exception->getMessage());
    }

    public function testAll() : void
    {
        $ampAdapter = new AmpPromiseAdapter();
        $promises   = [new Success(1), new Success(2), new Success(3)];

        $allPromise = $ampAdapter->all($promises);

        self::assertInstanceOf('GraphQL\Executor\Promise\Promise', $allPromise);
        self::assertInstanceOf(Promise::class, $allPromise->adoptedPromise);

        $result = null;

        $allPromise->then(static function ($values) use (&$result) {
            $result = $values;
        });

        self::assertSame([1, 2, 3], $result);
    }

    public function testAllShouldPreserveTheOrderOfTheArrayWhenResolvingAsyncPromises() : void
    {
        $ampAdapter = new AmpPromiseAdapter();
        $deferred   = new Deferred();
        $promises   = [new Success(1), 2, $deferred->promise(), new Success(4)];
        $result     = null;

        $ampAdapter->all($promises)->then(static function ($values) use (&$result) {
            $result = $values;
        });

        // Resolve the async promise
        $deferred->resolve(3);
        self::assertSame([1, 2, 3, 4], $result);
    }
}
