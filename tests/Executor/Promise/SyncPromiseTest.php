<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\Promise;

use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\Promise\Adapter\SyncPromise;
use GraphQL\Tests\TestCaseBase;

final class SyncPromiseTest extends TestCaseBase
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
    public static function fulfilledPromiseResolveData(): iterable
    {
        $onFulfilledReturnsNull = static fn () => null;

        $onFulfilledReturnsSameValue = static fn ($value) => $value;

        $onFulfilledReturnsOtherValue = static fn ($value): string => 'other-' . $value;

        $onFulfilledThrows = static function (): void {
            throw new \Exception('onFulfilled throws this!');
        };

        yield ['test-value', null, 'test-value', null, SyncPromise::FULFILLED];
        yield [uniqid(), $onFulfilledReturnsNull, null, null, SyncPromise::FULFILLED];
        yield ['test-value', $onFulfilledReturnsSameValue, 'test-value', null, SyncPromise::FULFILLED];
        yield ['test-value-2', $onFulfilledReturnsOtherValue, 'other-test-value-2', null, SyncPromise::FULFILLED];
        yield ['test-value-3', $onFulfilledThrows, null, 'onFulfilled throws this!', SyncPromise::REJECTED];
    }

    /** @dataProvider fulfilledPromiseResolveData */
    public function testFulfilledPromiseCannotChangeValue(
        string $resolvedValue,
        ?callable $onFulfilled,
        ?string $expectedNextValue,
        ?string $expectedNextReason,
        ?string $expectedNextState
    ): void {
        $promise = new SyncPromise();
        self::assertSame(SyncPromise::PENDING, $promise->state);

        $promise->resolve($resolvedValue);
        self::assertSame(SyncPromise::FULFILLED, $promise->state);

        $this->expectException(\Throwable::class);
        $this->expectExceptionMessage('Cannot change value of fulfilled promise');
        $promise->resolve($resolvedValue . '-other-value');
    }

    /** @dataProvider fulfilledPromiseResolveData */
    public function testFulfilledPromiseCannotBeRejected(
        string $resolvedValue,
        ?callable $onFulfilled,
        ?string $expectedNextValue,
        ?string $expectedNextReason,
        ?string $expectedNextState
    ): void {
        $promise = new SyncPromise();
        self::assertSame(SyncPromise::PENDING, $promise->state);

        $promise->resolve($resolvedValue);
        self::assertSame(SyncPromise::FULFILLED, $promise->state);

        $this->expectException(\Throwable::class);
        $this->expectExceptionMessage('Cannot reject fulfilled promise');
        $promise->reject(new \Exception('anything'));
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
        self::assertSame(SyncPromise::PENDING, $promise->state);

        $promise->resolve($resolvedValue);
        self::assertSame(SyncPromise::FULFILLED, $promise->state);

        $nextPromise = $promise->then(
            null,
            static function (): void {}
        );
        self::assertSame($promise, $nextPromise);

        $onRejectedCalled = false;
        $nextPromise = $promise->then(
            $onFulfilled,
            static function () use (&$onRejectedCalled): void {
                $onRejectedCalled = true;
            }
        );

        if ($onFulfilled !== null) {
            self::assertNotSame($promise, $nextPromise);
            self::assertSame(SyncPromise::PENDING, $nextPromise->state);
        } else {
            /** @phpstan-ignore argument.unresolvableType (false positive?)  */
            self::assertSame(SyncPromise::FULFILLED, $nextPromise->state);
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
     *
     * @throws InvariantViolation
     */
    private static function assertValidPromise(
        SyncPromise $promise,
        $expectedNextValue,
        ?string $expectedNextReason,
        ?string $expectedNextState
    ): void {
        $actualNextValue = null;
        $actualNextReason = null;
        $onFulfilledCalled = false;
        $onRejectedCalled = false;

        $promise->then(
            static function ($nextValue) use (&$actualNextValue, &$onFulfilledCalled): void {
                $onFulfilledCalled = true;
                $actualNextValue = $nextValue;
            },
            static function (\Throwable $reason) use (&$actualNextReason, &$onRejectedCalled): void {
                $onRejectedCalled = true;
                $actualNextReason = $reason->getMessage();
            }
        );

        self::assertFalse($onFulfilledCalled);
        self::assertFalse($onRejectedCalled);

        SyncPromise::runQueue();

        if ($expectedNextReason === null) {
            self::assertTrue($onFulfilledCalled); // @phpstan-ignore-line value is mutable
            self::assertFalse($onRejectedCalled); // @phpstan-ignore-line value is mutable
        } else {
            self::assertFalse($onFulfilledCalled); // @phpstan-ignore-line value is mutable
            self::assertTrue($onRejectedCalled); // @phpstan-ignore-line value is mutable
        }

        self::assertEquals($expectedNextValue, $actualNextValue);
        self::assertEquals($expectedNextReason, $actualNextReason);
        self::assertEquals($expectedNextState, $promise->state);
    }

    /** @return iterable<array{\Exception, ?callable, ?string, ?string, string}> */
    public static function rejectedPromiseData(): iterable
    {
        $onRejectedReturnsNull = static fn () => null;

        $onRejectedReturnsSomeValue = static fn (): string => 'some-value';

        $onRejectedThrowsSameReason = static function ($reason): void {
            throw $reason;
        };

        $onRejectedThrowsOtherReason = static function (): void {
            throw new \Exception('onRejected throws other!');
        };

        // $rejectedReason, $onRejected, $expectedNextValue, $expectedNextReason, $expectedNextState
        yield [new \Exception('test-reason'), null, null, 'test-reason', SyncPromise::REJECTED];
        yield [new \Exception('test-reason-2'), $onRejectedReturnsNull, null, null, SyncPromise::FULFILLED];
        yield [new \Exception('test-reason-3'), $onRejectedReturnsSomeValue, 'some-value', null, SyncPromise::FULFILLED];
        yield [new \Exception('test-reason-4'), $onRejectedThrowsSameReason, null, 'test-reason-4', SyncPromise::REJECTED];
        yield [new \Exception('test-reason-5'), $onRejectedThrowsOtherReason, null, 'onRejected throws other!', SyncPromise::REJECTED];
    }

    /** @dataProvider rejectedPromiseData */
    public function testRejectedPromiseCannotChangeReason(
        \Throwable $rejectedReason,
        ?callable $onRejected,
        ?string $expectedNextValue,
        ?string $expectedNextReason,
        string $expectedNextState
    ): void {
        $promise = new SyncPromise();
        self::assertSame(SyncPromise::PENDING, $promise->state);

        $promise->reject($rejectedReason);
        self::assertSame(SyncPromise::REJECTED, $promise->state);

        $this->expectException(\Throwable::class);
        $this->expectExceptionMessage('Cannot change rejection reason');
        $promise->reject(new \Exception('other-reason'));
    }

    /** @dataProvider rejectedPromiseData */
    public function testRejectedPromiseCannotBeResolved(
        \Throwable $rejectedReason,
        ?callable $onRejected,
        ?string $expectedNextValue,
        ?string $expectedNextReason,
        string $expectedNextState
    ): void {
        $promise = new SyncPromise();
        self::assertSame(SyncPromise::PENDING, $promise->state);

        $promise->reject($rejectedReason);
        self::assertSame(SyncPromise::REJECTED, $promise->state);

        $this->expectException(\Throwable::class);
        $this->expectExceptionMessage('Cannot resolve rejected promise');
        $promise->resolve('anything');
    }

    /** @dataProvider rejectedPromiseData */
    public function testRejectedPromise(
        \Throwable $rejectedReason,
        ?callable $onRejected,
        ?string $expectedNextValue,
        ?string $expectedNextReason,
        string $expectedNextState
    ): void {
        $promise = new SyncPromise();
        self::assertSame(SyncPromise::PENDING, $promise->state);

        $promise->reject($rejectedReason);
        self::assertSame(SyncPromise::REJECTED, $promise->state);

        try {
            $promise->reject(new \Exception('other-reason'));
            self::fail('Expected exception not thrown');
        } catch (\Throwable $e) {
            self::assertSame('Cannot change rejection reason', $e->getMessage());
        }

        try {
            $promise->resolve('anything');
            self::fail('Expected exception not thrown');
        } catch (\Throwable $e) {
            self::assertSame('Cannot resolve rejected promise', $e->getMessage());
        }

        $nextPromise = $promise->then(
            static function (): void {},
            null
        );
        self::assertSame($promise, $nextPromise);

        $onFulfilledCalled = false;
        $nextPromise = $promise->then(
            static function () use (&$onFulfilledCalled): void {
                $onFulfilledCalled = true;
            },
            $onRejected
        );

        if ($onRejected !== null) {
            self::assertNotSame($promise, $nextPromise);
            self::assertSame(SyncPromise::PENDING, $nextPromise->state);
        } else {
            /** @phpstan-ignore argument.unresolvableType (false positive?)  */
            self::assertSame(SyncPromise::REJECTED, $nextPromise->state);
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
        self::assertSame(SyncPromise::PENDING, $promise->state);

        try {
            $promise->resolve($promise);
            self::fail('Expected exception not thrown');
        } catch (\Throwable $e) {
            self::assertSame('Cannot resolve promise with self', $e->getMessage());
            self::assertSame(SyncPromise::PENDING, $promise->state);
        }

        // Try to resolve with other promise (must resolve when other promise resolves)
        $otherPromise = new SyncPromise();
        $promise->resolve($otherPromise);

        self::assertSame(SyncPromise::PENDING, $promise->state);
        self::assertSame(SyncPromise::PENDING, $otherPromise->state);

        $otherPromise->resolve('the value');
        self::assertSame(SyncPromise::FULFILLED, $otherPromise->state);
        self::assertSame(SyncPromise::PENDING, $promise->state);
        self::assertValidPromise($promise, 'the value', null, SyncPromise::FULFILLED);

        $promise = new SyncPromise();
        $promise->resolve('resolved!');

        self::assertValidPromise($promise, 'resolved!', null, SyncPromise::FULFILLED);

        // Test rejections
        $promise = new SyncPromise();
        self::assertSame(SyncPromise::PENDING, $promise->state);

        try {
            // @phpstan-ignore-next-line purposefully wrong
            $promise->reject('a');
            self::fail('Expected exception not thrown');
        } catch (\Throwable $e) {
            self::assertSame(SyncPromise::PENDING, $promise->state);
        }

        $promise->reject(new \Exception('Rejected Reason'));
        self::assertValidPromise($promise, null, 'Rejected Reason', SyncPromise::REJECTED);

        $promise = new SyncPromise();
        $promise2 = $promise->then(
            null,
            static fn (): string => 'value'
        );
        $promise->reject(new \Exception('Rejected Again'));
        self::assertValidPromise($promise2, 'value', null, SyncPromise::FULFILLED);

        $promise = new SyncPromise();
        $promise2 = $promise->then();
        $promise->reject(new \Exception('Rejected Once Again'));
        self::assertValidPromise($promise2, null, 'Rejected Once Again', SyncPromise::REJECTED);
    }

    public function testPendingPromiseThen(): void
    {
        $promise = new SyncPromise();
        self::assertSame(SyncPromise::PENDING, $promise->state);

        $nextPromise = $promise->then();
        self::assertNotSame($promise, $nextPromise);
        self::assertSame(SyncPromise::PENDING, $promise->state);
        self::assertSame(SyncPromise::PENDING, $nextPromise->state);

        // Make sure that it queues derivative promises until resolution:
        $onFulfilledCount = 0;
        $onRejectedCount = 0;
        $onFulfilled = static function ($value) use (&$onFulfilledCount): int {
            ++$onFulfilledCount;

            return $onFulfilledCount;
        };

        $onRejected = static function ($reason) use (&$onRejectedCount): void {
            ++$onRejectedCount;

            throw $reason;
        };

        $nextPromise2 = $promise->then($onFulfilled, $onRejected);
        $nextPromise3 = $promise->then($onFulfilled, $onRejected);
        $nextPromise4 = $promise->then($onFulfilled, $onRejected);

        self::assertCount(0, SyncPromise::getQueue());
        self::assertSame(0, $onFulfilledCount);
        self::assertSame(0, $onRejectedCount);
        $promise->resolve(1);

        self::assertCount(4, SyncPromise::getQueue());
        self::assertSame(0, $onFulfilledCount); // @phpstan-ignore-line side-effects
        self::assertSame(0, $onRejectedCount); // @phpstan-ignore-line side-effects
        self::assertSame(SyncPromise::PENDING, $nextPromise->state);
        self::assertSame(SyncPromise::PENDING, $nextPromise2->state);
        self::assertSame(SyncPromise::PENDING, $nextPromise3->state);
        self::assertSame(SyncPromise::PENDING, $nextPromise4->state);

        SyncPromise::runQueue();
        self::assertCount(0, SyncPromise::getQueue());
        self::assertSame(3, $onFulfilledCount); // @phpstan-ignore-line side-effects
        self::assertSame(0, $onRejectedCount); // @phpstan-ignore-line side-effects
        self::assertValidPromise($nextPromise, 1, null, SyncPromise::FULFILLED);
        self::assertValidPromise($nextPromise2, 1, null, SyncPromise::FULFILLED);
        self::assertValidPromise($nextPromise3, 2, null, SyncPromise::FULFILLED);
        self::assertValidPromise($nextPromise4, 3, null, SyncPromise::FULFILLED);
    }

    public function testRunEmptyQueue(): void
    {
        SyncPromise::runQueue();
        $this->assertDidNotCrash();
    }
}
