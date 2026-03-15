<?php declare(strict_types=1);

namespace GraphQL\Benchmarks;

use GraphQL\GraphQL;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

/**
 * Benchmarks comparing query execution performance with and without $trustResult.
 *
 * $trustResult=true skips per-field/per-object:
 * - Leaf value serialization (scalar type conversions)
 * - isTypeOf validation checks
 *
 * Scenarios:
 * 1. Built-in scalars at scale — 500 items × 8 fields = 4 000 serialize() calls
 * 2. Custom scalar with non-trivial serialization — shows benefit when serialize() does real work
 * 3. isTypeOf validation — 100 objects each triggering an isTypeOf() callback
 * 4. Realistic combined — scalars + nested objects in a typical "list page" query
 *
 * @BeforeMethods({"setUp"})
 *
 * @OutputTimeUnit("milliseconds", precision=3)
 *
 * @Warmup(5)
 *
 * @Revs(200)
 *
 * @Iterations(10)
 */
class TrustResultBench
{
    private Schema $builtinScalarSchema;

    private DocumentNode $builtinScalarQuery;

    private Schema $customScalarSchema;

    private DocumentNode $customScalarQuery;

    private Schema $typeOfSchema;

    private DocumentNode $typeOfQuery;

    private Schema $combinedSchema;

    private DocumentNode $combinedQuery;

    public function setUp(): void
    {
        $this->setupBuiltinScalarScenario();
        $this->setupCustomScalarScenario();
        $this->setupTypeOfScenario();
        $this->setupCombinedScenario();
    }

