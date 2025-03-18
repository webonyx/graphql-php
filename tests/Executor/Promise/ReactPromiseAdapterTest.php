<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\Promise;

use GraphQL\Executor\Promise\Adapter\ReactPromiseAdapter;
use PHPUnit\Framework\TestCase;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Promise\Promise as ReactPromise;

use function React\Promise\reject;
use function React\Promise\resolve;

/**
 * @group ReactPromise
 */
final class ReactPromiseAdapterTest extends TestCase
{
    /** @var class-string<object> */
    private string $classFulfilledPromise;

    /** @var class-string<object> */
    private string $classRejectedPromise;

    public function setUp(): void
    {
        /** @var class-string<object> $classFulfilledPromise */
        $classFulfilledPromise = class_exists('\React\Promise\FulfilledPromise')
            ? '\React\Promise\FulfilledPromise'
            : '\React\Promise\Internal\FulfilledPromise';
        $this->classFulfilledPromise = $classFulfilledPromise;

        /** @var class-string<object> $classRejectedPromise */
        $classRejectedPromise = class_exists('\React\Promise\RejectedPromise')
            ? '\React\Promise\RejectedPromise'
            : '\React\Promise\Internal\RejectedPromise';
        $this->classRejectedPromise = $classRejectedPromise;
    }

    public function testIsThenableReturnsTrueWhenAReactPromiseIsGiven(): void
    {
        $reactPromiseSetRejectionHandler = function_exists('\React\Promise\set_rejection_handler')
            ? '\React\Promise\set_rejection_handler'
            : fn ($error) => null;

        $reactAdapter = new ReactPromiseAdapter();

        self::assertTrue($reactAdapter->isThenable(new ReactPromise(static fn () => null)));
        self::assertTrue($reactAdapter->isThenable(resolve(null)));
        $original = $reactPromiseSetRejectionHandler(fn (\Throwable $reason) => null);
        self::assertTrue($reactAdapter->isThenable(reject(new \Exception())));
        $reactPromiseSetRejectionHandler($original);
        self::assertFalse($reactAdapter->isThenable(static fn () => null));
        self::assertFalse($reactAdapter->isThenable(false));
        self::assertFalse($reactAdapter->isThenable(true));
        self::assertFalse($reactAdapter->isThenable(1));
        self::assertFalse($reactAdapter->isThenable(0));
        self::assertFalse($reactAdapter->isThenable('test'));
        self::assertFalse($reactAdapter->isThenable(''));
        self::assertFalse($reactAdapter->isThenable([]));
        self::assertFalse($reactAdapter->isThenable(new \stdClass()));
    }

    public function testConvertsReactPromisesToGraphQLOnes(): void
    {
        $reactAdapter = new ReactPromiseAdapter();
        $reactPromise = resolve(1);

        $promise = $reactAdapter->convertThenable($reactPromise);

        self::assertInstanceOf($this->classFulfilledPromise, $promise->adoptedPromise);
    }

    public function testThen(): void
    {
        $reactAdapter = new ReactPromiseAdapter();
        $reactPromise = resolve(1);
        $promise = $reactAdapter->convertThenable($reactPromise);

        $result = null;
        $resultPromise = $reactAdapter->then(
            $promise,
            static function ($value) use (&$result): void {
                $result = $value;
            }
        );

        self::assertSame(1, $result);
        self::assertInstanceOf($this->classFulfilledPromise, $resultPromise->adoptedPromise);
    }

    public function testCreate(): void
    {
        $reactAdapter = new ReactPromiseAdapter();
        $resolvedPromise = $reactAdapter->create(static function ($resolve): void {
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
        $reactAdapter = new ReactPromiseAdapter();
        $fulfilledPromise = $reactAdapter->createFulfilled(1);

        self::assertInstanceOf($this->classFulfilledPromise, $fulfilledPromise->adoptedPromise);

        $result = null;
        $fulfilledPromise->then(static function ($value) use (&$result): void {
            $result = $value;
        });

        self::assertSame(1, $result);
    }

    public function testCreateRejected(): void
    {
        $reactAdapter = new ReactPromiseAdapter();
        $rejectedPromise = $reactAdapter->createRejected(new \Exception('I am a bad promise'));

        self::assertInstanceOf($this->classRejectedPromise, $rejectedPromise->adoptedPromise);

        $exception = null;
        $rejectedPromise->then(
            null,
            static function ($error) use (&$exception): void {
                $exception = $error;
            }
        );

        self::assertInstanceOf(\Exception::class, $exception);
        self::assertSame('I am a bad promise', $exception->getMessage());
    }

    public function testAll(): void
    {
        $reactAdapter = new ReactPromiseAdapter();
        $promises = [resolve(1), resolve(2), resolve(3)];

        $allPromise = $reactAdapter->all($promises);

        self::assertInstanceOf($this->classFulfilledPromise, $allPromise->adoptedPromise);

        $result = null;
        $allPromise->then(static function ($values) use (&$result): void {
            $result = $values;
        });

        self::assertSame([1, 2, 3], $result);
    }

    public function testAllShouldPreserveTheOrderOfTheArrayWhenResolvingAsyncPromises(): void
    {
        $reactAdapter = new ReactPromiseAdapter();
        $deferred = new Deferred();
        $promises = [resolve(1), $deferred->promise(), resolve(3)];

        $result = null;
        $reactAdapter->all($promises)->then(static function ($values) use (&$result): void {
            $result = $values;
        });

        // Resolve the async promise
        $deferred->resolve(2);
        self::assertSame([1, 2, 3], $result);
    }
}
