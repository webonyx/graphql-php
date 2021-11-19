<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor\Promise;

use Exception;
use GraphQL\Executor\Promise\Adapter\SyncPromise;
use GraphQL\Tests\TestCaseBase;
use PHPUnit\Framework\Error\Error;
use Throwable;

use function uniqid;

class SyncPromiseTestTestCaseBase extends TestCaseBase
{
    public function getFulfilledPromiseResolveData()
    {
        $onFulfilledReturnsNull = static function () {
            return null;
        };

        $onFulfilledReturnsSameValue = static function ($value) {
            return $value;
        };

        $onFulfilledReturnsOtherValue = static function ($value): string {
            return 'other-' . $value;
        };

        $onFulfilledThrows = static function ($value): void {
            throw new Exception('onFulfilled throws this!');
        };

        return [
            // $resolvedValue, $onFulfilled, $expectedNextValue, $expectedNextReason, $expectedNextState
            ['test-value', null, 'test-value', null, SyncPromise::FULFILLED],
            [uniqid(), $onFulfilledReturnsNull, null, null, SyncPromise::FULFILLED],
            ['test-value', $onFulfilledReturnsSameValue, 'test-value', null, SyncPromise::FULFILLED],
            ['test-value-2', $onFulfilledReturnsOtherValue, 'other-test-value-2', null, SyncPromise::FULFILLED],
            ['test-value-3', $onFulfilledThrows, null, 'onFulfilled throws this!', SyncPromise::REJECTED],
        ];
    }

    /**
     * @dataProvider getFulfilledPromiseResolveData
     */
    public function testFulfilledPromiseCannotChangeValue(
        $resolvedValue,
        $onFulfilled,
        $expectedNextValue,
        $expectedNextReason,
        $expectedNextState
    ): void {
        $promise = new SyncPromise();
        self::assertEquals(SyncPromise::PENDING, $promise->state);

        $promise->resolve($resolvedValue);
        self::assertEquals(SyncPromise::FULFILLED, $promise->state);

        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('Cannot change value of fulfilled promise');
        $promise->resolve($resolvedValue . '-other-value');
    }

    /**
     * @dataProvider getFulfilledPromiseResolveData
     */
    public function testFulfilledPromiseCannotBeRejected(
        $resolvedValue,
        $onFulfilled,
        $expectedNextValue,
        $expectedNextReason,
        $expectedNextState
    ): void {
        $promise = new SyncPromise();
        self::assertEquals(SyncPromise::PENDING, $promise->state);

        $promise->resolve($resolvedValue);
        self::assertEquals(SyncPromise::FULFILLED, $promise->state);

        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('Cannot reject fulfilled promise');
        $promise->reject(new Exception('anything'));
    }

    /**
     * @dataProvider getFulfilledPromiseResolveData
     */
    public function testFulfilledPromise(
        $resolvedValue,
        $onFulfilled,
        $expectedNextValue,
        $expectedNextReason,
        $expectedNextState
    ): void {
        $promise = new SyncPromise();
        self::assertEquals(SyncPromise::PENDING, $promise->state);

        $promise->resolve($resolvedValue);
        self::assertEquals(SyncPromise::FULFILLED, $promise->state);

        $nextPromise = $promise->then(
            null,
            static function (): void {
            }
        );
        self::assertSame($promise, $nextPromise);

        $onRejectedCalled = false;
        $nextPromise      = $promise->then(
            $onFulfilled,
            static function () use (&$onRejectedCalled): void {
                $onRejectedCalled = true;
            }
        );

        if ($onFulfilled) {
            self::assertNotSame($promise, $nextPromise);
            self::assertEquals(SyncPromise::PENDING, $nextPromise->state);
        } else {
            self::assertEquals(SyncPromise::FULFILLED, $nextPromise->state);
        }

        self::assertEquals(false, $onRejectedCalled);

        self::assertValidPromise($nextPromise, $expectedNextReason, $expectedNextValue, $expectedNextState);

        $nextPromise2 = $promise->then($onFulfilled);
        $nextPromise3 = $promise->then($onFulfilled);

        if ($onFulfilled) {
            self::assertNotSame($nextPromise, $nextPromise2);
        }

        SyncPromise::runQueue();

        self::assertValidPromise($nextPromise2, $expectedNextReason, $expectedNextValue, $expectedNextState);
        self::assertValidPromise($nextPromise3, $expectedNextReason, $expectedNextValue, $expectedNextState);
    }

