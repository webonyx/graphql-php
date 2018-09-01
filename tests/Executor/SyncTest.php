<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor;

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
use GraphQL\Utils\Utils;
use GraphQL\Validator\DocumentValidator;
use PHPUnit\Framework\TestCase;

class SyncTest extends TestCase
{
    /** @var Schema */
    private $schema;

    /** @var SyncPromiseAdapter */
    private $promiseAdapter;

    public function setUp()
    {
        $this->schema = new Schema([
            'query'    => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'syncField'  => [
                        'type'    => Type::string(),
                        'resolve' => function ($rootValue) {
                            return $rootValue;
                        },
                    ],
                    'asyncField' => [
                        'type'    => Type::string(),
                        'resolve' => function ($rootValue) {
                            return new Deferred(function () use ($rootValue) {
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
                        'resolve' => function ($rootValue) {
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
    public function testDoesNotReturnAPromiseForInitialErrors() : void
    {
        $doc    = 'fragment Example on Query { syncField }';
        $result = $this->execute(
            $this->schema,
            Parser::parse($doc),
            'rootValue'
        );
        $this->assertSync(['errors' => [['message' => 'Must provide an operation.']]], $result);
    }

    private function execute($schema, $doc, $rootValue = null)
    {
        return Executor::promiseToExecute($this->promiseAdapter, $schema, $doc, $rootValue);
    }

    private function assertSync($expectedFinalArray, $actualResult)
    {
        $message = 'Failed assertion that execution was synchronous';
        $this->assertInstanceOf(Promise::class, $actualResult, $message);
        $this->assertInstanceOf(SyncPromise::class, $actualResult->adoptedPromise, $message);
        $this->assertEquals(SyncPromise::FULFILLED, $actualResult->adoptedPromise->state, $message);
        $this->assertInstanceOf(ExecutionResult::class, $actualResult->adoptedPromise->result, $message);
        $this->assertArraySubset(
            $expectedFinalArray,
            $actualResult->adoptedPromise->result->toArray(),
            false,
            $message
        );
    }

    /**
     * @see it('does not return a Promise if fields are all synchronous')
     */
    public function testDoesNotReturnAPromiseIfFieldsAreAllSynchronous() : void
    {
        $doc    = 'query Example { syncField }';
        $result = $this->execute(
            $this->schema,
            Parser::parse($doc),
            'rootValue'
        );
        $this->assertSync(['data' => ['syncField' => 'rootValue']], $result);
    }

    // Describe: graphqlSync

    /**
     * @see it('does not return a Promise if mutation fields are all synchronous')
     */
    public function testDoesNotReturnAPromiseIfMutationFieldsAreAllSynchronous() : void
    {
        $doc    = 'mutation Example { syncMutationField }';
        $result = $this->execute(
            $this->schema,
            Parser::parse($doc),
            'rootValue'
        );
        $this->assertSync(['data' => ['syncMutationField' => 'rootValue']], $result);
    }

    /**
     * @see it('returns a Promise if any field is asynchronous')
     */
    public function testReturnsAPromiseIfAnyFieldIsAsynchronous() : void
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
        $this->assertInstanceOf(Promise::class, $actualResult, $message);
        $this->assertInstanceOf(SyncPromise::class, $actualResult->adoptedPromise, $message);
        $this->assertEquals(SyncPromise::PENDING, $actualResult->adoptedPromise->state, $message);
        $resolvedResult = $this->promiseAdapter->wait($actualResult);
        $this->assertInstanceOf(ExecutionResult::class, $resolvedResult, $message);
        $this->assertArraySubset($expectedFinalArray, $resolvedResult->toArray(), false, $message);
    }

    /**
     * @see it('does not return a Promise for syntax errors')
     */
    public function testDoesNotReturnAPromiseForSyntaxErrors() : void
    {
        $doc    = 'fragment Example on Query { { { syncField }';
        $result = $this->graphqlSync(
            $this->schema,
            $doc
        );
        $this->assertSync(
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
    public function testDoesNotReturnAPromiseForValidationErrors() : void
    {
        $doc              = 'fragment Example on Query { unknownField }';
        $validationErrors = DocumentValidator::validate($this->schema, Parser::parse($doc));
        $result           = $this->graphqlSync(
            $this->schema,
            $doc
        );
        $expected         = [
            'errors' => Utils::map(
                $validationErrors,
                function ($e) {
                    return FormattedError::createFromException($e);
                }
            ),
        ];
        $this->assertSync($expected, $result);
    }

    /**
     * @see it('does not return a Promise for sync execution')
     */
    public function testDoesNotReturnAPromiseForSyncExecution() : void
    {
        $doc    = 'query Example { syncField }';
        $result = $this->graphqlSync(
            $this->schema,
            $doc,
            'rootValue'
        );
        $this->assertSync(['data' => ['syncField' => 'rootValue']], $result);
    }
}
