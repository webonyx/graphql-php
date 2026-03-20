<?php declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use GraphQL\GraphQL;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

$trimmedString = new CustomScalarType([
    'name' => Type::STRING,
    'serialize' => static fn ($value): string => trim((string) $value),
    'parseValue' => static fn ($value): string => trim((string) $value),
    'parseLiteral' => static fn ($valueNode): string => trim($valueNode->value),
]);

$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'greeting' => [
            'type' => Type::string(),
            'args' => [
                'name' => ['type' => Type::string()],
            ],
            'resolve' => static fn ($root, array $args): string => "  Hello, {$args['name']}!  ",
        ],
    ],
]);

// Override via types config
$schemaViaTypes = new Schema([
    'query' => $queryType,
    'types' => [$trimmedString],
]);

$result = GraphQL::executeQuery($schemaViaTypes, '{ greeting(name: "World") }');
echo "Override via types config:\n";
echo json_encode($result->toArray(), JSON_THROW_ON_ERROR) . "\n\n";

// Override via typeLoader
$queryTypeForLoader = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'greeting' => [
            'type' => Type::string(),
            'args' => [
                'name' => ['type' => Type::string()],
            ],
            'resolve' => static fn ($root, array $args): string => "  Hello, {$args['name']}!  ",
        ],
    ],
]);

$types = ['Query' => $queryTypeForLoader, 'String' => $trimmedString];
$schemaViaTypeLoader = new Schema([
    'query' => $queryTypeForLoader,
    'typeLoader' => static fn (string $name): ?Type => $types[$name] ?? null,
    'typeLoaderSupportsScalars' => true,
]);

$result = GraphQL::executeQuery($schemaViaTypeLoader, '{ greeting(name: "World") }');
echo "Override via typeLoader:\n";
echo json_encode($result->toArray(), JSON_THROW_ON_ERROR) . "\n";

// Expected output:
// Override via types config:
// {"data":{"greeting":"Hello, World!"}}
//
// Override via typeLoader:
// {"data":{"greeting":"Hello, World!"}}