    private static function assertValidPromise(
        SyncPromise $promise,
        $expectedNextReason,
        $expectedNextValue,
        $expectedNextState
    ): void {
        $actualNextValue   = null;
        $actualNextReason  = null;
        $onFulfilledCalled = false;
        $onRejectedCalled  = false;

        $promise->then(
            static function ($nextValue) use (&$actualNextValue, &$onFulfilledCalled): void {
                $onFulfilledCalled = true;
                $actualNextValue   = $nextValue;
            },
            static function (Throwable $reason) use (&$actualNextReason, &$onRejectedCalled): void {
                $onRejectedCalled = true;
                $actualNextReason = $reason->getMessage();
            }
        );

        self::assertEquals($onFulfilledCalled, false);
        self::assertEquals($onRejectedCalled, false);

        SyncPromise::runQueue();

        self::assertEquals(! $expectedNextReason, $onFulfilledCalled);
        self::assertEquals(! ! $expectedNextReason, $onRejectedCalled);

        self::assertEquals($expectedNextValue, $actualNextValue);
        self::assertEquals($expectedNextReason, $actualNextReason);
        self::assertEquals($expectedNextState, $promise->state);
    }

    public function getRejectedPromiseData()
    {
        $onRejectedReturnsNull = static function () {
            return null;
        };

        $onRejectedReturnsSomeValue = static function ($reason): string {
            return 'some-value';
        };

        $onRejectedThrowsSameReason = static function ($reason): void {
            throw $reason;
        };

        $onRejectedThrowsOtherReason = static function ($value): void {
            throw new Exception('onRejected throws other!');
        };

        return [
            // $rejectedReason, $onRejected, $expectedNextValue, $expectedNextReason, $expectedNextState
            [new Exception('test-reason'), null, null, 'test-reason', SyncPromise::REJECTED],
            [new Exception('test-reason-2'), $onRejectedReturnsNull, null, null, SyncPromise::FULFILLED],
            [new Exception('test-reason-3'), $onRejectedReturnsSomeValue, 'some-value', null, SyncPromise::FULFILLED],
            [new Exception('test-reason-4'), $onRejectedThrowsSameReason, null, 'test-reason-4', SyncPromise::REJECTED],
            [new Exception('test-reason-5'), $onRejectedThrowsOtherReason, null, 'onRejected throws other!', SyncPromise::REJECTED],
        ];
    }

    /**
     * @dataProvider getRejectedPromiseData
     */
    public function testRejectedPromiseCannotChangeReason(
        $rejectedReason,
        $onRejected,
        $expectedNextValue,
        $expectedNextReason,
        $expectedNextState
    ): void {
        $promise = new SyncPromise();
        self::assertEquals(SyncPromise::PENDING, $promise->state);

        $promise->reject($rejectedReason);
        self::assertEquals(SyncPromise::REJECTED, $promise->state);

        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('Cannot change rejection reason');
        $promise->reject(new Exception('other-reason'));
    }

    /**
     * @dataProvider getRejectedPromiseData
     */
    public function testRejectedPromiseCannotBeResolved(
        $rejectedReason,
        $onRejected,
        $expectedNextValue,
        $expectedNextReason,
        $expectedNextState
    ): void {
        $promise = new SyncPromise();
        self::assertEquals(SyncPromise::PENDING, $promise->state);

        $promise->reject($rejectedReason);
        self::assertEquals(SyncPromise::REJECTED, $promise->state);

        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('Cannot resolve rejected promise');
        $promise->resolve('anything');
    }

    /**
     * @dataProvider getRejectedPromiseData
     */
    public function testRejectedPromise(
        $rejectedReason,
        $onRejected,
        $expectedNextValue,
        $expectedNextReason,
        $expectedNextState
    ): void {
        $promise = new SyncPromise();
        self::assertEquals(SyncPromise::PENDING, $promise->state);

        $promise->reject($rejectedReason);
        self::assertEquals(SyncPromise::REJECTED, $promise->state);

        try {
            $promise->reject(new Exception('other-reason'));
            self::fail('Expected exception not thrown');
        } catch (Throwable $e) {
            self::assertEquals('Cannot change rejection reason', $e->getMessage());
        }

        try {
            $promise->resolve('anything');
            self::fail('Expected exception not thrown');
        } catch (Throwable $e) {
            self::assertEquals('Cannot resolve rejected promise', $e->getMessage());
        }

        $nextPromise = $promise->then(
            static function (): void {
            },
            null
        );
        self::assertSame($promise, $nextPromise);

        $onFulfilledCalled = false;
        $nextPromise       = $promise->then(
            static function () use (&$onFulfilledCalled): void {
                $onFulfilledCalled = true;
            },
            $onRejected
        );

        if ($onRejected) {
            self::assertNotSame($promise, $nextPromise);
            self::assertEquals(SyncPromise::PENDING, $nextPromise->state);
        } else {
            self::assertEquals(SyncPromise::REJECTED, $nextPromise->state);
        }

        self::assertEquals(false, $onFulfilledCalled);
        self::assertValidPromise($nextPromise, $expectedNextReason, $expectedNextValue, $expectedNextState);

        $nextPromise2 = $promise->then(null, $onRejected);
        $nextPromise3 = $promise->then(null, $onRejected);

        if ($onRejected) {
            self::assertNotSame($nextPromise, $nextPromise2);
        }

        SyncPromise::runQueue();

        self::assertValidPromise($nextPromise2, $expectedNextReason, $expectedNextValue, $expectedNextState);
        self::assertValidPromise($nextPromise3, $expectedNextReason, $expectedNextValue, $expectedNextState);
    }

