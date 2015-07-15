<?php
namespace GraphQL\Type;

use GraphQL\Type\Definition\Type;

class ScalarCoercionTest extends \PHPUnit_Framework_TestCase
{
    public function testCoercesOutputInt()
    {
        $intType = Type::int();

        $this->assertSame(1, $intType->coerce(1));
        $this->assertSame(0, $intType->coerce(0));
        $this->assertSame(0, $intType->coerce(0.1));
        $this->assertSame(1, $intType->coerce(1.1));
        $this->assertSame(-1, $intType->coerce(-1.1));
        $this->assertSame(100000, $intType->coerce(1e5));
        $this->assertSame(null, $intType->coerce(1e100));
        $this->assertSame(null, $intType->coerce(-1e100));
        $this->assertSame(-1, $intType->coerce('-1.1'));
        $this->assertSame(null, $intType->coerce('one'));
        $this->assertSame(0, $intType->coerce(false));
    }

    public function testCoercesOutputFloat()
    {
        $floatType = Type::float();

        $this->assertSame(1.0, $floatType->coerce(1));
        $this->assertSame(-1.0, $floatType->coerce(-1));
        $this->assertSame(0.1, $floatType->coerce(0.1));
        $this->assertSame(1.1, $floatType->coerce(1.1));
        $this->assertSame(-1.1, $floatType->coerce(-1.1));
        $this->assertSame(null, $floatType->coerce('one'));
        $this->assertSame(0.0, $floatType->coerce(false));
        $this->assertSame(1.0, $floatType->coerce(true));
    }

    public function testCoercesOutputStrings()
    {
        $stringType = Type::string();

        $this->assertSame('string', $stringType->coerce('string'));
        $this->assertSame('1', $stringType->coerce(1));
        $this->assertSame('-1.1', $stringType->coerce(-1.1));
        $this->assertSame('true', $stringType->coerce(true));
        $this->assertSame('false', $stringType->coerce(false));
    }

    public function testCoercesOutputBoolean()
    {
        $boolType = Type::boolean();

        $this->assertSame(true, $boolType->coerce('string'));
        $this->assertSame(false, $boolType->coerce(''));
        $this->assertSame(true, $boolType->coerce(1));
        $this->assertSame(false, $boolType->coerce(0));
        $this->assertSame(true, $boolType->coerce(true));
        $this->assertSame(false, $boolType->coerce(false));

        // TODO: how should it behaive on '0'?
    }
}