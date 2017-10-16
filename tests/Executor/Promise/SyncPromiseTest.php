<?php
namespace GraphQL\Tests\Executor\Promise;

use GraphQL\Executor\Promise\Adapter\SyncPromise;

class SyncPromiseTest extends \PHPUnit_Framework_TestCase
{
    public function getFulfilledPromiseResolveData()
    {
        $onFulfilledReturnsNull = function() {
            return null;
        };
        $onFulfilledReturnsSameValue = function($value) {
            return $value;
        };
        $onFulfilledReturnsOtherValue = function($value) {
            return 'other-' . $value;
        };
        $onFulfilledThrows = function($value) {
            throw new \Exception("onFulfilled throws this!");
        };

        return [
            // $resolvedValue, $onFulfilled, $expectedNextValue, $expectedNextReason, $expectedNextState
            ['test-value', null, 'test-value', null, SyncPromise::FULFILLED],
            [uniqid(), $onFulfilledReturnsNull, null, null, SyncPromise::FULFILLED],
            ['test-value', $onFulfilledReturnsSameValue, 'test-value', null, SyncPromise::FULFILLED],
            ['test-value-2', $onFulfilledReturnsOtherValue, 'other-test-value-2', null, SyncPromise::FULFILLED],
            ['test-value-3', $onFulfilledThrows, null, "onFulfilled throws this!", SyncPromise::REJECTED],
        ];
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
    )
    {
        $promise = new SyncPromise();
        $this->assertEquals(SyncPromise::PENDING, $promise->state);

        $promise->resolve($resolvedValue);
        $this->assertEquals(SyncPromise::FULFILLED, $promise->state);

        try {
            $promise->resolve($resolvedValue . '-other-value');
            $this->fail('Expected exception not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Cannot change value of fulfilled promise', $e->getMessage());
        }

        try {
            $promise->reject(new \Exception('anything'));
            $this->fail('Expected exception not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Cannot reject fulfilled promise', $e->getMessage());
        }

        $nextPromise = $promise->then(null, function() {});
        $this->assertSame($promise, $nextPromise);

        $onRejectedCalled = false;
        $nextPromise = $promise->then($onFulfilled, function () use (&$onRejectedCalled) {
            $onRejectedCalled = true;
        });

        if ($onFulfilled) {
            $this->assertNotSame($promise, $nextPromise);
            $this->assertEquals(SyncPromise::PENDING, $nextPromise->state);
        } else {
            $this->assertEquals(SyncPromise::FULFILLED, $nextPromise->state);
        }
        $this->assertEquals(false, $onRejectedCalled);

        $this->assertValidPromise($nextPromise, $expectedNextReason, $expectedNextValue, $expectedNextState);

        $nextPromise2 = $promise->then($onFulfilled);
        $nextPromise3 = $promise->then($onFulfilled);

        if ($onFulfilled) {
            $this->assertNotSame($nextPromise, $nextPromise2);
        }

        SyncPromise::runQueue();

        $this->assertValidPromise($nextPromise2, $expectedNextReason, $expectedNextValue, $expectedNextState);
        $this->assertValidPromise($nextPromise3, $expectedNextReason, $expectedNextValue, $expectedNextState);
    }

