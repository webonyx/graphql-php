<?php
// Test this using following command
// php -S localhost:8080 ./graphql.php
require_once '../../vendor/autoload.php';

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Schema;
use GraphQL\GraphQL;

try {
    $queryType = new ObjectType([
        'name' => 'Query',
        'fields' => [
            'echo' => [
                'type' => Type::string(),
                'args' => [
                    'message' => ['type' => Type::string()],
                ],
                'resolve' => function ($root, $args) {
                    return $root['prefix'].$args['message'];
                }
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
                'resolve' => function ($root, $args) {
                    return $args['x'] + $args['y'];
                },
            ],
        ],
    ]);

    $schema = new Schema([
        'query' => $queryType,
        'mutation' => $mutationType,
    ]);

    $rawInput = file_get_contents('php://input');

    $rootValue = ['prefix' => 'You said: '];
    $result = GraphQL::execute($schema, $rawInput, $rootValue);
} catch (\Exception $e) {
    $result = [
        'error' => [
            'message' => $e->getMessage()
        ]
    ];
}
header('Content-Type: application/json; charset=UTF-8');
echo json_encode($result);

