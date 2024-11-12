<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Introspection;
use PHPUnit\Framework\TestCase;

final class StandardTypesTest extends TestCase
{
    public function tearDown(): void
    {
        parent::tearDown();
        Type::reset();
    }

    public function testAllowsOverridingStandardTypes(): void
    {
        $originalTypes = Type::getStandardTypes();
        self::assertCount(5, $originalTypes);

        $newBooleanType = self::createCustomScalarType(Type::BOOLEAN);
        $newFloatType = self::createCustomScalarType(Type::FLOAT);
        $newIDType = self::createCustomScalarType(Type::ID);
        $newIntType = self::createCustomScalarType(Type::INT);
        $newStringType = self::createCustomScalarType(Type::STRING);

        Type::overrideStandardTypes([
            $newBooleanType,
            $newFloatType,
            $newIDType,
            $newIntType,
            $newStringType,
        ]);

        $types = Type::getStandardTypes();
        self::assertCount(5, $types);

        self::assertSame($newBooleanType, $types[Type::BOOLEAN]);
        self::assertSame($newFloatType, $types[Type::FLOAT]);
        self::assertSame($newIDType, $types[Type::ID]);
        self::assertSame($newIntType, $types[Type::INT]);
        self::assertSame($newStringType, $types[Type::STRING]);

        self::assertSame($newBooleanType, Type::boolean());
        self::assertSame($newFloatType, Type::float());
        self::assertSame($newIDType, Type::id());
        self::assertSame($newIntType, Type::int());
        self::assertSame($newStringType, Type::string());
    }

    public function testPreservesOriginalStandardTypes(): void
    {
        $originalTypes = Type::getStandardTypes();
        self::assertCount(5, $originalTypes);

        $newIDType = self::createCustomScalarType(Type::ID);
        $newStringType = self::createCustomScalarType(Type::STRING);

        Type::overrideStandardTypes([
            $newStringType,
            $newIDType,
        ]);

        $types = Type::getStandardTypes();
        self::assertCount(5, $types);

        self::assertSame($originalTypes[Type::BOOLEAN], $types[Type::BOOLEAN]);
        self::assertSame($originalTypes[Type::FLOAT], $types[Type::FLOAT]);
        self::assertSame($originalTypes[Type::INT], $types[Type::INT]);

        self::assertSame($originalTypes[Type::BOOLEAN], Type::boolean());
        self::assertSame($originalTypes[Type::FLOAT], Type::float());
        self::assertSame($originalTypes[Type::INT], Type::int());

        self::assertSame($newIDType, $types[Type::ID]);
        self::assertSame($newStringType, $types[Type::STRING]);

        self::assertSame($newIDType, Type::id());
        self::assertSame($newStringType, Type::string());
    }

    /**
     * @throws InvariantViolation
     *
     * @return iterable<array{mixed, string}>
     */
    public static function invalidStandardTypes(): iterable
    {
        yield [null, 'Expecting instance of GraphQL\Type\Definition\ScalarType, got null'];
        yield [5, 'Expecting instance of GraphQL\Type\Definition\ScalarType, got 5'];
        yield ['', 'Expecting instance of GraphQL\Type\Definition\ScalarType, got (empty string)'];
        yield [new \stdClass(), 'Expecting instance of GraphQL\Type\Definition\ScalarType, got instance of stdClass'];
        yield [[], 'Expecting instance of GraphQL\Type\Definition\ScalarType, got []'];
        yield [new ObjectType(['name' => 'ID', 'fields' => []]), 'Expecting instance of GraphQL\Type\Definition\ScalarType, got ID'];
        yield [self::createCustomScalarType('NonStandardName'), 'Expecting one of the following names for a standard type: Int, Float, String, Boolean, ID; got "NonStandardName"'];
    }

    /**
     * @param mixed $notType invalid type
     *
     * @dataProvider invalidStandardTypes
     */
    public function testStandardTypesOverrideDoesSanityChecks($notType, string $expectedMessage): void
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage($expectedMessage);

        Type::overrideStandardTypes([$notType]);
    }

    public function testCachesShouldResetWhenOverridingStandardTypes(): void
    {
        $string = Type::string();
        $schema = Introspection::_schema();
        $directives = Directive::getInternalDirectives();

        Type::overrideStandardTypes([
            $newString = self::createCustomScalarType(Type::STRING),
        ]);

        self::assertNotSame($string, Type::string());
        self::assertSame($newString, Type::string());
        self::assertNotSame($schema, Introspection::_schema());
        self::assertNotSame($directives, Directive::getInternalDirectives());
    }

    /** @throws InvariantViolation */
    private static function createCustomScalarType(string $name): CustomScalarType
    {
        return new CustomScalarType([
            'name' => $name,
            'serialize' => static fn () => null,
            'parseValue' => static fn () => null,
            'parseLiteral' => static fn () => null,
        ]);
    }
}
