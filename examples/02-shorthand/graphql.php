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
    $schema    = BuildSchema::build(file_get_contents(__DIR__ . '/schema.graphql'));
    $rootValue = include __DIR__ . '/rootValue.php';

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
