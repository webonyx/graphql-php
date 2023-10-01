<?php declare(strict_types=1);

namespace GraphQL\Tests;

use GraphQL\Error\InvariantViolation;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Registry\DefaultStandardTypeRegistry;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use PHPUnit\Framework\TestCase;

final class MultipleSchemaTest extends TestCase
{
    public function testMultipleSchemasWithCustomIntType(): void
    {
        $schema1 = $this->createSchema();
        $result1 = GraphQL::executeQuery($schema1, '{ count }');
        self::assertSame(['data' => ['count' => 1]], $result1->toArray());

        $schema2 = $this->createSchema();
        $result2 = GraphQL::executeQuery($schema2, '{ count }');
        self::assertSame(['data' => ['count' => 1]], $result2->toArray());

        self::assertNotSame($schema1->getType('Int'), $schema2->getType('Int'));
    }

    /** @throws InvariantViolation */
    private function createSchema(): Schema
    {
        $typeRegistry = new DefaultStandardTypeRegistry(
            CustomIntType::class
        );

        $query = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'count' => [
                    'type' => Type::nonNull($typeRegistry->int()),
                    'resolve' => fn () => 1,
                ],
            ],
        ]);

        $config = SchemaConfig::create([
            'typeRegistry' => $typeRegistry,
            'query' => $query,
        ]);

        return new Schema($config);
    }
}
