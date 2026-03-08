<?php declare(strict_types=1);

/**
 * Per-schema scalar override using BuiltInDefinitions.
 *
 * Run: php examples/06-per-schema-scalar-override/example.php
 *
 * The key pattern: pass a BuiltInDefinitions instance to SchemaConfig and use
 * that same instance's scalar accessors in field definitions — never Type::string()
 * or other static singleton accessors.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use GraphQL\GraphQL;
use GraphQL\Type\BuiltInDefinitions;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;

// A custom String scalar that trims leading/trailing whitespace on serialization.
$trimmedString = new CustomScalarType([
    'name' => Type::STRING,
    'serialize' => static fn (mixed $value): string => trim((string) $value),
    'parseValue' => static fn (mixed $value): string => trim((string) $value),
    'parseLiteral' => static fn (mixed $ast): string => trim($ast->value ?? ''),
]);

$builtInDefs = new BuiltInDefinitions([Type::STRING => $trimmedString]);

/**
 * Instance-based type registry that threads BuiltInDefinitions through all type
 * definitions.
 * Each type accessor that returns a string field must delegate to
 * $this->builtInDefs->string() rather than Type::string(), so every field in
 * the schema references the same scalar instance that was registered with the
 * schema.
 */
final class TypeRegistry
{
    /** @var array<string, ObjectType> */
    private array $cache = [];

    public function __construct(private readonly BuiltInDefinitions $builtInDefs) {}

    public function string(): ScalarType
    {
        return $this->builtInDefs->string();
    }

    public function user(): ObjectType
    {
        return $this->cache['User'] ??= new ObjectType([ // @phpstan-ignore missingType.checkedException (static configuration is known to be correct)
            'name' => 'User',
            'fields' => fn (): array => [
                'name' => ['type' => $this->string()],
                'email' => ['type' => $this->string()],
            ],
        ]);
    }
}

$registry = new TypeRegistry($builtInDefs);

$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'user' => [
            'type' => $registry->user(),
            'resolve' => static fn (): array => [
                'name' => '  Alice  ',
                'email' => '  alice@example.com  ',
            ],
        ],
        'greeting' => [
            'type' => $registry->string(),
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
