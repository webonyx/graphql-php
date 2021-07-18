<?php

declare(strict_types=1);

// Test this using the following command:
// php -S localhost:8080 graphql.php

// Try the following example queries:
// curl http://localhost:8080 -d '{"query": "query { echo(message: \"Hello World\") }" }'
// curl http://localhost:8080 -d '{"query": "mutation { sum(x: 2, y: 2) }" }'

require_once __DIR__ . '/../../vendor/autoload.php';

use GraphQL\GraphQL;
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
                'resolve' => static function ($rootValue, array $args): string {
                    return $rootValue['prefix'] . $args['message'];
                },
            ],
        ],
    ]);

    $mutationType = new ObjectType([
        'name' => 'Calc',
        'fields' => [
            'sum' => [
                'type' => Type::int(),
                'args' => [
                    'x' => ['type' => Type::int()],
                    'y' => ['type' => Type::int()],
                ],
                'resolve' => static function ($calc, array $args): int {
                    return $args['x'] + $args['y'];
                },
            ],
        ],
    ]);

    // See docs on schema options:
    // https://webonyx.github.io/graphql-php/type-system/schema/#configuration-options
    $schema = new Schema([
        'query' => $queryType,
        'mutation' => $mutationType,
    ]);

    $rawInput       = file_get_contents('php://input');
    $input          = json_decode($rawInput, true);
    $query          = $input['query'];
    $variableValues = $input['variables'] ?? null;

    $rootValue = ['prefix' => 'You said: '];
    $result    = GraphQL::executeQuery($schema, $query, $rootValue, null, $variableValues);
    $output    = $result->toArray();
} catch (Throwable $e) {
    $output = [
        'error' => [
            'message' => $e->getMessage(),
        ],
    ];
}

header('Content-Type: application/json; charset=UTF-8');
echo json_encode($output);
