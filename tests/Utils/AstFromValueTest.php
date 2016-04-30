<?php
namespace GraphQL\Tests\Utils;

use GraphQL\Language\AST\BooleanValue;
use GraphQL\Language\AST\EnumValue;
use GraphQL\Language\AST\FloatValue;
use GraphQL\Language\AST\IntValue;
use GraphQL\Language\AST\ListValue;
use GraphQL\Language\AST\Name;
use GraphQL\Language\AST\ObjectField;
use GraphQL\Language\AST\ObjectValue;
use GraphQL\Language\AST\StringValue;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\AST;

class ASTFromValueTest extends \PHPUnit_Framework_TestCase
{
    // Describe: astFromValue

    /**
     * @it converts boolean values to ASTs
     */
    public function testConvertsBooleanValueToASTs()
    {
        $this->assertEquals(new BooleanValue(['value' => true]), AST::astFromValue(true));
        $this->assertEquals(new BooleanValue(['value' => false]), AST::astFromValue(false));
    }

    /**
     * @it converts numeric values to ASTs
     */
    public function testConvertsNumericValuesToASTs()
    {
        $this->assertEquals(new IntValue(['value' => '123']), AST::astFromValue(123));
        // $this->assertEquals(new IntValue(['value' => 123]), AST::astFromValue(123.0)); // doesn't make sense for PHP because it has float and int natively unlike JS
        $this->assertEquals(new FloatValue(['value' => '123.5']), AST::astFromValue(123.5));
        $this->assertEquals(new IntValue(['value' => '10000']), AST::astFromValue(1e4));
        $this->assertEquals(new FloatValue(['value' => '1.0E+40']), AST::astFromValue(1e40)); // Note: js version will produce 1e+40, both values are valid GraphQL floats
    }

    /**
     * @it converts numeric values to Float ASTs
     */
    public function testConvertsNumericValuesToFloatASTs()
    {
        $this->assertEquals(new FloatValue(['value' => '123.0']), AST::astFromValue(123, Type::float()));
        $this->assertEquals(new FloatValue(['value' => '123.0']), AST::astFromValue(123.0, Type::float()));
        $this->assertEquals(new FloatValue(['value' => '123.5']), AST::astFromValue(123.5, Type::float()));
        $this->assertEquals(new FloatValue(['value' => '10000.0']), AST::astFromValue(1e4, Type::float()));
        $this->assertEquals(new FloatValue(['value' => '1e+40']), AST::astFromValue(1e40, Type::float()));
    }

    /**
     * @it converts string values to ASTs
     */
    public function testConvertsStringValuesToASTs()
    {
        $this->assertEquals(new StringValue(['value' => 'hello']), AST::astFromValue('hello'));
        $this->assertEquals(new StringValue(['value' => 'VALUE']), AST::astFromValue('VALUE'));
        $this->assertEquals(new StringValue(['value' => 'VA\\nLUE']), AST::astFromValue("VA\nLUE"));
        $this->assertEquals(new StringValue(['value' => '123']), AST::astFromValue('123'));
    }

    /**
     * @it converts string values to Enum ASTs if possible
     */
    public function testConvertsStringValuesToEnumASTsIfPossible()
    {
        $this->assertEquals(new EnumValue(['value' => 'hello']), AST::astFromValue('hello', $this->myEnum()));
        $this->assertEquals(new EnumValue(['value' => 'HELLO']), AST::astFromValue('HELLO', $this->myEnum()));
        $this->assertEquals(new EnumValue(['value' => 'VALUE']), AST::astFromValue('VALUE', $this->myEnum()));
        $this->assertEquals(new StringValue(['value' => 'VA\\nLUE']), AST::astFromValue("VA\nLUE", $this->myEnum()));
        $this->assertEquals(new StringValue(['value' => '123']), AST::astFromValue("123", $this->myEnum()));
    }

    /**
     * @it converts array values to List ASTs
     */
    public function testConvertsArrayValuesToListASTs()
    {
        $value1 = new ListValue([
            'values' => [
                new StringValue(['value' => 'FOO']),
                new StringValue(['value' => 'BAR'])
            ]
        ]);
        $this->assertEquals($value1, AST::astFromValue(['FOO', 'BAR']));

        $value2 = new ListValue([
            'values' => [
                new EnumValue(['value' => 'FOO']),
                new EnumValue(['value' => 'BAR']),
            ]
        ]);
        $this->assertEquals($value2, AST::astFromValue(['FOO', 'BAR'], Type::listOf($this->myEnum())));
    }

    /**
     * @it converts list singletons
     */
    public function testConvertsListSingletons()
    {
        $this->assertEquals(new EnumValue(['value' => 'FOO']), AST::astFromValue('FOO', Type::listOf($this->myEnum())));
    }

    /**
     * @it converts input objects
     */
    public function testConvertsInputObjects()
    {
        $expected = new ObjectValue([
            'fields' => [
                $this->objectField('foo', new IntValue(['value' => 3])),
                $this->objectField('bar', new StringValue(['value' => 'HELLO']))
            ]
        ]);

        $data = ['foo' => 3, 'bar' => 'HELLO'];
        $this->assertEquals($expected, AST::astFromValue($data));
        $this->assertEquals($expected, AST::astFromValue((object) $data));

        $expected = new ObjectValue([
            'fields' => [
                $this->objectField('foo', new FloatValue(['value' => '3.0'])),
                $this->objectField('bar', new EnumValue(['value' => 'HELLO'])),
            ]
        ]);
        $this->assertEquals($expected, AST::astFromValue($data, $this->inputObj()));
        $this->assertEquals($expected, AST::astFromValue((object) $data, $this->inputObj()));
    }

    /**
     * @return EnumType
     */
    private function myEnum()
    {
        return new EnumType([
            'name' => 'MyEnum',
            'values' => [
                'HELLO' => [],
                'GOODBYE' => [],
            ]
        ]);
    }

    /**
     * @return InputObjectField
     */
    private function inputObj()
    {
        return new InputObjectType([
            'name' => 'MyInputObj',
            'fields' => [
                'foo' => ['type' => Type::float()],
                'bar' => ['type' => $this->myEnum()]
            ]
        ]);
    }

    /**
     * @param $name
     * @param $value
     * @return ObjectField
     */
    private function objectField($name, $value)
    {
        return new ObjectField([
            'name' => new Name(['value' => $name]),
            'value' => $value
        ]);
    }
}
