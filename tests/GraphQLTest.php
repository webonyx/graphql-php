<?php declare(strict_types=1);

namespace GraphQL\Tests;

use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Executor\Promise\Promise;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

class GraphQLTest extends TestCase
{
    public function testPromiseToExecute(): void
    {
        $promiseAdapter = new SyncPromiseAdapter();
        $schema = new Schema(
            [
                'query' => new ObjectType(
                    [
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
                    ]
                ),
            ]
        );

        $promise = GraphQL::promiseToExecute($promiseAdapter, $schema, '{ sayHi(name: "John") }');
        $result = $promiseAdapter->wait($promise);
        self::assertSame(['data' => ['sayHi' => 'Hi John!']], $result->toArray());
    }
}
