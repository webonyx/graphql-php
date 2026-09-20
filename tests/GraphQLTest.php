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
        self::assertSame(['data' => ['sayHi' => 'Hi John!']], $result->toArray());
    }

    public function testTrustResultFlagPropagatesViaExecuteQuery(): void
    {
        $schema = new Schema(
            (new SchemaConfig())
                ->setQuery(new ObjectType([
                    'name' => 'Query',
                    'fields' => [
                        'scalarNumber' => [
                            'type' => Type::int(),
                            'resolve' => static fn (): string => '123', // should be int, but we trust it
                        ],
                    ],
                ]))
        );

        $query = '{ scalarNumber }';

        // Without trustResult, it returns 123 (int) because Type::int() serializes '123' to 123
        $result = GraphQL::executeQuery($schema, $query);
        self::assertSame(['data' => ['scalarNumber' => 123]], $result->toArray());

        // With trustResult, it should return '123' (string) directly without serialization
        $result = GraphQL::executeQuery($schema, $query, null, null, null, null, null, null, true);
        self::assertSame(['data' => ['scalarNumber' => '123']], $result->toArray());
    }
}
