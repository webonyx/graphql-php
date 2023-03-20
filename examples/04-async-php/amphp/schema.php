<?php declare(strict_types=1);

/*
 * The resolvers for "product" and "article" run asynchronously.
 * This can have big advantages if you fetch data from different microservices.
 * Keep in mind everything in "call" should now be non-blocking, check out async libraries like amphp/mysql, amphp/http-client.
 */

use Amp\Promise;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use function Amp\call;

return new Schema([
    'query' => new ObjectType([
        'name' => 'Query',
        'fields' => [
            'product' => [
                'type' => Type::string(),
                'resolve' => fn(): Promise => call(
                    // use inside the closure e.g. amphp/mysql, amphp/http-client, ...
                    fn(): string => 'xyz'
                ),
            ],
            'article' => [
                'type' => Type::string(),
                'resolve' => fn(): Promise => call(
                    // use inside the closure e.g. amphp/mysql, amphp/http-client, ...
                    fn(): string => 'zyx'
                ),
            ],
        ],
    ])
]);
