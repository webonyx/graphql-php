<?php
namespace GraphQL\Tests\Executor\Promise;

use GraphQL\Deferred;
use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\Promise\Adapter\SyncPromise;
use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Executor\Promise\Promise;

class SyncPromiseAdapterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var SyncPromiseAdapter
     */
    private $promises;

    public function setUp()
    {
        $this->promises = new SyncPromiseAdapter();
    }

    public function testIsThenable()
    {
        $this->assertEquals(true, $this->promises->isThenable(new Deferred(function() {})));
        $this->assertEquals(false, $this->promises->isThenable(false));
        $this->assertEquals(false, $this->promises->isThenable(true));
        $this->assertEquals(false, $this->promises->isThenable(1));
        $this->assertEquals(false, $this->promises->isThenable(0));
        $this->assertEquals(false, $this->promises->isThenable('test'));
        $this->assertEquals(false, $this->promises->isThenable(''));
        $this->assertEquals(false, $this->promises->isThenable([]));
        $this->assertEquals(false, $this->promises->isThenable(new \stdClass()));
    }

    public function testConvert()
    {
        $dfd = new Deferred(function() {});
        $result = $this->promises->convertThenable($dfd);

        $this->assertInstanceOf('GraphQL\Executor\Promise\Promise', $result);
        $this->assertInstanceOf('GraphQL\Executor\Promise\Adapter\SyncPromise', $result->adoptedPromise);

        try {
            $this->promises->convertThenable('');
            $this->fail('Expected exception no thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals('Expected instance of GraphQL\Deferred, got (empty string)', $e->getMessage());
        }
    }

    public function testThen()
    {
        $dfd = new Deferred(function() {});
        $promise = $this->promises->convertThenable($dfd);

        $result = $this->promises->then($promise);

        $this->assertInstanceOf('GraphQL\Executor\Promise\Promise', $result);
        $this->assertInstanceOf('GraphQL\Executor\Promise\Adapter\SyncPromise', $result->adoptedPromise);
    }

    public function testCreatePromise()
    {
        $promise = $this->promises->create(function($resolve, $reject) {});

        $this->assertInstanceOf('GraphQL\Executor\Promise\Promise', $promise);
        $this->assertInstanceOf('GraphQL\Executor\Promise\Adapter\SyncPromise', $promise->adoptedPromise);

        $promise = $this->promises->create(function($resolve, $reject) {
            $resolve('A');
        });

        $this->assertValidPromise($promise, null, 'A', SyncPromise::FULFILLED);
    }

    public function testCreateFulfilledPromise()
    {
        $promise = $this->promises->createFulfilled('test');
        $this->assertValidPromise($promise, null, 'test', SyncPromise::FULFILLED);
    }

    public function testCreateRejectedPromise()
    {
        $promise = $this->promises->createRejected(new \Exception('test reason'));
        $this->assertValidPromise($promise, 'test reason', null, SyncPromise::REJECTED);
    }

    public function testCreatePromiseAll()
    {
        $promise = $this->promises->all([]);
        $this->assertValidPromise($promise, null, [], SyncPromise::FULFILLED);

        $promise = $this->promises->all(['1']);
        $this->assertValidPromise($promise, null, ['1'], SyncPromise::FULFILLED);

        $promise1 = new SyncPromise();
        $promise2 = new SyncPromise();
        $promise3 = $promise2->then(
            function($value) {
                return $value .'-value3';
            }
        );

        $data = [
            '1',
            new Promise($promise1, $this->promises),
            new Promise($promise2, $this->promises),
            3,
            new Promise($promise3, $this->promises),
            []
        ];

        $promise = $this->promises->all($data);
        $this->assertValidPromise($promise, null, null, SyncPromise::PENDING);

        $promise1->resolve('value1');
        $this->assertValidPromise($promise, null, null, SyncPromise::PENDING);
        $promise2->resolve('value2');
        $this->assertValidPromise($promise, null, ['1', 'value1', 'value2', 3, 'value2-value3', []], SyncPromise::FULFILLED);
    }

    public function testWait()
    {
        $called = [];

        $deferred1 = new Deferred(function() use (&$called) {
            $called[] = 1;
            return 1;
        });
        $deferred2 = new Deferred(function() use (&$called) {
            $called[] = 2;
            return 2;
        });

        $p1 = $this->promises->convertThenable($deferred1);
        $p2 = $this->promises->convertThenable($deferred2);

        $p3 = $p2->then(function() use (&$called) {
            $dfd = new Deferred(function() use (&$called) {
                $called[] = 3;
                return 3;
            });
            return $this->promises->convertThenable($dfd);
        });

        $p4 = $p3->then(function() use (&$called) {
            return new Deferred(function() use (&$called) {
                $called[] = 4;
                return 4;
            });
        });

        $all = $this->promises->all([0, $p1, $p2, $p3, $p4]);

        $result = $this->promises->wait($p2);
        $this->assertEquals(2, $result);
        $this->assertEquals(SyncPromise::PENDING, $p3->adoptedPromise->state);
        $this->assertEquals(SyncPromise::PENDING, $all->adoptedPromise->state);
        $this->assertEquals([1, 2], $called);

        $expectedResult = [0,1,2,3,4];
        $result = $this->promises->wait($all);
        $this->assertEquals($expectedResult, $result);
        $this->assertEquals([1, 2, 3, 4], $called);
        $this->assertValidPromise($all, null, [0,1,2,3,4], SyncPromise::FULFILLED);
    }

    private function assertValidPromise($promise, $expectedNextReason, $expectedNextValue, $expectedNextState)
    {
        $this->assertInstanceOf('GraphQL\Executor\Promise\Promise', $promise);
        $this->assertInstanceOf('GraphQL\Executor\Promise\Adapter\SyncPromise', $promise->adoptedPromise);

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

        $this->assertSame($onFulfilledCalled, false);
        $this->assertSame($onRejectedCalled, false);

        SyncPromise::runQueue();

        if ($expectedNextState !== SyncPromise::PENDING) {
            $this->assertSame(!$expectedNextReason, $onFulfilledCalled);
            $this->assertSame(!!$expectedNextReason, $onRejectedCalled);
        }

        $this->assertSame($expectedNextValue, $actualNextValue);
        $this->assertSame($expectedNextReason, $actualNextReason);
        $this->assertSame($expectedNextState, $promise->adoptedPromise->state);
    }
}
