<?php declare(strict_types=1);

/**
 * Per-schema scalar override using BuiltInDefinitions.
 *
 * Run: php examples/06-per-schema-scalar-override/example.php
 *
 * Register a custom scalar with the same name as a built-in (e.g. "String")
 * via BuiltInDefinitions, and the schema transparently uses it — even for
 * fields that reference the global Type::string() singleton.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use GraphQL\GraphQL;
use GraphQL\Type\BuiltInDefinitions;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;

$trimmedString = new CustomScalarType([
    'name' => Type::STRING,
    'serialize' => static fn ($value): string => trim((string) $value),
    'parseValue' => static fn ($value): string => trim((string) $value),
    'parseLiteral' => static fn ($ast): string => trim($ast->value ?? ''),
]);

$builtInDefs = new BuiltInDefinitions([Type::STRING => $trimmedString]);

$userType = new ObjectType([
    'name' => 'User',
    'fields' => [
        'name' => Type::string(),
        'email' => Type::string(),
    ],
]);

$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'user' => [
            'type' => $userType,
            'resolve' => static fn (): array => [
                'name' => '  Alice  ',
                'email' => '  alice@example.com  ',
            ],
        ],
        'greeting' => [
            'type' => Type::string(),
            'resolve' => static fn (): string => '  hello world  ',
        ],
    ],
]);

$schema = new Schema(
    (new SchemaConfig())
        ->setQuery($queryType)
        ->setBuiltInDefinitions($builtInDefs)
);

$schema->assertValid();

$result = GraphQL::executeQuery($schema, '{ greeting user { name email } }');

if ($result->errors !== []) {
    foreach ($result->errors as $error) {
        echo "Error: {$error->getMessage()}\n";
    }
    exit(1);
}

$data = $result->toArray()['data'] ?? [];
echo json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";

// Expected output (whitespace trimmed by the custom scalar):
// {
//     "greeting": "hello world",
//     "user": {
//         "name": "Alice",
//         "email": "alice@example.com"
//     }
// }
