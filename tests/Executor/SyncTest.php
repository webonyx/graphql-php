<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GraphQL\Deferred;
use GraphQL\Error\FormattedError;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Executor;
use GraphQL\Executor\Promise\Adapter\SyncPromise;
use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Executor\Promise\Promise;
use GraphQL\GraphQL;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Validator\DocumentValidator;
use PHPUnit\Framework\TestCase;

use function array_map;

class SyncTest extends TestCase
{
    use ArraySubsetAsserts;

    /** @var Schema */
    private $schema;

    /** @var SyncPromiseAdapter */
    private $promiseAdapter;

    public function setUp(): void
    {
        $this->schema = new Schema([
            'query'    => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'syncField'  => [
                        'type'    => Type::string(),
                        'resolve' => static function ($rootValue) {
                            return $rootValue;
                        },
                    ],
                    'asyncField' => [
                        'type'    => Type::string(),
                        'resolve' => static function ($rootValue): Deferred {
                            return new Deferred(static function () use ($rootValue) {
                                return $rootValue;
                            });
                        },
                    ],
                ],
            ]),
            'mutation' => new ObjectType([
                'name'   => 'Mutation',
                'fields' => [
                    'syncMutationField' => [
                        'type'    => Type::string(),
                        'resolve' => static function ($rootValue) {
                            return $rootValue;
                        },
                    ],
                ],
            ]),
        ]);

        $this->promiseAdapter = new SyncPromiseAdapter();
    }

    // Describe: Execute: synchronously when possible

    /**
     * @see it('does not return a Promise for initial errors')
     */
    public function testDoesNotReturnAPromiseForInitialErrors(): void
    {
        $doc    = 'fragment Example on Query { syncField }';
        $result = $this->execute(
            $this->schema,
            Parser::parse($doc),
            'rootValue'
        );
        self::assertSync(['errors' => [['message' => 'Must provide an operation.']]], $result);
    }

    private function execute($schema, $doc, $rootValue = null)
    {
        return Executor::promiseToExecute($this->promiseAdapter, $schema, $doc, $rootValue);
    }

    private static function assertSync($expectedFinalArray, $actualResult): void
    {
        $message = 'Failed assertion that execution was synchronous';
        self::assertInstanceOf(Promise::class, $actualResult, $message);
        self::assertInstanceOf(SyncPromise::class, $actualResult->adoptedPromise, $message);
        self::assertEquals(SyncPromise::FULFILLED, $actualResult->adoptedPromise->state, $message);
        self::assertInstanceOf(ExecutionResult::class, $actualResult->adoptedPromise->result, $message);
        self::assertArraySubset(
            $expectedFinalArray,
            $actualResult->adoptedPromise->result->toArray(),
            false,
            $message
        );
    }

    /**
     * @see it('does not return a Promise if fields are all synchronous')
     */
    public function testDoesNotReturnAPromiseIfFieldsAreAllSynchronous(): void
    {
        $doc    = 'query Example { syncField }';
        $result = $this->execute(
            $this->schema,
            Parser::parse($doc),
            'rootValue'
        );
        self::assertSync(['data' => ['syncField' => 'rootValue']], $result);
    }

    // Describe: graphqlSync

    /**
     * @see it('does not return a Promise if mutation fields are all synchronous')
     */
    public function testDoesNotReturnAPromiseIfMutationFieldsAreAllSynchronous(): void
    {
        $doc    = 'mutation Example { syncMutationField }';
        $result = $this->execute(
            $this->schema,
            Parser::parse($doc),
            'rootValue'
        );
        self::assertSync(['data' => ['syncMutationField' => 'rootValue']], $result);
    }

    /**
     * @see it('returns a Promise if any field is asynchronous')
     */
    public function testReturnsAPromiseIfAnyFieldIsAsynchronous(): void
    {
        $doc    = 'query Example { syncField, asyncField }';
        $result = $this->execute(
            $this->schema,
            Parser::parse($doc),
            'rootValue'
        );
        $this->assertAsync(['data' => ['syncField' => 'rootValue', 'asyncField' => 'rootValue']], $result);
    }

    private function assertAsync($expectedFinalArray, $actualResult)
    {
        $message = 'Failed assertion that execution was asynchronous';
        self::assertInstanceOf(Promise::class, $actualResult, $message);
        self::assertInstanceOf(SyncPromise::class, $actualResult->adoptedPromise, $message);
        self::assertEquals(SyncPromise::PENDING, $actualResult->adoptedPromise->state, $message);
        $resolvedResult = $this->promiseAdapter->wait($actualResult);
        self::assertInstanceOf(ExecutionResult::class, $resolvedResult, $message);
        self::assertArraySubset($expectedFinalArray, $resolvedResult->toArray(), false, $message);
    }

    /**
     * @see it('does not return a Promise for syntax errors')
     */
    public function testDoesNotReturnAPromiseForSyntaxErrors(): void
    {
        $doc    = 'fragment Example on Query { { { syncField }';
        $result = $this->graphqlSync(
            $this->schema,
            $doc
        );
        self::assertSync(
            [
                'errors' => [
                    [
                        'message'   => 'Syntax Error: Expected Name, found {',
                        'locations' => [['line' => 1, 'column' => 29]],
                    ],
                ],
            ],
            $result
        );
    }

    private function graphqlSync($schema, $doc, $rootValue = null)
    {
        return GraphQL::promiseToExecute($this->promiseAdapter, $schema, $doc, $rootValue);
    }

    /**
     * @see it('does not return a Promise for validation errors')
     */
    public function testDoesNotReturnAPromiseForValidationErrors(): void
    {
        $doc              = 'fragment Example on Query { unknownField }';
        $validationErrors = DocumentValidator::validate($this->schema, Parser::parse($doc));
        $result           = $this->graphqlSync(
            $this->schema,
            $doc
        );
        $expected         = [
            'errors' => array_map(
                [FormattedError::class, 'createFromException'],
                $validationErrors
            ),
        ];
        self::assertSync($expected, $result);
    }

    /**
     * @see it('does not return a Promise for sync execution')
     */
    public function testDoesNotReturnAPromiseForSyncExecution(): void
    {
        $doc    = 'query Example { syncField }';
        $result = $this->graphqlSync(
            $this->schema,
            $doc,
            'rootValue'
        );
        self::assertSync(['data' => ['syncField' => 'rootValue']], $result);
    }
}
