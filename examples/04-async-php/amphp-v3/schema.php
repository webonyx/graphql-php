<?php declare(strict_types=1);

// The resolvers for "product" and "article" run asynchronously via fibers.
// This can have big advantages if you fetch data from different microservices.
// Keep in mind resolvers should use non-blocking async libraries like amphp/mysql, amphp/http-client, etc.

use Amp\Future;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

use function Amp\async;

return new Schema([
    'query' => new ObjectType([
        'name' => 'Query',
        'fields' => [
            'product' => [
                'type' => Type::string(),
                'resolve' => static fn (): Future => async(
                    // use inside the closure e.g. amphp/mysql, amphp/http-client, ...
                    static fn (): string => 'xyz'
                ),
            ],
            'article' => [
                'type' => Type::string(),
                'resolve' => static fn (): Future => async(
                    // use inside the closure e.g. amphp/mysql, amphp/http-client, ...
                    static fn (): string => 'zyx'
                ),
            ],
        ],
    ]),
]);