    public function getRejectedPromiseData()
    {
        $onRejectedReturnsNull = function() {
            return null;
        };
        $onRejectedReturnsSomeValue = function($reason) {
            return 'some-value';
        };
        $onRejectedThrowsSameReason = function($reason) {
            throw $reason;
        };
        $onRejectedThrowsOtherReason = function($value) {
            throw new \Exception("onRejected throws other!");
        };

        return [
            // $rejectedReason, $onRejected, $expectedNextValue, $expectedNextReason, $expectedNextState
            [new \Exception('test-reason'), null, null, 'test-reason', SyncPromise::REJECTED],
            [new \Exception('test-reason-2'), $onRejectedReturnsNull, null, null, SyncPromise::FULFILLED],
            [new \Exception('test-reason-3'), $onRejectedReturnsSomeValue, 'some-value', null, SyncPromise::FULFILLED],
            [new \Exception('test-reason-4'), $onRejectedThrowsSameReason, null, 'test-reason-4', SyncPromise::REJECTED],
            [new \Exception('test-reason-5'), $onRejectedThrowsOtherReason, null, 'onRejected throws other!', SyncPromise::REJECTED],
        ];
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
    )
    {
        $promise = new SyncPromise();
        $this->assertEquals(SyncPromise::PENDING, $promise->state);

        $promise->reject($rejectedReason);
        $this->assertEquals(SyncPromise::REJECTED, $promise->state);

        try {
            $promise->reject(new \Exception('other-reason'));
            $this->fail('Expected exception not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Cannot change rejection reason', $e->getMessage());
        }

        try {
            $promise->resolve('anything');
            $this->fail('Expected exception not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Cannot resolve rejected promise', $e->getMessage());
        }

        $nextPromise = $promise->then(function() {}, null);
        $this->assertSame($promise, $nextPromise);

        $onFulfilledCalled = false;
        $nextPromise = $promise->then(
            function () use (&$onFulfilledCalled) {
                $onFulfilledCalled = true;
            },
            $onRejected
        );

        if ($onRejected) {
            $this->assertNotSame($promise, $nextPromise);
            $this->assertEquals(SyncPromise::PENDING, $nextPromise->state);
        } else {
            $this->assertEquals(SyncPromise::REJECTED, $nextPromise->state);
        }
        $this->assertEquals(false, $onFulfilledCalled);
        $this->assertValidPromise($nextPromise, $expectedNextReason, $expectedNextValue, $expectedNextState);

        $nextPromise2 = $promise->then(null, $onRejected);
        $nextPromise3 = $promise->then(null, $onRejected);

        if ($onRejected) {
            $this->assertNotSame($nextPromise, $nextPromise2);
        }

        SyncPromise::runQueue();

        $this->assertValidPromise($nextPromise2, $expectedNextReason, $expectedNextValue, $expectedNextState);
        $this->assertValidPromise($nextPromise3, $expectedNextReason, $expectedNextValue, $expectedNextState);
    }

    public function testPendingPromise()
    {
        $promise = new SyncPromise();
        $this->assertEquals(SyncPromise::PENDING, $promise->state);

        try {
            $promise->resolve($promise);
            $this->fail('Expected exception not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Cannot resolve promise with self', $e->getMessage());
            $this->assertEquals(SyncPromise::PENDING, $promise->state);
        }

        // Try to resolve with other promise (must resolve when other promise resolves)
        $otherPromise = new SyncPromise();
        $promise->resolve($otherPromise);

        $this->assertEquals(SyncPromise::PENDING, $promise->state);
        $this->assertEquals(SyncPromise::PENDING, $otherPromise->state);

        $otherPromise->resolve('the value');
        $this->assertEquals(SyncPromise::FULFILLED, $otherPromise->state);
        $this->assertEquals(SyncPromise::PENDING, $promise->state);
        $this->assertValidPromise($promise, null, 'the value', SyncPromise::FULFILLED);

        $promise = new SyncPromise();
        $promise->resolve('resolved!');

        $this->assertValidPromise($promise, null, 'resolved!', SyncPromise::FULFILLED);

        // Test rejections
        $promise = new SyncPromise();
        $this->assertEquals(SyncPromise::PENDING, $promise->state);

        try {
            $promise->reject('a');
            $this->fail('Expected exception not thrown');
        } catch (\PHPUnit_Framework_AssertionFailedError $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->assertEquals(SyncPromise::PENDING, $promise->state);
        } catch (\Exception $e) {
            $this->assertEquals(SyncPromise::PENDING, $promise->state);
        }

        $promise->reject(new \Exception("Rejected Reason"));
        $this->assertValidPromise($promise, "Rejected Reason", null, SyncPromise::REJECTED);

        $promise = new SyncPromise();
        $promise2 = $promise->then(null, function() {
            return 'value';
        });
        $promise->reject(new \Exception("Rejected Again"));
        $this->assertValidPromise($promise2, null, 'value', SyncPromise::FULFILLED);

        $promise = new SyncPromise();
        $promise2 = $promise->then();
        $promise->reject(new \Exception("Rejected Once Again"));
        $this->assertValidPromise($promise2, "Rejected Once Again", null, SyncPromise::REJECTED);
    }

