<?php

declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\Type;
use PHPUnit\Framework\TestCase;
use stdClass;

class StandardTypesTest extends TestCase
{
    /** @var Type[] */
    private static $originalStandardTypes;

    public static function setUpBeforeClass()
    {
        self::$originalStandardTypes = Type::getStandardTypes();
    }

    public function tearDown()
    {
        parent::tearDown();
        Type::overrideStandardTypes(self::$originalStandardTypes);
    }

    public function testAllowsOverridingStandardTypes()
    {
        $originalTypes = Type::getStandardTypes();
        self::assertCount(5, $originalTypes);
        self::assertSame(self::$originalStandardTypes, $originalTypes);

        $newBooleanType = $this->createCustomScalarType(Type::BOOLEAN);
        $newFloatType   = $this->createCustomScalarType(Type::FLOAT);
        $newIDType      = $this->createCustomScalarType(Type::ID);
        $newIntType     = $this->createCustomScalarType(Type::INT);
        $newStringType  = $this->createCustomScalarType(Type::STRING);

        Type::overrideStandardTypes([
            $newStringType,
            $newBooleanType,
            $newIDType,
            $newIntType,
            $newFloatType,
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

    public function testPreservesOriginalStandardTypes()
    {
        $originalTypes = Type::getStandardTypes();
        self::assertCount(5, $originalTypes);
        self::assertSame(self::$originalStandardTypes, $originalTypes);

        $newIDType     = $this->createCustomScalarType(Type::ID);
        $newStringType = $this->createCustomScalarType(Type::STRING);

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

    public function getInvalidStandardTypes()
    {
        return [
            [null, 'Expecting instance of GraphQL\Type\Definition\Type, got null'],
            [5, 'Expecting instance of GraphQL\Type\Definition\Type, got 5'],
            ['', 'Expecting instance of GraphQL\Type\Definition\Type, got (empty string)'],
            [new stdClass(), 'Expecting instance of GraphQL\Type\Definition\Type, got instance of stdClass'],
            [[], 'Expecting instance of GraphQL\Type\Definition\Type, got []'],
            [$this->createCustomScalarType('NonStandardName'), 'Expecting one of the following names for a standard type: ID, String, Float, Int, Boolean, got NonStandardName'],
        ];
    }

    /**
     * @dataProvider getInvalidStandardTypes
     */
    public function testStandardTypesOverrideDoesSanityChecks($type, string $expectedMessage)
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage($expectedMessage);

        Type::overrideStandardTypes([ $type ]);
    }

    private function createCustomScalarType($name)
    {
        return new CustomScalarType([
            'name' => $name,
            'serialize' => static function () {
            },
            'parseValue' => static function () {
            },
            'parseLiteral' => static function () {
            },
        ]);
    }
}
