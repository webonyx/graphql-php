<?php declare(strict_types=1);

// Run local test server
// php -S localhost:8080 graphql.php

// Try query
// curl -d '{"query": "query { echo(message: \"Hello World\") }" }' -H "Content-Type: application/json" http://localhost:8080

// Try mutation
// curl -d '{"query": "mutation { sum(x: 2, y: 2) }" }' -H "Content-Type: application/json" http://localhost:8080

require_once __DIR__ . '/../../vendor/autoload.php';

use GraphQL\Server\StandardServer;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

try {
    $queryType = new ObjectType([
        'name' => 'Query',
        'fields' => [
            'echo' => [
                'type' => Type::string(),
                'args' => [
                    'message' => ['type' => Type::string()],
                ],
                'resolve' => static fn (array $rootValue, array $args): string => $rootValue['prefix'] . $args['message'],
            ],
        ],
    ]);

    $mutationType = new ObjectType([
        'name' => 'Mutation',
        'fields' => [
            'sum' => [
                'type' => Type::int(),
                'args' => [
                    'x' => ['type' => Type::int()],
                    'y' => ['type' => Type::int()],
                ],
                'resolve' => static fn (array $rootValue, array $args): int => $args['x'] + $args['y'],
            ],
        ],
    ]);

    // See docs on schema options:
    // https://webonyx.github.io/graphql-php/schema-definition/#configuration-options
    $schema = new Schema([
        'query' => $queryType,
        'mutation' => $mutationType,
    ]);

    $rootValue = ['prefix' => 'You said: '];

    // See docs on server options:
    // https://webonyx.github.io/graphql-php/executing-queries/#server-configuration-options
    $server = new StandardServer([
        'schema' => $schema,
        'rootValue' => $rootValue,
    ]);

    $server->handleRequest();
} catch (Throwable $e) {
    StandardServer::send500Error($e);
}