    public function testPendingPromiseThen()
    {
        $promise = new SyncPromise();
        $this->assertEquals(SyncPromise::PENDING, $promise->state);

        $nextPromise = $promise->then();
        $this->assertNotSame($promise, $nextPromise);
        $this->assertEquals(SyncPromise::PENDING, $promise->state);
        $this->assertEquals(SyncPromise::PENDING, $nextPromise->state);

        // Make sure that it queues derivative promises until resolution:
        $onFulfilledCount = 0;
        $onRejectedCount = 0;
        $onFulfilled = function($value) use (&$onFulfilledCount) {
            $onFulfilledCount++;
            return $onFulfilledCount;
        };
        $onRejected = function($reason) use (&$onRejectedCount) {
            $onRejectedCount++;
            throw $reason;
        };

        $nextPromise2 = $promise->then($onFulfilled, $onRejected);
        $nextPromise3 = $promise->then($onFulfilled, $onRejected);
        $nextPromise4 = $promise->then($onFulfilled, $onRejected);

        $this->assertEquals(SyncPromise::getQueue()->count(), 0);
        $this->assertEquals($onFulfilledCount, 0);
        $this->assertEquals($onRejectedCount, 0);
        $promise->resolve(1);

        $this->assertEquals(SyncPromise::getQueue()->count(), 4);
        $this->assertEquals($onFulfilledCount, 0);
        $this->assertEquals($onRejectedCount, 0);
        $this->assertEquals(SyncPromise::PENDING, $nextPromise->state);
        $this->assertEquals(SyncPromise::PENDING, $nextPromise2->state);
        $this->assertEquals(SyncPromise::PENDING, $nextPromise3->state);
        $this->assertEquals(SyncPromise::PENDING, $nextPromise4->state);

        SyncPromise::runQueue();
        $this->assertEquals(SyncPromise::getQueue()->count(), 0);
        $this->assertEquals($onFulfilledCount, 3);
        $this->assertEquals($onRejectedCount, 0);
        $this->assertValidPromise($nextPromise, null, 1, SyncPromise::FULFILLED);
        $this->assertValidPromise($nextPromise2, null, 1, SyncPromise::FULFILLED);
        $this->assertValidPromise($nextPromise3, null, 2, SyncPromise::FULFILLED);
        $this->assertValidPromise($nextPromise4, null, 3, SyncPromise::FULFILLED);
    }

    private function assertValidPromise(SyncPromise $promise, $expectedNextReason, $expectedNextValue, $expectedNextState)
    {
        $actualNextValue = null;
        $actualNextReason = null;
        $onFulfilledCalled = false;
        $onRejectedCalled = false;

        $promise->then(
            function($nextValue) use (&$actualNextValue, &$onFulfilledCalled) {
                $onFulfilledCalled = true;
                $actualNextValue = $nextValue;
            },
            function(\Exception $reason) use (&$actualNextReason, &$onRejectedCalled) {
                $onRejectedCalled = true;
                $actualNextReason = $reason->getMessage();
            }
        );

        $this->assertEquals($onFulfilledCalled, false);
        $this->assertEquals($onRejectedCalled, false);

        SyncPromise::runQueue();

        $this->assertEquals(!$expectedNextReason, $onFulfilledCalled);
        $this->assertEquals(!!$expectedNextReason, $onRejectedCalled);

        $this->assertEquals($expectedNextValue, $actualNextValue);
        $this->assertEquals($expectedNextReason, $actualNextReason);
        $this->assertEquals($expectedNextState, $promise->state);
    }
}
