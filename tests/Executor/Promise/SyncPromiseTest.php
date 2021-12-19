<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor\Promise;

use Exception;
use GraphQL\Executor\Promise\Adapter\SyncPromise;
use PHPUnit\Framework\TestCase;
use Throwable;

use function uniqid;

class SyncPromiseTest extends TestCase
{
    /**
     * @return iterable<array{
     *   string,
     *   ?callable,
     *   ?string,
     *   ?string,
     *   string,
     * }>
     */
    public function fulfilledPromiseResolveData(): iterable
    {
        $onFulfilledReturnsNull = static fn () => null;

        $onFulfilledReturnsSameValue = static fn ($value) => $value;

        $onFulfilledReturnsOtherValue = static fn ($value): string => 'other-' . $value;

        $onFulfilledThrows = static function ($value): void {
            throw new Exception('onFulfilled throws this!');
        };

        return [
            ['test-value', null, 'test-value', null, SyncPromise::FULFILLED],
            [uniqid(), $onFulfilledReturnsNull, null, null, SyncPromise::FULFILLED],
            ['test-value', $onFulfilledReturnsSameValue, 'test-value', null, SyncPromise::FULFILLED],
            ['test-value-2', $onFulfilledReturnsOtherValue, 'other-test-value-2', null, SyncPromise::FULFILLED],
            ['test-value-3', $onFulfilledThrows, null, 'onFulfilled throws this!', SyncPromise::REJECTED],
        ];
    }

