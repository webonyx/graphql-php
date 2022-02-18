<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\Promise;

use Exception;
use GraphQL\Deferred;
use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\Promise\Adapter\SyncPromise;
use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Executor\Promise\Promise;
use PHPUnit\Framework\TestCase;
use stdClass;
use Throwable;

class SyncPromiseAdapterTest extends TestCase
{
    private SyncPromiseAdapter $promises;

    public function setUp(): void
    {
        $this->promises = new SyncPromiseAdapter();
    }

    public function testIsThenable(): void
    {
        self::assertEquals(
            true,
            $this->promises->isThenable(new Deferred(static function (): void {
            }))
        );
        self::assertEquals(false, $this->promises->isThenable(false));
        self::assertEquals(false, $this->promises->isThenable(true));
        self::assertEquals(false, $this->promises->isThenable(1));
        self::assertEquals(false, $this->promises->isThenable(0));
        self::assertEquals(false, $this->promises->isThenable('test'));
        self::assertEquals(false, $this->promises->isThenable(''));
        self::assertEquals(false, $this->promises->isThenable([]));
        self::assertEquals(false, $this->promises->isThenable(new stdClass()));
    }

    public function testConvert(): void
    {
        $dfd = new Deferred(static function (): void {
        });
        $result = $this->promises->convertThenable($dfd);

        self::assertInstanceOf(SyncPromise::class, $result->adoptedPromise);

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage('Expected instance of GraphQL\Deferred, got (empty string)');
        $this->promises->convertThenable('');
    }

    public function testThen(): void
    {
        $dfd = new Deferred(static function (): void {
        });
        $promise = $this->promises->convertThenable($dfd);

        $result = $this->promises->then($promise);

        self::assertInstanceOf(SyncPromise::class, $result->adoptedPromise);
    }

    public function testCreatePromise(): void
    {
        $promise = $this->promises->create(static function ($resolve, $reject): void {
        });

        self::assertInstanceOf(SyncPromise::class, $promise->adoptedPromise);

        $promise = $this->promises->create(static function ($resolve, $reject): void {
            $resolve('A');
        });

        self::assertValidPromise($promise, null, 'A', SyncPromise::FULFILLED);
    }

    /**
     * @param mixed $expectedNextValue
     */
    private static function assertValidPromise(Promise $promise, ?string $expectedNextReason, $expectedNextValue, string $expectedNextState): void
    {
        self::assertInstanceOf(SyncPromise::class, $promise->adoptedPromise);

        $actualNextValue = null;
        $actualNextReason = null;
        $onFulfilledCalled = false;
        $onRejectedCalled = false;

        $promise->then(
            static function ($nextValue) use (&$actualNextValue, &$onFulfilledCalled): void {
                $onFulfilledCalled = true;
                $actualNextValue = $nextValue;
            },
            static function (Throwable $reason) use (&$actualNextReason, &$onRejectedCalled): void {
                $onRejectedCalled = true;
                $actualNextReason = $reason->getMessage();
            }
        );

        self::assertSame($onFulfilledCalled, false);
        self::assertSame($onRejectedCalled, false);

        SyncPromise::runQueue();

        if ($expectedNextState !== SyncPromise::PENDING) {
            if ($expectedNextReason === null) {
                self::assertTrue($onFulfilledCalled);
                self::assertFalse($onRejectedCalled);
            } else {
                self::assertFalse($onFulfilledCalled);
                self::assertTrue($onRejectedCalled);
            }
        }

        self::assertSame($expectedNextValue, $actualNextValue);
        self::assertSame($expectedNextReason, $actualNextReason);
        self::assertSame($expectedNextState, $promise->adoptedPromise->state);
    }

    public function testCreateFulfilledPromise(): void
    {
        $promise = $this->promises->createFulfilled('test');
        self::assertValidPromise($promise, null, 'test', SyncPromise::FULFILLED);
    }

    public function testCreateRejectedPromise(): void
    {
        $promise = $this->promises->createRejected(new Exception('test reason'));
        self::assertValidPromise($promise, 'test reason', null, SyncPromise::REJECTED);
    }

    public function testCreatePromiseAll(): void
    {
        $promise = $this->promises->all([]);
        self::assertValidPromise($promise, null, [], SyncPromise::FULFILLED);

        $promise = $this->promises->all(['1']);
        self::assertValidPromise($promise, null, ['1'], SyncPromise::FULFILLED);

        $promise1 = new SyncPromise();
        $promise2 = new SyncPromise();
        $promise3 = $promise2->then(
            static function ($value): string {
                return $value . '-value3';
            }
        );

        $data = [
            '1',
            new Promise($promise1, $this->promises),
            new Promise($promise2, $this->promises),
            3,
            new Promise($promise3, $this->promises),
            [],
        ];

        $promise = $this->promises->all($data);
        self::assertValidPromise($promise, null, null, SyncPromise::PENDING);

        $promise1->resolve('value1');
        self::assertValidPromise($promise, null, null, SyncPromise::PENDING);
        $promise2->resolve('value2');
        self::assertValidPromise(
            $promise,
            null,
            ['1', 'value1', 'value2', 3, 'value2-value3', []],
            SyncPromise::FULFILLED
        );
    }

    public function testWait(): void
    {
        $called = [];

        $deferred1 = new Deferred(static function () use (&$called): int {
            $called[] = 1;

            return 1;
        });
        $deferred2 = new Deferred(static function () use (&$called): int {
            $called[] = 2;

            return 2;
        });

        $p1 = $this->promises->convertThenable($deferred1);
        $p2 = $this->promises->convertThenable($deferred2);

        $p3 = $p2->then(function () use (&$called): Promise {
            $dfd = new Deferred(static function () use (&$called): int {
                $called[] = 3;

                return 3;
            });

            return $this->promises->convertThenable($dfd);
        });

        $p4 = $p3->then(static function () use (&$called): Deferred {
            return new Deferred(static function () use (&$called): int {
                $called[] = 4;

                return 4;
            });
        });

        $all = $this->promises->all([0, $p1, $p2, $p3, $p4]);

        $result = $this->promises->wait($p2);

        // Having single promise queue means that we won't stop in wait
        // until all pending promises are resolved
        self::assertEquals(2, $result);

        $p3AdoptedPromise = $p3->adoptedPromise;
        self::assertInstanceOf(SyncPromise::class, $p3AdoptedPromise);
        self::assertEquals(SyncPromise::FULFILLED, $p3AdoptedPromise->state);

        $allAdoptedPromise = $all->adoptedPromise;
        self::assertInstanceOf(SyncPromise::class, $allAdoptedPromise);
        self::assertEquals(SyncPromise::FULFILLED, $allAdoptedPromise->state);

        self::assertEquals([1, 2, 3, 4], $called);

        $expectedResult = [0, 1, 2, 3, 4];
        $result = $this->promises->wait($all);
        self::assertEquals($expectedResult, $result);
        self::assertEquals([1, 2, 3, 4], $called);
        self::assertValidPromise($all, null, [0, 1, 2, 3, 4], SyncPromise::FULFILLED);
    }
}