    public function testPendingPromise(): void
    {
        $promise = new SyncPromise();
        self::assertEquals(SyncPromise::PENDING, $promise->state);

        try {
            $promise->resolve($promise);
            self::fail('Expected exception not thrown');
        } catch (Throwable $e) {
            self::assertEquals('Cannot resolve promise with self', $e->getMessage());
            self::assertEquals(SyncPromise::PENDING, $promise->state);
        }

        // Try to resolve with other promise (must resolve when other promise resolves)
        $otherPromise = new SyncPromise();
        $promise->resolve($otherPromise);

        self::assertEquals(SyncPromise::PENDING, $promise->state);
        self::assertEquals(SyncPromise::PENDING, $otherPromise->state);

        $otherPromise->resolve('the value');
        self::assertEquals(SyncPromise::FULFILLED, $otherPromise->state);
        self::assertEquals(SyncPromise::PENDING, $promise->state);
        self::assertValidPromise($promise, null, 'the value', SyncPromise::FULFILLED);

        $promise = new SyncPromise();
        $promise->resolve('resolved!');

        self::assertValidPromise($promise, null, 'resolved!', SyncPromise::FULFILLED);

        // Test rejections
        $promise = new SyncPromise();
        self::assertEquals(SyncPromise::PENDING, $promise->state);

        try {
            $promise->reject('a');
            self::fail('Expected exception not thrown');
        } catch (Error $e) {
            throw $e;
        } catch (Throwable $e) {
            self::assertEquals(SyncPromise::PENDING, $promise->state);
        }

        $promise->reject(new Exception('Rejected Reason'));
        self::assertValidPromise($promise, 'Rejected Reason', null, SyncPromise::REJECTED);

        $promise  = new SyncPromise();
        $promise2 = $promise->then(
            null,
            static function (): string {
                return 'value';
            }
        );
        $promise->reject(new Exception('Rejected Again'));
        self::assertValidPromise($promise2, null, 'value', SyncPromise::FULFILLED);

        $promise  = new SyncPromise();
        $promise2 = $promise->then();
        $promise->reject(new Exception('Rejected Once Again'));
        self::assertValidPromise($promise2, 'Rejected Once Again', null, SyncPromise::REJECTED);
    }

    public function testPendingPromiseThen(): void
    {
        $promise = new SyncPromise();
        self::assertEquals(SyncPromise::PENDING, $promise->state);

        $nextPromise = $promise->then();
        self::assertNotSame($promise, $nextPromise);
        self::assertEquals(SyncPromise::PENDING, $promise->state);
        self::assertEquals(SyncPromise::PENDING, $nextPromise->state);

        // Make sure that it queues derivative promises until resolution:
        $onFulfilledCount = 0;
        $onRejectedCount  = 0;
        $onFulfilled      = static function ($value) use (&$onFulfilledCount): int {
            $onFulfilledCount++;

            return $onFulfilledCount;
        };

        $onRejected = static function ($reason) use (&$onRejectedCount): void {
            $onRejectedCount++;

            throw $reason;
        };

        $nextPromise2 = $promise->then($onFulfilled, $onRejected);
        $nextPromise3 = $promise->then($onFulfilled, $onRejected);
        $nextPromise4 = $promise->then($onFulfilled, $onRejected);

        self::assertEquals(SyncPromise::getQueue()->count(), 0);
        self::assertEquals($onFulfilledCount, 0);
        self::assertEquals($onRejectedCount, 0);
        $promise->resolve(1);

        self::assertEquals(SyncPromise::getQueue()->count(), 4);
        self::assertEquals($onFulfilledCount, 0);
        self::assertEquals($onRejectedCount, 0);
        self::assertEquals(SyncPromise::PENDING, $nextPromise->state);
        self::assertEquals(SyncPromise::PENDING, $nextPromise2->state);
        self::assertEquals(SyncPromise::PENDING, $nextPromise3->state);
        self::assertEquals(SyncPromise::PENDING, $nextPromise4->state);

        SyncPromise::runQueue();
        self::assertEquals(SyncPromise::getQueue()->count(), 0);
        self::assertEquals($onFulfilledCount, 3);
        self::assertEquals($onRejectedCount, 0);
        self::assertValidPromise($nextPromise, null, 1, SyncPromise::FULFILLED);
        self::assertValidPromise($nextPromise2, null, 1, SyncPromise::FULFILLED);
        self::assertValidPromise($nextPromise3, null, 2, SyncPromise::FULFILLED);
        self::assertValidPromise($nextPromise4, null, 3, SyncPromise::FULFILLED);
    }

    public function testRunEmptyQueue(): void
    {
        SyncPromise::runQueue();

        self::assertDidNotCrash();
    }
}
