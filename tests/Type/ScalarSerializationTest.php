<?php

declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\IDType;
use GraphQL\Type\Definition\StringType;
use GraphQL\Type\Definition\Type;
use PHPUnit\Framework\TestCase;
use stdClass;
use function sprintf;

class ScalarSerializationTest extends TestCase
{
    // Type System: Scalar coercion
    /**
     * @see it('serializes output as Int')
     */
    public function testSerializesOutputAsInt() : void
    {
        $intType = Type::int();

        self::assertSame(1, $intType->serialize(1));
        self::assertSame(123, $intType->serialize('123'));
        self::assertSame(0, $intType->serialize(0));
        self::assertSame(-1, $intType->serialize(-1));
        self::assertSame(100000, $intType->serialize(1e5));
        self::assertSame(0, $intType->serialize(0e5));
        self::assertSame(0, $intType->serialize(false));
        self::assertSame(1, $intType->serialize(true));
    }

    public function testSerializesOutputIntCannotRepresentFloat1() : void
    {
        // The GraphQL specification does not allow serializing non-integer values
        // as Int to avoid accidental data loss.
        $intType = Type::int();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Int cannot represent non-integer value: 0.1');
        $intType->serialize(0.1);
    }

    public function testSerializesOutputIntCannotRepresentFloat2() : void
    {
        $intType = Type::int();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Int cannot represent non-integer value: 1.1');
        $intType->serialize(1.1);
    }

    public function testSerializesOutputIntCannotRepresentNegativeFloat() : void
    {
        $intType = Type::int();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Int cannot represent non-integer value: -1.1');
        $intType->serialize(-1.1);
    }

    public function testSerializesOutputIntCannotRepresentNumericString() : void
    {
        $intType = Type::int();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Int cannot represent non 32-bit signed integer value: Int cannot represent non-integer value: "-1.1"');
        $intType->serialize('Int cannot represent non-integer value: "-1.1"');
    }

    public function testSerializesOutputIntCannotRepresentBiggerThan32Bits() : void
    {
        // Maybe a safe PHP int, but bigger than 2^32, so not
        // representable as a GraphQL Int
        $intType = Type::int();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Int cannot represent non 32-bit signed integer value: 9876504321');
        $intType->serialize(9876504321);
    }

    public function testSerializesOutputIntCannotRepresentLowerThan32Bits() : void
    {
        $intType = Type::int();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Int cannot represent non 32-bit signed integer value: -9876504321');
        $intType->serialize(-9876504321);
    }

    public function testSerializesOutputIntCannotRepresentBiggerThanSigned32Bits() : void
    {
        $intType = Type::int();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Int cannot represent non 32-bit signed integer value: 1.0E+100');
        $intType->serialize(1e100);
    }

    public function testSerializesOutputIntCannotRepresentLowerThanSigned32Bits() : void
    {
        $intType = Type::int();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Int cannot represent non 32-bit signed integer value: -1.0E+100');
        $intType->serialize(-1e100);
    }

    public function testSerializesOutputIntCannotRepresentString() : void
    {
        $intType = Type::int();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Int cannot represent non 32-bit signed integer value: one');
        $intType->serialize('one');
    }

    public function testSerializesOutputIntCannotRepresentEmptyString() : void
    {
        $intType = Type::int();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Int cannot represent non 32-bit signed integer value: (empty string)');
        $intType->serialize('');
    }

    public function testSerializesOutputIntCannotRepresentArray() : void
    {
        $intType = Type::int();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Int cannot represent an array value: [5]');
        $intType->serialize([5]);
    }

    /**
     * @see it('serializes output as Float')
     */
    public function testSerializesOutputAsFloat() : void
    {
        $floatType = Type::float();

        self::assertSame(1.0, $floatType->serialize(1));
        self::assertSame(0.0, $floatType->serialize(0));
        self::assertSame(123.5, $floatType->serialize('123.5'));
        self::assertSame(-1.0, $floatType->serialize(-1));
        self::assertSame(0.1, $floatType->serialize(0.1));
        self::assertSame(1.1, $floatType->serialize(1.1));
        self::assertSame(-1.1, $floatType->serialize(-1.1));
        self::assertSame(-1.1, $floatType->serialize('-1.1'));
        self::assertSame(0.0, $floatType->serialize(false));
        self::assertSame(1.0, $floatType->serialize(true));
    }

    public function testSerializesOutputFloatCannotRepresentString() : void
    {
        $floatType = Type::float();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Float cannot represent non numeric value: one');
        $floatType->serialize('one');
    }

    public function testSerializesOutputFloatCannotRepresentEmptyString() : void
    {
        $floatType = Type::float();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Float cannot represent non numeric value: (empty string)');
        $floatType->serialize('');
    }

    public function testSerializesOutputFloatCannotRepresentArray() : void
    {
        $floatType = Type::float();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Float cannot represent an array value: [5]');
        $floatType->serialize([5]);
    }

    public function stringLikeTypes()
    {
        return [
            [ Type::string() ],
            [ Type::id() ],
        ];
    }

    /**
     * @see it('serializes output as String')
     *
     * @param StringType|IDType $stringType
     *
     * @dataProvider stringLikeTypes
     */
    public function testSerializesOutputAsString($stringType) : void
    {
        self::assertSame('string', $stringType->serialize('string'));
        self::assertSame('1', $stringType->serialize(1));
        self::assertSame('-1.1', $stringType->serialize(-1.1));
        self::assertSame('true', $stringType->serialize(true));
        self::assertSame('false', $stringType->serialize(false));
        self::assertSame('null', $stringType->serialize(null));
        self::assertSame('2', $stringType->serialize(new ObjectIdStub(2)));
    }

    /**
     * @param StringType|IDType $stringType
     *
     * @throws Error
     *
     * @dataProvider stringLikeTypes
     */
    public function testSerializesOutputStringsCannotRepresentArray($stringType) : void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage(sprintf('%s cannot represent an array value: [1]', $stringType->name));
        $stringType->serialize([1]);
    }

    /**
     * @param StringType|IDType $stringType
     *
     * @dataProvider stringLikeTypes
     */
    public function testSerializesOutputStringsCannotRepresentObject($stringType) : void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage(sprintf('%s cannot represent non scalar value: instance of stdClass', $stringType->name));
        $stringType->serialize(new stdClass());
    }

    /**
     * @param StringType|IDType $stringType
     *
     * @throws Error
     *
     * @dataProvider stringLikeTypes
     */
    public function testSerializesOutputStringCannotRepresentArray($stringType) : void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage(sprintf('%s cannot represent an array value: [5]', $stringType->name));
        $stringType->serialize([5]);
    }

    /**
     * @see it('serializes output as Boolean')
     */
    public function testSerializesOutputAsBoolean() : void
    {
        $boolType = Type::boolean();

        self::assertTrue($boolType->serialize(true));
        self::assertTrue($boolType->serialize(1));
        self::assertTrue($boolType->serialize('1'));
        self::assertTrue($boolType->serialize('string'));

        self::assertFalse($boolType->serialize(false));
        self::assertFalse($boolType->serialize(0));
        self::assertFalse($boolType->serialize('0'));
        self::assertFalse($boolType->serialize(''));
    }

    public function testSerializesOutputBooleanCannotRepresentArray() : void
    {
        $boolType = Type::boolean();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Boolean cannot represent an array value: [5]');
        $boolType->serialize([5]);
    }
}
