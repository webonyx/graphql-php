<?php
namespace GraphQL\Tests\Type;

use GraphQL\Type\Definition\Type;

class ScalarSerializationTest extends \PHPUnit_Framework_TestCase
{
    // Type System: Scalar coercion

    /**
     * @it serializes output int
     */
    public function testSerializesOutputInt()
    {
        $intType = Type::int();

        $this->assertSame(1, $intType->serialize(1));
        $this->assertSame(0, $intType->serialize(0));
        $this->assertSame(-1, $intType->serialize(-1));
        $this->assertSame(0, $intType->serialize(0.1));
        $this->assertSame(1, $intType->serialize(1.1));
        $this->assertSame(-1, $intType->serialize(-1.1));
        $this->assertSame(100000, $intType->serialize(1e5));
        // Maybe a safe PHP int, but bigger than 2^32, so not
        // representable as a GraphQL Int
        $this->assertSame(null, $intType->serialize(9876504321));
        $this->assertSame(null, $intType->serialize(-9876504321));
        $this->assertSame(null, $intType->serialize(1e100));
        $this->assertSame(null, $intType->serialize(-1e100));
        $this->assertSame(-1, $intType->serialize('-1.1'));
        $this->assertSame(null, $intType->serialize('one'));
        $this->assertSame(0, $intType->serialize(false));
        $this->assertSame(1, $intType->serialize(true));
    }

    /**
     * @it serializes output float
     */
    public function testSerializesOutputFloat()
    {
        $floatType = Type::float();

        $this->assertSame(1.0, $floatType->serialize(1));
        $this->assertSame(0.0, $floatType->serialize(0));
        $this->assertSame(-1.0, $floatType->serialize(-1));
        $this->assertSame(0.1, $floatType->serialize(0.1));
        $this->assertSame(1.1, $floatType->serialize(1.1));
        $this->assertSame(-1.1, $floatType->serialize(-1.1));
        $this->assertSame(-1.1, $floatType->serialize('-1.1'));
        $this->assertSame(null, $floatType->serialize('one'));
        $this->assertSame(0.0, $floatType->serialize(false));
        $this->assertSame(1.0, $floatType->serialize(true));
    }

    /**
     * @it serializes output strings
     */
    public function testSerializesOutputStrings()
    {
        $stringType = Type::string();

        $this->assertSame('string', $stringType->serialize('string'));
        $this->assertSame('1', $stringType->serialize(1));
        $this->assertSame('-1.1', $stringType->serialize(-1.1));
        $this->assertSame('true', $stringType->serialize(true));
        $this->assertSame('false', $stringType->serialize(false));
    }

    /**
     * @it serializes output boolean
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

        // TODO: how should it behaive on '0'?
    }
}
