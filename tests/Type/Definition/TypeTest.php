<?php declare(strict_types=1);

namespace GraphQL\Tests\Type\Definition;

use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use PHPUnit\Framework\TestCase;

final class TypeTest extends TestCase
{
    public function testWrappingNonNullableTypeWithNonNull(): void
    {
        $nonNullableString = Type::nonNull(Type::string());

        self::assertSame($nonNullableString, Type::nonNull($nonNullableString));
    }

    public function testIsBuiltInScalarReturnsTrueForBuiltInScalars(): void
    {
        self::assertTrue(Type::isBuiltInScalar(Type::int())); // @phpstan-ignore staticMethod.alreadyNarrowedType
        self::assertTrue(Type::isBuiltInScalar(Type::float())); // @phpstan-ignore staticMethod.alreadyNarrowedType
        self::assertTrue(Type::isBuiltInScalar(Type::string())); // @phpstan-ignore staticMethod.alreadyNarrowedType
        self::assertTrue(Type::isBuiltInScalar(Type::boolean())); // @phpstan-ignore staticMethod.alreadyNarrowedType
        self::assertTrue(Type::isBuiltInScalar(Type::id())); // @phpstan-ignore staticMethod.alreadyNarrowedType
    }

    public function testIsBuiltInScalarReturnsTrueForCustomScalarWithBuiltInName(): void
    {
        self::assertTrue(Type::isBuiltInScalar(new CustomScalarType(['name' => Type::STRING]))); // @phpstan-ignore staticMethod.alreadyNarrowedType
        self::assertTrue(Type::isBuiltInScalar(new CustomScalarType(['name' => Type::ID]))); // @phpstan-ignore staticMethod.alreadyNarrowedType
    }

    public function testIsBuiltInScalarReturnsFalseForNonScalarTypes(): void
    {
        self::assertFalse(Type::isBuiltInScalar(new ObjectType(['name' => 'Obj', 'fields' => []]))); // @phpstan-ignore staticMethod.impossibleType
        self::assertFalse(Type::isBuiltInScalar(new EnumType(['name' => 'E', 'values' => ['A']]))); // @phpstan-ignore staticMethod.impossibleType
    }

    public function testIsBuiltInScalarReturnsFalseForWrappedTypes(): void
    {
        self::assertFalse(Type::isBuiltInScalar(Type::nonNull(Type::string()))); // @phpstan-ignore staticMethod.impossibleType
        self::assertFalse(Type::isBuiltInScalar(Type::listOf(Type::string()))); // @phpstan-ignore staticMethod.impossibleType
    }

    public function testIsBuiltInScalarReturnsFalseForCustomScalarWithNonBuiltInName(): void
    {
        self::assertFalse(Type::isBuiltInScalar(new CustomScalarType(['name' => 'MyScalar']))); // @phpstan-ignore staticMethod.alreadyNarrowedType
    }
}
