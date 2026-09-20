<?php declare(strict_types=1);

namespace GraphQL\Benchmarks;

use GraphQL\GraphQL;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

/**
 * Compares query execution with and without the $trustResult flag.
 *
 * With $trustResult=true the executor skips per-value validation of resolver
 * results: leaf values are not serialized and isTypeOf checks are not run.
 *
 * @BeforeMethods({"setUp"})
 *
 * @OutputTimeUnit("milliseconds", precision=3)
 *
 * @Warmup(5)
 *
 * @Revs(100)
 *
 * @Iterations(10)
 */
class TrustResultBench
{
    private Schema $leafSchema;

    private DocumentNode $leafQuery;

    private Schema $customScalarSchema;

    private DocumentNode $customScalarQuery;

    private Schema $isTypeOfSchema;

    private DocumentNode $isTypeOfQuery;

    public function setUp(): void
    {
        $itemType = new ObjectType([
            'name' => 'Item',
            'fields' => [
                'id' => ['type' => Type::int()],
                'name' => ['type' => Type::string()],
                'price' => ['type' => Type::float()],
                'active' => ['type' => Type::boolean()],
                'stock' => ['type' => Type::int()],
                'rating' => ['type' => Type::float()],
            ],
        ]);

        $items = array_fill(0, 500, [
            'id' => 1,
            'name' => 'Widget',
            'price' => 9.99,
            'active' => true,
            'stock' => 42,
            'rating' => 4.5,
        ]);

        $this->leafSchema = new Schema([
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
        $this->leafQuery = Parser::parse('{ items { id name price active stock rating } }');

        $slugScalar = new CustomScalarType([
            'name' => 'Slug',
            'serialize' => static fn ($value): string => strtolower(
                preg_replace('/[^a-z0-9\-]/i', '-', (string) $value) ?? (string) $value
            ),
            'parseValue' => static fn ($value): string => (string) $value,
            'parseLiteral' => static fn ($ast): string => $ast->value,
        ]);

        $productType = new ObjectType([
            'name' => 'Product',
            'fields' => [
                'code' => ['type' => $slugScalar],
                'slug' => ['type' => $slugScalar],
                'ref' => ['type' => $slugScalar],
                'tag' => ['type' => $slugScalar],
            ],
        ]);

        $products = array_fill(0, 100, [
            'code' => 'WIDGET-001',
            'slug' => 'My Product Slug',
            'ref' => 'REF-ABC-123',
            'tag' => 'electronics/gadgets',
        ]);

        $this->customScalarSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'products' => [
                        'type' => Type::listOf($productType),
                        'resolve' => static fn (): array => $products,
                    ],
                ],
            ]),
        ]);
        $this->customScalarQuery = Parser::parse('{ products { code slug ref tag } }');

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

        $users = array_fill(0, 100, [
            '__typename' => 'User',
            'id' => 1,
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ]);

        $this->isTypeOfSchema = new Schema([
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
        $this->isTypeOfQuery = Parser::parse('{ users { id name email } }');
    }

    /** 500 items with 6 built-in scalar fields each. */
    public function benchLeafSerialization(): void
    {
        GraphQL::executeQuery($this->leafSchema, $this->leafQuery);
    }

    public function benchLeafSerializationTrusted(): void
    {
        GraphQL::executeQuery($this->leafSchema, $this->leafQuery, null, null, null, null, null, null, true);
    }

    /** 100 products with 4 custom scalar fields whose serialize() does real work. */
    public function benchCustomScalar(): void
    {
        GraphQL::executeQuery($this->customScalarSchema, $this->customScalarQuery);
    }

    public function benchCustomScalarTrusted(): void
    {
        GraphQL::executeQuery($this->customScalarSchema, $this->customScalarQuery, null, null, null, null, null, null, true);
    }

    /** 100 objects that each go through an isTypeOf() check. */
    public function benchIsTypeOf(): void
    {
        GraphQL::executeQuery($this->isTypeOfSchema, $this->isTypeOfQuery);
    }

    public function benchIsTypeOfTrusted(): void
    {
        GraphQL::executeQuery($this->isTypeOfSchema, $this->isTypeOfQuery, null, null, null, null, null, null, true);
    }
}