    /**
     * @dataProvider fulfilledPromiseResolveData
     */
    public function testFulfilledPromiseCannotChangeValue(
        string $resolvedValue,
        ?callable $onFulfilled,
        ?string $expectedNextValue,
        ?string $expectedNextReason,
        ?string $expectedNextState
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
     * @dataProvider fulfilledPromiseResolveData
     */
    public function testFulfilledPromiseCannotBeRejected(
        string $resolvedValue,
        ?callable $onFulfilled,
        ?string $expectedNextValue,
        ?string $expectedNextReason,
        ?string $expectedNextState
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
     * @param mixed $expectedNextValue
     *
     * @dataProvider fulfilledPromiseResolveData
     */
    public function testFulfilledPromise(
        string $resolvedValue,
        ?callable $onFulfilled,
        $expectedNextValue,
        ?string $expectedNextReason,
        ?string $expectedNextState
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

        if ($onFulfilled !== null) {
            self::assertNotSame($promise, $nextPromise);
            self::assertEquals(SyncPromise::PENDING, $nextPromise->state);
        } else {
            self::assertEquals(SyncPromise::FULFILLED, $nextPromise->state);
        }

        self::assertEquals(false, $onRejectedCalled);

        self::assertValidPromise($nextPromise, $expectedNextValue, $expectedNextReason, $expectedNextState);

        $nextPromise2 = $promise->then($onFulfilled);
        $nextPromise3 = $promise->then($onFulfilled);

        if ($onFulfilled !== null) {
            self::assertNotSame($nextPromise, $nextPromise2);
        }

        SyncPromise::runQueue();

        self::assertValidPromise($nextPromise2, $expectedNextValue, $expectedNextReason, $expectedNextState);
        self::assertValidPromise($nextPromise3, $expectedNextValue, $expectedNextReason, $expectedNextState);
    }

    /**
     * @param mixed $expectedNextValue
     */
    private static function assertValidPromise(
        SyncPromise $promise,
        $expectedNextValue,
        ?string $expectedNextReason,
        ?string $expectedNextState
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

        self::assertFalse($onFulfilledCalled);
        self::assertFalse($onRejectedCalled);

        SyncPromise::runQueue();

        if ($expectedNextReason === null) {
            self::assertTrue($onFulfilledCalled);
            self::assertFalse($onRejectedCalled);
        } else {
            self::assertFalse($onFulfilledCalled);
            self::assertTrue($onRejectedCalled);
        }

        self::assertEquals($expectedNextValue, $actualNextValue);
        self::assertEquals($expectedNextReason, $actualNextReason);
        self::assertEquals($expectedNextState, $promise->state);
    }

    /**
     * @return iterable<array{Exception, ?callable, ?string, ?string, string}>
     */
    public function rejectedPromiseData(): iterable
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
     * @dataProvider rejectedPromiseData
     */
    public function testRejectedPromiseCannotChangeReason(
        Throwable $rejectedReason,
        ?callable $onRejected,
        ?string $expectedNextValue,
        ?string $expectedNextReason,
        string $expectedNextState
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
     * @dataProvider rejectedPromiseData
     */
    public function testRejectedPromiseCannotBeResolved(
        Throwable $rejectedReason,
        ?callable $onRejected,
        ?string $expectedNextValue,
        ?string $expectedNextReason,
        string $expectedNextState
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
     * @dataProvider rejectedPromiseData
     */
    public function testRejectedPromise(
        Throwable $rejectedReason,
        ?callable $onRejected,
        ?string $expectedNextValue,
        ?string $expectedNextReason,
        string $expectedNextState
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

        if ($onRejected !== null) {
            self::assertNotSame($promise, $nextPromise);
            self::assertEquals(SyncPromise::PENDING, $nextPromise->state);
        } else {
            self::assertEquals(SyncPromise::REJECTED, $nextPromise->state);
        }

        self::assertFalse($onFulfilledCalled);
        self::assertValidPromise($nextPromise, $expectedNextValue, $expectedNextReason, $expectedNextState);

        $nextPromise2 = $promise->then(null, $onRejected);
        $nextPromise3 = $promise->then(null, $onRejected);

        if ($onRejected !== null) {
            self::assertNotSame($nextPromise, $nextPromise2);
        }

        SyncPromise::runQueue();

        self::assertValidPromise($nextPromise2, $expectedNextValue, $expectedNextReason, $expectedNextState);
        self::assertValidPromise($nextPromise3, $expectedNextValue, $expectedNextReason, $expectedNextState);
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
        self::assertValidPromise($promise, 'the value', null, SyncPromise::FULFILLED);

        $promise = new SyncPromise();
        $promise->resolve('resolved!');

        self::assertValidPromise($promise, 'resolved!', null, SyncPromise::FULFILLED);

        // Test rejections
        $promise = new SyncPromise();
        self::assertEquals(SyncPromise::PENDING, $promise->state);

        try {
            // @phpstan-ignore-next-line purposefully wrong
            $promise->reject('a');
            self::fail('Expected exception not thrown');
        } catch (Throwable $e) {
            self::assertEquals(SyncPromise::PENDING, $promise->state);
        }

        $promise->reject(new Exception('Rejected Reason'));
        self::assertValidPromise($promise, null, 'Rejected Reason', SyncPromise::REJECTED);

        $promise  = new SyncPromise();
        $promise2 = $promise->then(
            null,
            static function (): string {
                return 'value';
            }
        );
        $promise->reject(new Exception('Rejected Again'));
        self::assertValidPromise($promise2, 'value', null, SyncPromise::FULFILLED);

        $promise  = new SyncPromise();
        $promise2 = $promise->then();
        $promise->reject(new Exception('Rejected Once Again'));
        self::assertValidPromise($promise2, null, 'Rejected Once Again', SyncPromise::REJECTED);
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
        self::assertValidPromise($nextPromise, 1, null, SyncPromise::FULFILLED);
        self::assertValidPromise($nextPromise2, 1, null, SyncPromise::FULFILLED);
        self::assertValidPromise($nextPromise3, 2, null, SyncPromise::FULFILLED);
        self::assertValidPromise($nextPromise4, 3, null, SyncPromise::FULFILLED);
    }

    public function testRunEmptyQueue(): void
    {
        $this->expectNotToPerformAssertions();

        SyncPromise::runQueue();
    }
}
