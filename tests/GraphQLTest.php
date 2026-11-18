<?php declare(strict_types=1);

namespace GraphQL\Tests;

use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Executor\Promise\Promise;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use PHPUnit\Framework\TestCase;

final class GraphQLTest extends TestCase
{
    public function testPromiseToExecute(): void
    {
        $promiseAdapter = new SyncPromiseAdapter();
        $schema = new Schema(
            (new SchemaConfig())
                ->setQuery(new ObjectType([
                    'name' => 'Query',
                    'fields' => [
                        'sayHi' => [
                            'type' => Type::nonNull(Type::string()),
                            'args' => [
                                'name' => [
                                    'type' => Type::nonNull(Type::string()),
                                ],
                            ],
                            'resolve' => static fn ($rootValue, array $args): Promise => $promiseAdapter->createFulfilled("Hi {$args['name']}!"),
                        ],
                    ],
                ]))
        );

        $promise = GraphQL::promiseToExecute($promiseAdapter, $schema, '{ sayHi(name: "John") }');
        $result = $promiseAdapter->wait($promise);
        self::assertTrue($result->executed);
        self::assertSame(['data' => ['sayHi' => 'Hi John!']], $result->toArray());
    }

    public function testExecutionOutcomeDistinguishesRequestErrorFromNullData(): void
    {
        $schema = new Schema(
            (new SchemaConfig())
                ->setQuery(new ObjectType([
                    'name' => 'Query',
                    'fields' => [
                        'required' => [
                            'type' => Type::nonNull(Type::string()),
                            'resolve' => static fn () => null,
                        ],
                    ],
                ]))
        );

        $requestError = GraphQL::executeQuery($schema, 'not a query');
        self::assertFalse($requestError->executed);
        self::assertNotEmpty($requestError->errors);

        $executionResult = GraphQL::executeQuery($schema, '{ required }');
        self::assertTrue($executionResult->executed);
        self::assertNull($executionResult->data);
        self::assertSame(
            [
                'errors' => [[
                    'message' => 'Internal server error',
                    'locations' => [['line' => 1, 'column' => 3]],
                    'path' => ['required'],
                ]],
            ],
            $executionResult->toArray()
        );
    }
}
