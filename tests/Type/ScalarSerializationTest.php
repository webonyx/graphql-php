<?php

declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\Type;
use PHPUnit\Framework\TestCase;
use stdClass;

class ScalarSerializationTest extends TestCase
{
    // Type System: Scalar coercion
    /**
     * @see it('serializes output int')
     */
    public function testSerializesOutputInt() : void
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

    /**
     * @see it('serializes output float')
     */
    public function testSerializesOutputFloat() : void
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

    /**
     * @see it('serializes output strings')
     */
    public function testSerializesOutputStrings() : void
    {
        $stringType = Type::string();

        self::assertSame('string', $stringType->serialize('string'));
        self::assertSame('1', $stringType->serialize(1));
        self::assertSame('-1.1', $stringType->serialize(-1.1));
        self::assertSame('true', $stringType->serialize(true));
        self::assertSame('false', $stringType->serialize(false));
        self::assertSame('null', $stringType->serialize(null));
        self::assertSame('2', $stringType->serialize(new ObjectIdStub(2)));
    }

    public function testSerializesOutputStringsCannotRepresentArray() : void
    {
        $stringType = Type::string();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('String cannot represent non scalar value: []');
        $stringType->serialize([]);
    }

    public function testSerializesOutputStringsCannotRepresentObject() : void
    {
        $stringType = Type::string();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('String cannot represent non scalar value: instance of stdClass');
        $stringType->serialize(new stdClass());
    }

    /**
     * @see it('serializes output boolean')
     */
    public function testSerializesOutputBoolean() : void
    {
        $boolType = Type::boolean();

        self::assertTrue($boolType->serialize('string'));
        self::assertFalse($boolType->serialize(''));
        self::assertTrue($boolType->serialize('1'));
        self::assertTrue($boolType->serialize(1));
        self::assertFalse($boolType->serialize(0));
        self::assertTrue($boolType->serialize(true));
        self::assertFalse($boolType->serialize(false));
        // TODO: how should it behave on '0'?
    }

    public function testSerializesOutputID() : void
    {
        $idType = Type::id();

        self::assertSame('string', $idType->serialize('string'));
        self::assertSame('', $idType->serialize(''));
        self::assertSame('1', $idType->serialize('1'));
        self::assertSame('1', $idType->serialize(1));
        self::assertSame('0', $idType->serialize(0));
        self::assertSame('true', $idType->serialize(true));
        self::assertSame('false', $idType->serialize(false));
        self::assertSame('2', $idType->serialize(new ObjectIdStub(2)));
    }

    public function testSerializesOutputIDCannotRepresentObject() : void
    {
        $idType = Type::id();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('ID type cannot represent non scalar value: instance of stdClass');
        $idType->serialize(new stdClass());
    }
}
