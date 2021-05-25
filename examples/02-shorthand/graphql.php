<?php

declare(strict_types=1);

// Test this using the following command:
// php -S localhost:8080 graphql.php

// Try the following example queries:
// curl http://localhost:8080 -d '{"query": "query { echo(message: \"Hello World\") }" }'
// curl http://localhost:8080 -d '{"query": "mutation { sum(x: 2, y: 2) }" }'

require_once __DIR__ . '/../../vendor/autoload.php';

use GraphQL\GraphQL;
use GraphQL\Utils\BuildSchema;

try {
    $schema    = BuildSchema::build(/** @lang GraphQL */ '
    type Query {
      echo(message: String!): String!
      sum(x: Int!, y: Int!): Int!
    }
    ');
    $rootValue = [
        'echo' => static fn (array $rootValue, array $args): string => $rootValue['prefix'] . $args['message'],
        'sum' => static fn (array $rootValue, array $args): int => $args['x'] + $args['y'],
        'prefix' => 'You said: ',
    ];

    $rawInput       = file_get_contents('php://input');
    $input          = json_decode($rawInput, true);
    $query          = $input['query'];
    $variableValues = $input['variables'] ?? null;

    $result = GraphQL::executeQuery($schema, $query, $rootValue, null, $variableValues);
} catch (Throwable $e) {
    $result = [
        'error' => [
            'message' => $e->getMessage(),
        ],
    ];
}

header('Content-Type: application/json; charset=UTF-8');
echo json_encode($result);
