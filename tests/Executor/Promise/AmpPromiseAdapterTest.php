<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\Promise;

use Amp\Deferred;
use Amp\Delayed;
use Amp\Failure;
use Amp\LazyPromise;
use Amp\Promise;
use Amp\Success;
use GraphQL\Executor\Promise\Adapter\AmpPromiseAdapter;
use PHPUnit\Framework\TestCase;

use function Amp\call;

/**
 * @group AmpPromise
 */
final class AmpPromiseAdapterTest extends TestCase
{
    public function testIsThenableReturnsTrueWhenAnAmpPromiseIsGiven(): void
    {
        $ampAdapter = new AmpPromiseAdapter();

        self::assertTrue(
            $ampAdapter->isThenable(call(static function (): \Generator {
                yield from [];
            }))
        );
        self::assertTrue($ampAdapter->isThenable(new Success()));
        self::assertTrue($ampAdapter->isThenable(new Failure(new \Exception())));
        self::assertTrue($ampAdapter->isThenable(new Delayed(0)));
        self::assertTrue(
            $ampAdapter->isThenable(new LazyPromise(static function (): void {}))
        );
        self::assertFalse($ampAdapter->isThenable(false));
        self::assertFalse($ampAdapter->isThenable(true));
        self::assertFalse($ampAdapter->isThenable(1));
        self::assertFalse($ampAdapter->isThenable(0));
        self::assertFalse($ampAdapter->isThenable('test'));
        self::assertFalse($ampAdapter->isThenable(''));
        self::assertFalse($ampAdapter->isThenable([]));
        self::assertFalse($ampAdapter->isThenable(new \stdClass()));
    }

    public function testConvertsReactPromisesToGraphQLOnes(): void
    {
        $ampAdapter = new AmpPromiseAdapter();
        $ampPromise = new Success(1);

        $promise = $ampAdapter->convertThenable($ampPromise);

        self::assertInstanceOf(Success::class, $promise->adoptedPromise);
    }

    public function testThen(): void
    {
        $ampAdapter = new AmpPromiseAdapter();
        $ampPromise = new Success(1);
        $promise = $ampAdapter->convertThenable($ampPromise);

        $result = null;

        $resultPromise = $ampAdapter->then(
            $promise,
            static function ($value) use (&$result): void {
                $result = $value;
            }
        );

        self::assertSame(1, $result);
        self::assertInstanceOf(Promise::class, $resultPromise->adoptedPromise);
    }

    public function testCreate(): void
    {
        $ampAdapter = new AmpPromiseAdapter();
        $resolvedPromise = $ampAdapter->create(static function ($resolve): void {
            $resolve(1);
        });

        self::assertInstanceOf(Promise::class, $resolvedPromise->adoptedPromise);

        $result = null;

        $resolvedPromise->then(static function ($value) use (&$result): void {
            $result = $value;
        });

        self::assertSame(1, $result);
    }

    public function testCreateFulfilled(): void
    {
        $ampAdapter = new AmpPromiseAdapter();
        $fulfilledPromise = $ampAdapter->createFulfilled(1);

        self::assertInstanceOf(Success::class, $fulfilledPromise->adoptedPromise);

        $result = null;

        $fulfilledPromise->then(static function ($value) use (&$result): void {
            $result = $value;
        });

        self::assertSame(1, $result);
    }

    public function testCreateRejected(): void
    {
        $ampAdapter = new AmpPromiseAdapter();
        $rejectedPromise = $ampAdapter->createRejected(new \Exception('I am a bad promise'));

        self::assertInstanceOf(Failure::class, $rejectedPromise->adoptedPromise);

        $exception = null;

        $rejectedPromise->then(
            null,
            static function ($error) use (&$exception): void {
                $exception = $error;
            }
        );

        self::assertInstanceOf(\Throwable::class, $exception);
        self::assertSame('I am a bad promise', $exception->getMessage());
    }

    public function testAll(): void
    {
        $ampAdapter = new AmpPromiseAdapter();
        $promises = [new Success(1), new Success(2), new Success(3)];

        $allPromise = $ampAdapter->all($promises);

        self::assertInstanceOf(Promise::class, $allPromise->adoptedPromise);

        $result = null;

        $allPromise->then(static function ($values) use (&$result): void {
            $result = $values;
        });

        self::assertSame([1, 2, 3], $result);
    }

    public function testAllShouldPreserveTheOrderOfTheArrayWhenResolvingAsyncPromises(): void
    {
        $ampAdapter = new AmpPromiseAdapter();
        $deferred = new Deferred();
        $promises = [new Success(1), 2, $deferred->promise(), new Success(4)];
        $result = null;

        $ampAdapter->all($promises)->then(static function ($values) use (&$result): void {
            $result = $values;
        });

        // Resolve the async promise
        $deferred->resolve(3);
        self::assertSame([1, 2, 3, 4], $result);
    }
}
