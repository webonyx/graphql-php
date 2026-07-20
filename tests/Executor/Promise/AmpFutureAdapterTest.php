<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\Promise;

use Amp\DeferredFuture;
use Amp\Future;
use GraphQL\Executor\Promise\Adapter\AmpFutureAdapter;
use PHPUnit\Framework\TestCase;

use function Amp\async;

/**
 * @group AmpFuture
 */
final class AmpFutureAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        if (! class_exists(Future::class)) {
            self::markTestSkipped('amphp/amp ^3 is required for this test suite.');
        }
    }

    public function testIsThenableReturnsTrueWhenAnAmpFutureIsGiven(): void
    {
        $ampAdapter = new AmpFutureAdapter();

        self::assertTrue($ampAdapter->isThenable(Future::complete()));
        $errorFuture = Future::error(new \Exception());
        self::assertTrue($ampAdapter->isThenable($errorFuture));
        $errorFuture->ignore();
        $asyncFuture = async(static function (): void {});
        self::assertTrue($ampAdapter->isThenable($asyncFuture));
        $asyncFuture->await();
        self::assertFalse($ampAdapter->isThenable(false));
        self::assertFalse($ampAdapter->isThenable(true));
        self::assertFalse($ampAdapter->isThenable(1));
        self::assertFalse($ampAdapter->isThenable(0));
        self::assertFalse($ampAdapter->isThenable('test'));
        self::assertFalse($ampAdapter->isThenable(''));
        self::assertFalse($ampAdapter->isThenable([]));
        self::assertFalse($ampAdapter->isThenable(new \stdClass()));
    }

    public function testConvertsAmpFuturesToGraphQLOnes(): void
    {
        $ampAdapter = new AmpFutureAdapter();
        $future = Future::complete(1);

        $promise = $ampAdapter->convertThenable($future);

        self::assertSame($future, $promise->adoptedPromise);
    }

    public function testThen(): void
    {
        $ampAdapter = new AmpFutureAdapter();
        $future = Future::complete(1);
        $promise = $ampAdapter->convertThenable($future);

        $result = null;

        $resultPromise = $ampAdapter->then(
            $promise,
            static function ($value) use (&$result): void {
                $result = $value;
            }
        );

        $resultPromise->adoptedPromise->await();

        self::assertSame(1, $result);
    }

    public function testCreate(): void
    {
        $ampAdapter = new AmpFutureAdapter();
        $resolvedPromise = $ampAdapter->create(static function ($resolve): void {
            $resolve(1);
        });

        $result = null;

        $resultPromise = $resolvedPromise->then(static function ($value) use (&$result): void {
            $result = $value;
        });

        $resultPromise->adoptedPromise->await();

        self::assertSame(1, $result);
    }

    public function testCreateFulfilled(): void
    {
        $ampAdapter = new AmpFutureAdapter();
        $fulfilledPromise = $ampAdapter->createFulfilled(1);

        $result = null;

        $resultPromise = $fulfilledPromise->then(static function ($value) use (&$result): void {
            $result = $value;
        });

        $resultPromise->adoptedPromise->await();

        self::assertSame(1, $result);
    }

    public function testCreateRejected(): void
    {
        $ampAdapter = new AmpFutureAdapter();
        $rejectedPromise = $ampAdapter->createRejected(new \Exception('I am a bad promise'));

        $exception = null;

        $resultPromise = $rejectedPromise->then(
            null,
            static function ($error) use (&$exception): void {
                $exception = $error;
            }
        );

        $resultPromise->adoptedPromise->await();

        self::assertInstanceOf(\Throwable::class, $exception);
        self::assertSame('I am a bad promise', $exception->getMessage());
    }

    public function testAll(): void
    {
        $ampAdapter = new AmpFutureAdapter();
        $promises = [Future::complete(1), Future::complete(2), Future::complete(3)];

        $allPromise = $ampAdapter->all($promises);

        $result = $allPromise->adoptedPromise->await();

        self::assertSame([1, 2, 3], $result);
    }

    public function testAllShouldPreserveTheOrderOfTheArrayWhenResolvingAsyncPromises(): void
    {
        $ampAdapter = new AmpFutureAdapter();
        $deferred = new DeferredFuture();
        $promises = [Future::complete(1), 2, $deferred->getFuture(), Future::complete(4)];

        $allPromise = $ampAdapter->all($promises);

        $deferred->complete(3);

        $result = $allPromise->adoptedPromise->await();

        self::assertSame([1, 2, 3, 4], $result);
    }
}
