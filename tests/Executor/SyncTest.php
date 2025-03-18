<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GraphQL\Deferred;
use GraphQL\Error\FormattedError;
use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Executor;
use GraphQL\Executor\Promise\Adapter\SyncPromise;
use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Executor\Promise\Promise;
use GraphQL\GraphQL;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Validator\DocumentValidator;
use PHPUnit\Framework\TestCase;

final class SyncTest extends TestCase
{
    use ArraySubsetAsserts;

    private Schema $schema;

    private SyncPromiseAdapter $promiseAdapter;

    public function setUp(): void
    {
        $this->schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'syncField' => [
                        'type' => Type::string(),
                        'resolve' => static fn ($rootValue) => $rootValue,
                    ],
                    'asyncField' => [
                        'type' => Type::string(),
                        'resolve' => static fn ($rootValue): Deferred => new Deferred(
                            static fn () => $rootValue
                        ),
                    ],
                ],
            ]),
            'mutation' => new ObjectType([
                'name' => 'Mutation',
                'fields' => [
                    'syncMutationField' => [
                        'type' => Type::string(),
                        'resolve' => static fn ($rootValue) => $rootValue,
                    ],
                ],
            ]),
        ]);

        $this->promiseAdapter = new SyncPromiseAdapter();
    }

    // Describe: Execute: synchronously when possible

    /** @see it('does not return a Promise for initial errors') */
    public function testDoesNotReturnAPromiseForInitialErrors(): void
    {
        $doc = 'fragment Example on Query { syncField }';
        $result = $this->execute(
            $this->schema,
            Parser::parse($doc),
            'rootValue'
        );
        self::assertSync(['errors' => [['message' => 'Must provide an operation.']]], $result);
    }

    /** @param mixed $rootValue */
    private function execute(Schema $schema, DocumentNode $doc, $rootValue = null): Promise
    {
        return Executor::promiseToExecute($this->promiseAdapter, $schema, $doc, $rootValue);
    }

    /**
     * @param array<string, mixed> $expectedFinalArray
     *
     * @throws \Exception
     */
    private static function assertSync(array $expectedFinalArray, Promise $actualResult): void
    {
        $message = 'Failed assertion that execution was synchronous';
        $adoptedPromise = $actualResult->adoptedPromise;

        self::assertInstanceOf(SyncPromise::class, $adoptedPromise, $message);
        self::assertSame(SyncPromise::FULFILLED, $adoptedPromise->state, $message);

        $result = $adoptedPromise->result;
        self::assertInstanceOf(ExecutionResult::class, $result);
        self::assertArraySubset($expectedFinalArray, $result->toArray());
    }

    /** @see it('does not return a Promise if fields are all synchronous') */
    public function testDoesNotReturnAPromiseIfFieldsAreAllSynchronous(): void
    {
        $doc = 'query Example { syncField }';
        $result = $this->execute(
            $this->schema,
            Parser::parse($doc),
            'rootValue'
        );
        self::assertSync(['data' => ['syncField' => 'rootValue']], $result);
    }

    // Describe: graphqlSync

    /** @see it('does not return a Promise if mutation fields are all synchronous') */
    public function testDoesNotReturnAPromiseIfMutationFieldsAreAllSynchronous(): void
    {
        $doc = 'mutation Example { syncMutationField }';
        $result = $this->execute(
            $this->schema,
            Parser::parse($doc),
            'rootValue'
        );
        self::assertSync(['data' => ['syncMutationField' => 'rootValue']], $result);
    }

    /** @see it('returns a Promise if any field is asynchronous') */
    public function testReturnsAPromiseIfAnyFieldIsAsynchronous(): void
    {
        $doc = 'query Example { syncField, asyncField }';
        $result = $this->execute(
            $this->schema,
            Parser::parse($doc),
            'rootValue'
        );
        $this->assertAsync(['data' => ['syncField' => 'rootValue', 'asyncField' => 'rootValue']], $result);
    }

    /**
     * @param array<string, mixed> $expectedFinalArray
     *
     * @throws \Exception
     * @throws InvariantViolation
     */
    private function assertAsync(array $expectedFinalArray, Promise $actualResult): void
    {
        $message = 'Failed assertion that execution was asynchronous';
        $adoptedPromise = $actualResult->adoptedPromise;

        self::assertInstanceOf(SyncPromise::class, $adoptedPromise, $message);
        self::assertSame(SyncPromise::PENDING, $adoptedPromise->state, $message);

        $resolvedResult = $this->promiseAdapter->wait($actualResult);
        self::assertInstanceOf(ExecutionResult::class, $resolvedResult);
        self::assertArraySubset($expectedFinalArray, $resolvedResult->toArray());
    }

    /** @see it('does not return a Promise for syntax errors') */
    public function testDoesNotReturnAPromiseForSyntaxErrors(): void
    {
        $result = $this->graphqlSync(
            $this->schema,
            'fragment Example on Query { { { syncField }'
        );
        self::assertSync(
            [
                'errors' => [
                    [
                        'message' => 'Syntax Error: Expected Name, found {',
                        'locations' => [['line' => 1, 'column' => 29]],
                    ],
                ],
            ],
            $result
        );
    }

    /**
     * @param mixed $rootValue
     *
     * @throws \Exception
     * @throws InvariantViolation
     */
    private function graphqlSync(Schema $schema, string $doc, $rootValue = null): Promise
    {
        return GraphQL::promiseToExecute($this->promiseAdapter, $schema, $doc, $rootValue);
    }

    /** @see it('does not return a Promise for validation errors') */
    public function testDoesNotReturnAPromiseForValidationErrors(): void
    {
        $doc = 'fragment Example on Query { unknownField }';
        $validationErrors = DocumentValidator::validate($this->schema, Parser::parse($doc));
        $result = $this->graphqlSync(
            $this->schema,
            $doc
        );
        $expected = [
            'errors' => array_map(
                [FormattedError::class, 'createFromException'],
                $validationErrors
            ),
        ];
        self::assertSync($expected, $result);
    }

    /** @see it('does not return a Promise for sync execution') */
    public function testDoesNotReturnAPromiseForSyncExecution(): void
    {
        $doc = 'query Example { syncField }';
        $result = $this->graphqlSync(
            $this->schema,
            $doc,
            'rootValue'
        );
        self::assertSync(['data' => ['syncField' => 'rootValue']], $result);
    }
}
