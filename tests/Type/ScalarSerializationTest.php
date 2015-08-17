<?php
namespace GraphQL\Type;

use GraphQL\Type\Definition\Type;

class ScalarSerializationTest extends \PHPUnit_Framework_TestCase
{
    public function testCoercesOutputInt()
    {
        $intType = Type::int();

        $this->assertSame(1, $intType->serialize(1));
        $this->assertSame(0, $intType->serialize(0));
        $this->assertSame(0, $intType->serialize(0.1));
        $this->assertSame(1, $intType->serialize(1.1));
        $this->assertSame(-1, $intType->serialize(-1.1));
        $this->assertSame(100000, $intType->serialize(1e5));
        $this->assertSame(null, $intType->serialize(1e100));
        $this->assertSame(null, $intType->serialize(-1e100));
        $this->assertSame(-1, $intType->serialize('-1.1'));
        $this->assertSame(null, $intType->serialize('one'));
        $this->assertSame(0, $intType->serialize(false));
    }

    public function testCoercesOutputFloat()
    {
        $floatType = Type::float();

        $this->assertSame(1.0, $floatType->serialize(1));
        $this->assertSame(-1.0, $floatType->serialize(-1));
        $this->assertSame(0.1, $floatType->serialize(0.1));
        $this->assertSame(1.1, $floatType->serialize(1.1));
        $this->assertSame(-1.1, $floatType->serialize(-1.1));
        $this->assertSame(null, $floatType->serialize('one'));
        $this->assertSame(0.0, $floatType->serialize(false));
        $this->assertSame(1.0, $floatType->serialize(true));
    }

    public function testCoercesOutputStrings()
    {
        $stringType = Type::string();

        $this->assertSame('string', $stringType->serialize('string'));
        $this->assertSame('1', $stringType->serialize(1));
        $this->assertSame('-1.1', $stringType->serialize(-1.1));
        $this->assertSame('true', $stringType->serialize(true));
        $this->assertSame('false', $stringType->serialize(false));
    }

    public function testCoercesOutputBoolean()
    {
        $boolType = Type::boolean();

        $this->assertSame(true, $boolType->serialize('string'));
        $this->assertSame(false, $boolType->serialize(''));
        $this->assertSame(true, $boolType->serialize(1));
        $this->assertSame(false, $boolType->serialize(0));
        $this->assertSame(true, $boolType->serialize(true));
        $this->assertSame(false, $boolType->serialize(false));

        // TODO: how should it behaive on '0'?
    }
}