    /**
     * Scenario 1: 500 items × 8 built-in scalar fields = 4 000 serialize() calls.
     * Built-in serialize() is cheap (type coercion), so gains here are modest.
     */
    private function setupBuiltinScalarScenario(): void
    {
        $items = array_fill(0, 500, [
            'id' => 1,
            'name' => 'Widget',
            'price' => 9.99,
            'active' => true,
            'stock' => 42,
            'rating' => 4.5,
            'views' => 1337,
            'score' => 99,
        ]);

        $productType = new ObjectType([
            'name' => 'Product',
            'fields' => [
                'id' => ['type' => Type::int()],
                'name' => ['type' => Type::string()],
                'price' => ['type' => Type::float()],
                'active' => ['type' => Type::boolean()],
                'stock' => ['type' => Type::int()],
                'rating' => ['type' => Type::float()],
                'views' => ['type' => Type::int()],
                'score' => ['type' => Type::int()],
            ],
        ]);

        $this->builtinScalarSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'products' => [
                        'type' => Type::listOf($productType),
                        'resolve' => static fn (): array => $items,
                    ],
                ],
            ]),
        ]);

        $this->builtinScalarQuery = Parser::parse(new Source(
            '{ products { id name price active stock rating views score } }'
        ));
    }

    public function benchBuiltinScalarFields(): void
    {
        GraphQL::executeQuery($this->builtinScalarSchema, $this->builtinScalarQuery);
    }

    public function benchBuiltinScalarFieldsTrusted(): void
    {
        GraphQL::executeQuery($this->builtinScalarSchema, $this->builtinScalarQuery, trustResult: true);
    }

    /**
     * Scenario 2: 100 items × 5 custom scalar fields. The custom serialize() does real work
     * (slug normalisation + validation), making the per-field cost measurable.
     */
    private function setupCustomScalarScenario(): void
    {
        $items = array_fill(0, 100, [
            'code' => 'WIDGET-001',
            'slug' => 'my-product-slug',
            'ref' => 'REF-ABC-123',
            'tag' => 'electronics/gadgets',
            'sku' => 'SKU-XYZ-999',
        ]);

        // A scalar that lowercases and strips non-alphanumeric characters on serialize.
        $slugScalar = new CustomScalarType([
            'name' => 'Slug',
            'serialize' => static fn ($value): string => strtolower(
                preg_replace('/[^a-z0-9\-]/i', '-', (string) $value) ?? (string) $value
            ),
            'parseValue' => static fn ($value): string => (string) $value,
            'parseLiteral' => static fn ($ast): string => $ast->value,
        ]);

        $itemType = new ObjectType([
            'name' => 'Item',
            'fields' => [
                'code' => ['type' => $slugScalar],
                'slug' => ['type' => $slugScalar],
                'ref' => ['type' => $slugScalar],
                'tag' => ['type' => $slugScalar],
                'sku' => ['type' => $slugScalar],
            ],
        ]);

        $this->customScalarSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'items' => [
                        'type' => Type::listOf($itemType),
                        'resolve' => static fn (): array => $items,
                    ],
                ],
            ]),
        ]);

        $this->customScalarQuery = Parser::parse(new Source(
            '{ items { code slug ref tag sku } }'
        ));
    }

    public function benchCustomScalarFields(): void
    {
        GraphQL::executeQuery($this->customScalarSchema, $this->customScalarQuery);
    }

    public function benchCustomScalarFieldsTrusted(): void
    {
        GraphQL::executeQuery($this->customScalarSchema, $this->customScalarQuery, trustResult: true);
    }

    /**
     * Scenario 3: 100 objects each triggering an isTypeOf() callback.
     * trustResult skips all isTypeOf checks.
     */
    private function setupTypeOfScenario(): void
    {
        $users = array_fill(0, 100, [
            '__typename' => 'User',
            'id' => 1,
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ]);

        $userType = new ObjectType([
            'name' => 'User',
            'fields' => [
                'id' => ['type' => Type::int()],
                'name' => ['type' => Type::string()],
                'email' => ['type' => Type::string()],
            ],
            'isTypeOf' => static fn ($value): bool => is_array($value)
                && ($value['__typename'] ?? null) === 'User',
        ]);

        $this->typeOfSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'users' => [
                        'type' => Type::listOf($userType),
                        'resolve' => static fn (): array => $users,
                    ],
                ],
            ]),
        ]);

        $this->typeOfQuery = Parser::parse(new Source('{ users { id name email } }'));
    }

    public function benchIsTypeOf(): void
    {
        GraphQL::executeQuery($this->typeOfSchema, $this->typeOfQuery);
    }

    public function benchIsTypeOfTrusted(): void
    {
        GraphQL::executeQuery($this->typeOfSchema, $this->typeOfQuery, trustResult: true);
    }

    /**
     * Scenario 4: 30 orders with a nested customer object and 5 scalar fields each.
     * Combines scalar serialization (30 × 8 = 240 calls) and nested object resolution.
     */
    private function setupCombinedScenario(): void
    {
        $orders = array_fill(0, 30, [
            'id' => 1,
            'status' => 'shipped',
            'total' => 99.99,
            'quantity' => 3,
            'note' => 'fragile',
            'customer' => ['id' => 42, 'name' => 'Bob', 'tier' => 'gold'],
        ]);

        $customerType = new ObjectType([
            'name' => 'Customer',
            'fields' => [
                'id' => ['type' => Type::int()],
                'name' => ['type' => Type::string()],
                'tier' => ['type' => Type::string()],
            ],
        ]);

        $orderType = new ObjectType([
            'name' => 'Order',
            'fields' => [
                'id' => ['type' => Type::int()],
                'status' => ['type' => Type::string()],
                'total' => ['type' => Type::float()],
                'quantity' => ['type' => Type::int()],
                'note' => ['type' => Type::string()],
                'customer' => ['type' => $customerType],
            ],
        ]);

        $this->combinedSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'orders' => [
                        'type' => Type::listOf($orderType),
                        'resolve' => static fn (): array => $orders,
                    ],
                ],
            ]),
        ]);

        $this->combinedQuery = Parser::parse(new Source(
            '{ orders { id status total quantity note customer { id name tier } } }'
        ));
    }

    public function benchCombinedQuery(): void
    {
        GraphQL::executeQuery($this->combinedSchema, $this->combinedQuery);
    }

    public function benchCombinedQueryTrusted(): void
    {
        GraphQL::executeQuery($this->combinedSchema, $this->combinedQuery, trustResult: true);
    }
}
