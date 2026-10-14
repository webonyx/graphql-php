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

    public function testThenUnwrapsFutureReturnedByFulfillmentCallback(): void
    {
        $ampAdapter = new AmpFutureAdapter();
        $deferred = new DeferredFuture();
        $promise = $ampAdapter->convertThenable(Future::complete(1));

        $resultPromise = $ampAdapter->then($promise, static function ($value) use ($deferred): Future {
            self::assertSame(1, $value);

            return $deferred->getFuture();
        });

        $deferred->complete(2);

        self::assertSame(2, $resultPromise->adoptedPromise->await());
    }

    public function testThenDoesNotInvokeRejectionCallbackForFulfillmentCallbackException(): void
    {
        $ampAdapter = new AmpFutureAdapter();
        $promise = $ampAdapter->convertThenable(Future::complete());

        $resultPromise = $ampAdapter->then(
            $promise,
            static function ($value): void {
                self::assertNull($value);

                throw new \RuntimeException('fulfillment failed');
            },
            static function (\Throwable $reason): void {
                self::fail('The rejection callback must not run for a fulfillment callback exception.');
            }
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('fulfillment failed');

        $resultPromise->adoptedPromise->await();
    }

    public function testThenRejectsWhenNoRejectionCallbackIsProvided(): void
    {
        $ampAdapter = new AmpFutureAdapter();
        $promise = $ampAdapter->convertThenable(Future::error(new \RuntimeException('failed')));
        $resultPromise = $ampAdapter->then($promise);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('failed');

        $resultPromise->adoptedPromise->await();
    }

    public function testThenRejectsWhenRejectionCallbackThrows(): void
    {
        $ampAdapter = new AmpFutureAdapter();
        $promise = $ampAdapter->convertThenable(Future::error(new \RuntimeException('failed')));
        $resultPromise = $ampAdapter->then(
            $promise,
            null,
            static function (\Throwable $reason): void {
                self::assertSame('failed', $reason->getMessage());

                throw new \RuntimeException('rejection failed');
            }
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('rejection failed');

        $resultPromise->adoptedPromise->await();
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

    public function testCreateUnwrapsPromise(): void
    {
        $ampAdapter = new AmpFutureAdapter();
        $nestedPromise = $ampAdapter->createFulfilled(1);
        $promise = $ampAdapter->create(static function ($resolve) use ($nestedPromise): void {
            $resolve($nestedPromise);
        });

        self::assertSame(1, $promise->adoptedPromise->await());
    }

    public function testCreateRejectsWhenNestedFutureFails(): void
    {
        $ampAdapter = new AmpFutureAdapter();
        $future = Future::error(new \RuntimeException('failed'));
        $promise = $ampAdapter->create(static function ($resolve) use ($future): void {
            $resolve($future);
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('failed');

        $promise->adoptedPromise->await();
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

    public function testThenUnwrapsFutureReturnedByRejectionCallback(): void
    {
        $ampAdapter = new AmpFutureAdapter();
        $deferred = new DeferredFuture();
        $promise = $ampAdapter->convertThenable(Future::error(new \RuntimeException('failed')));

        $resultPromise = $ampAdapter->then(
            $promise,
            null,
            static function (\Throwable $reason) use ($deferred): Future {
                self::assertSame('failed', $reason->getMessage());

                return $deferred->getFuture();
            }
        );

        $deferred->complete('recovered');

        self::assertSame('recovered', $resultPromise->adoptedPromise->await());
    }

    public function testAll(): void
    {
        $ampAdapter = new AmpFutureAdapter();
        $promises = [Future::complete(1), Future::complete(2), Future::complete(3)];

        $allPromise = $ampAdapter->all($promises);

        $result = $allPromise->adoptedPromise->await();

        self::assertSame([1, 2, 3], $result);
    }

    public function testAllResolvesEmptyIterable(): void
    {
        $ampAdapter = new AmpFutureAdapter();

        self::assertSame([], $ampAdapter->all([])->adoptedPromise->await());
    }

    public function testAllShouldPreserveKeysWhenResolvingAsyncPromises(): void
    {
        $ampAdapter = new AmpFutureAdapter();
        $deferred = new DeferredFuture();
        $promises = [
            'first' => Future::complete(1),
            'second' => 2,
            'third' => $deferred->getFuture(),
            'fourth' => Future::complete(4),
        ];

        $allPromise = $ampAdapter->all($promises);

        $deferred->complete(3);

        $result = $allPromise->adoptedPromise->await();

        self::assertSame([
            'first' => 1,
            'second' => 2,
            'third' => 3,
            'fourth' => 4,
        ], $result);
    }

    public function testAllAcceptsPromisesInIterables(): void
    {
        $ampAdapter = new AmpFutureAdapter();
        $promise = $ampAdapter->createFulfilled(1);
        $promises = (static function () use ($promise): \Generator {
            yield 'promise' => $promise;
            yield 'future' => Future::complete(2);
        })();

        $allPromise = $ampAdapter->all($promises);

        self::assertSame(['promise' => 1, 'future' => 2], $allPromise->adoptedPromise->await());
    }

    public function testAllRejectsWhenOneFutureFails(): void
    {
        $ampAdapter = new AmpFutureAdapter();
        $deferred = new DeferredFuture();
        $allPromise = $ampAdapter->all([
            'resolved' => Future::complete(1),
            'rejected' => $deferred->getFuture(),
        ]);

        $deferred->error(new \RuntimeException('failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('failed');

        $allPromise->adoptedPromise->await();
    }

    public function testAllRemainsRejectedWhenAnotherFutureResolves(): void
    {
        $ampAdapter = new AmpFutureAdapter();
        $rejected = new DeferredFuture();
        $resolved = new DeferredFuture();
        $allPromise = $ampAdapter->all([$rejected->getFuture(), $resolved->getFuture()]);

        $rejected->error(new \RuntimeException('failed'));
        $resolved->complete('resolved');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('failed');

        $allPromise->adoptedPromise->await();
    }

    public function testAllIgnoresOtherFuturesAfterRejection(): void
    {
        $ampAdapter = new AmpFutureAdapter();
        $rejected = new DeferredFuture();
        $resolved = new DeferredFuture();
        $alsoRejected = new DeferredFuture();
        $allPromise = $ampAdapter->all([
            $rejected->getFuture(),
            $resolved->getFuture(),
            $alsoRejected->getFuture(),
        ]);

        $rejected->error(new \RuntimeException('failed'));

        try {
            $allPromise->adoptedPromise->await();
            self::fail('Expected aggregate rejection.');
        } catch (\RuntimeException $exception) {
            self::assertSame('failed', $exception->getMessage());
        }

        $resolved->complete('resolved');
        $alsoRejected->error(new \RuntimeException('also failed'));

        async(static function (): void {})->await();
    }
}
