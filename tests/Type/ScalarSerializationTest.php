<?php
namespace GraphQL\Tests\Type;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\Type;
use PHPUnit\Framework\TestCase;

class ScalarSerializationTest extends TestCase
{
    // Type System: Scalar coercion

    /**
     * @see it('serializes output int')
     */
    public function testSerializesOutputInt()
    {
        $intType = Type::int();

        $this->assertSame(1, $intType->serialize(1));
        $this->assertSame(123, $intType->serialize('123'));
        $this->assertSame(0, $intType->serialize(0));
        $this->assertSame(-1, $intType->serialize(-1));
        $this->assertSame(100000, $intType->serialize(1e5));
        $this->assertSame(0, $intType->serialize(0e5));
        $this->assertSame(0, $intType->serialize(false));
        $this->assertSame(1, $intType->serialize(true));
    }

    public function testSerializesOutputIntCannotRepresentFloat1()
    {
        // The GraphQL specification does not allow serializing non-integer values
        // as Int to avoid accidental data loss.
        $intType = Type::int();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Int cannot represent non-integer value: 0.1');
        $intType->serialize(0.1);
    }

    public function testSerializesOutputIntCannotRepresentFloat2()
    {
        $intType = Type::int();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Int cannot represent non-integer value: 1.1');
        $intType->serialize(1.1);

    }

    public function testSerializesOutputIntCannotRepresentNegativeFloat()
    {
        $intType = Type::int();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Int cannot represent non-integer value: -1.1');
        $intType->serialize(-1.1);

    }

    public function testSerializesOutputIntCannotRepresentNumericString()
    {
        $intType = Type::int();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Int cannot represent non 32-bit signed integer value: Int cannot represent non-integer value: "-1.1"');
        $intType->serialize('Int cannot represent non-integer value: "-1.1"');

    }

    public function testSerializesOutputIntCannotRepresentBiggerThan32Bits()
    {
        // Maybe a safe PHP int, but bigger than 2^32, so not
        // representable as a GraphQL Int
        $intType = Type::int();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Int cannot represent non 32-bit signed integer value: 9876504321');
        $intType->serialize(9876504321);

    }

    public function testSerializesOutputIntCannotRepresentLowerThan32Bits()
    {
        $intType = Type::int();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Int cannot represent non 32-bit signed integer value: -9876504321');
        $intType->serialize(-9876504321);
    }

    public function testSerializesOutputIntCannotRepresentBiggerThanSigned32Bits()
    {
        $intType = Type::int();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Int cannot represent non 32-bit signed integer value: 1.0E+100');
        $intType->serialize(1e100);
    }

    public function testSerializesOutputIntCannotRepresentLowerThanSigned32Bits()
    {
        $intType = Type::int();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Int cannot represent non 32-bit signed integer value: -1.0E+100');
        $intType->serialize(-1e100);
    }

    public function testSerializesOutputIntCannotRepresentString()
    {
        $intType = Type::int();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Int cannot represent non 32-bit signed integer value: one');
        $intType->serialize('one');

    }

    public function testSerializesOutputIntCannotRepresentEmptyString()
    {
        $intType = Type::int();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Int cannot represent non 32-bit signed integer value: (empty string)');
        $intType->serialize('');
    }

    /**
     * @see it('serializes output float')
     */
    public function testSerializesOutputFloat()
    {
        $floatType = Type::float();

        $this->assertSame(1.0, $floatType->serialize(1));
        $this->assertSame(0.0, $floatType->serialize(0));
        $this->assertSame(123.5, $floatType->serialize('123.5'));
        $this->assertSame(-1.0, $floatType->serialize(-1));
        $this->assertSame(0.1, $floatType->serialize(0.1));
        $this->assertSame(1.1, $floatType->serialize(1.1));
        $this->assertSame(-1.1, $floatType->serialize(-1.1));
        $this->assertSame(-1.1, $floatType->serialize('-1.1'));
        $this->assertSame(0.0, $floatType->serialize(false));
        $this->assertSame(1.0, $floatType->serialize(true));
    }

    public function testSerializesOutputFloatCannotRepresentString()
    {
        $floatType = Type::float();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Float cannot represent non numeric value: one');
        $floatType->serialize('one');
    }

    public function testSerializesOutputFloatCannotRepresentEmptyString()
    {
        $floatType = Type::float();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Float cannot represent non numeric value: (empty string)');
        $floatType->serialize('');
    }

    /**
     * @see it('serializes output strings')
     */
    public function testSerializesOutputStrings()
    {
        $stringType = Type::string();

        $this->assertSame('string', $stringType->serialize('string'));
        $this->assertSame('1', $stringType->serialize(1));
        $this->assertSame('-1.1', $stringType->serialize(-1.1));
        $this->assertSame('true', $stringType->serialize(true));
        $this->assertSame('false', $stringType->serialize(false));
        $this->assertSame('null', $stringType->serialize(null));
        $this->assertSame('2', $stringType->serialize(new ObjectIdStub(2)));
    }

    public function testSerializesOutputStringsCannotRepresentArray()
    {
        $stringType = Type::string();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('String cannot represent non scalar value: []');
        $stringType->serialize([]);
    }

    public function testSerializesOutputStringsCannotRepresentObject()
    {
        $stringType = Type::string();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('String cannot represent non scalar value: instance of stdClass');
        $stringType->serialize(new \stdClass());
    }

    /**
     * @see it('serializes output boolean')
     */
    public function testSerializesOutputBoolean()
    {
        $boolType = Type::boolean();

        $this->assertSame(true, $boolType->serialize('string'));
        $this->assertSame(false, $boolType->serialize(''));
        $this->assertSame(true, $boolType->serialize('1'));
        $this->assertSame(true, $boolType->serialize(1));
        $this->assertSame(false, $boolType->serialize(0));
        $this->assertSame(true, $boolType->serialize(true));
        $this->assertSame(false, $boolType->serialize(false));

        // TODO: how should it behave on '0'?
    }

    public function testSerializesOutputID()
    {
        $idType = Type::id();

        $this->assertSame('string', $idType->serialize('string'));
        $this->assertSame('', $idType->serialize(''));
        $this->assertSame('1', $idType->serialize('1'));
        $this->assertSame('1', $idType->serialize(1));
        $this->assertSame('0', $idType->serialize(0));
        $this->assertSame('true', $idType->serialize(true));
        $this->assertSame('false', $idType->serialize(false));
        $this->assertSame('2', $idType->serialize(new ObjectIdStub(2)));
    }

    public function testSerializesOutputIDCannotRepresentObject()
    {
        $idType = Type::id();
        $this->expectException(Error::class);
        $this->expectExceptionMessage('ID type cannot represent non scalar value: instance of stdClass');
        $idType->serialize(new \stdClass());
    }
}
