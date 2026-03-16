<?php declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\TypeComparators;
use PHPUnit\Framework\TestCase;

final class TypeComparatorsTest extends TestCase
{
    public function testIsEqualTypeWithDifferentInstancesOfSameNamedType(): void
    {
        $customString = new CustomScalarType(['name' => Type::STRING]);

        self::assertTrue(TypeComparators::isEqualType(Type::string(), $customString));
        self::assertTrue(TypeComparators::isEqualType($customString, Type::string()));
    }

    public function testIsEqualTypeWithWrappedDifferentInstances(): void
    {
        $customString = new CustomScalarType(['name' => Type::STRING]);

        self::assertTrue(TypeComparators::isEqualType(Type::nonNull(Type::string()), Type::nonNull($customString)));
        self::assertTrue(TypeComparators::isEqualType(Type::listOf(Type::string()), Type::listOf($customString)));
        self::assertTrue(TypeComparators::isEqualType(
            Type::nonNull(Type::listOf(Type::string())),
            Type::nonNull(Type::listOf($customString)),
        ));
    }

    public function testIsTypeSubTypeOfWithDifferentInstancesOfSameNamedType(): void
    {
        $schema = $this->createSchemaWithCustomString();
        $customString = new CustomScalarType(['name' => Type::STRING]);

        self::assertTrue(TypeComparators::isTypeSubTypeOf($schema, $customString, Type::string()));
        self::assertTrue(TypeComparators::isTypeSubTypeOf($schema, Type::string(), $customString));
    }

    public function testIsTypeSubTypeOfWithWrappedDifferentInstances(): void
    {
        $schema = $this->createSchemaWithCustomString();
        $customString = new CustomScalarType(['name' => Type::STRING]);

        self::assertTrue(TypeComparators::isTypeSubTypeOf($schema, Type::nonNull($customString), Type::string()));
        self::assertTrue(TypeComparators::isTypeSubTypeOf($schema, Type::nonNull($customString), Type::nonNull(Type::string())));
        self::assertTrue(TypeComparators::isTypeSubTypeOf(
            $schema,
            Type::nonNull(Type::listOf(Type::nonNull($customString))),
            Type::listOf(Type::nonNull(Type::string())),
        ));
    }

    /** @throws InvariantViolation */
    private function createSchemaWithCustomString(): Schema
    {
        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'greeting' => [
                    'type' => Type::string(),
                    'resolve' => static fn (): string => 'hello',
                ],
            ],
        ]);

        return new Schema([
            'query' => $queryType,
            'typeLoader' => static fn (string $name): ?Type => ['Query' => $queryType][$name] ?? null,
        ]);
    }
}
