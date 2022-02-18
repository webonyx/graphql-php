<?php declare(strict_types=1);

// Run local test server
// php -S localhost:8080 graphql.php

// Try query
// curl -d '{"query": "query { echo(message: \"Hello World\") }" }' -H "Content-Type: application/json" http://localhost:8080

// Try mutation
// curl -d '{"query": "mutation { sum(x: 2, y: 2) }" }' -H "Content-Type: application/json" http://localhost:8080

require_once __DIR__ . '/../../vendor/autoload.php';

use GraphQL\GraphQL;
use GraphQL\Utils\BuildSchema;

try {
    $schema = BuildSchema::build(/** @lang GraphQL */ '
    type Query {
      echo(message: String!): String!
    }
    
    type Mutation {
      sum(x: Int!, y: Int!): Int!
    }
    ');
    $rootValue = [
        'echo' => static fn (array $rootValue, array $args): string => $rootValue['prefix'] . $args['message'],
        'sum' => static fn (array $rootValue, array $args): int => $args['x'] + $args['y'],
        'prefix' => 'You said: ',
    ];

    $rawInput = file_get_contents('php://input');
    if ($rawInput === false) {
        throw new RuntimeException('Failed to get php://input');
    }

    $input = json_decode($rawInput, true);
    $query = $input['query'];
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
