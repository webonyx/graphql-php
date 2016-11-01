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
        $this->assertEquals(new BooleanValue(['value' => true]), AST::astFromValue(true, Type::boolean()));
        $this->assertEquals(new BooleanValue(['value' => false]), AST::astFromValue(false, Type::boolean()));
        $this->assertEquals(null, AST::astFromValue(null, Type::boolean()));
        $this->assertEquals(new BooleanValue(['value' => false]), AST::astFromValue(0, Type::boolean()));
        $this->assertEquals(new BooleanValue(['value' => true]), AST::astFromValue(1, Type::boolean()));
    }

    /**
     * @it converts Int values to Int ASTs
     */
    public function testConvertsIntValuesToASTs()
    {
        $this->assertEquals(new IntValue(['value' => '123']), AST::astFromValue(123.0, Type::int()));
        $this->assertEquals(new IntValue(['value' => '123']), AST::astFromValue(123.5, Type::int()));
        $this->assertEquals(new IntValue(['value' => '10000']), AST::astFromValue(1e4, Type::int()));

        try {
            AST::astFromValue(1e40, Type::int()); // Note: js version will produce 1e+40, both values are valid GraphQL floats
            $this->fail('Expected exception is not thrown');
        } catch (\Exception $e) {
            $this->assertSame('Int cannot represent non 32-bit signed integer value: 1.0E+40', $e->getMessage());
        }
    }

    /**
     * @it converts Float values to Int/Float ASTs
     */
    public function testConvertsFloatValuesToIntOrFloatASTs()
    {
        $this->assertEquals(new IntValue(['value' => '123']), AST::astFromValue(123, Type::float()));
        $this->assertEquals(new IntValue(['value' => '123']), AST::astFromValue(123.0, Type::float()));
        $this->assertEquals(new FloatValue(['value' => '123.5']), AST::astFromValue(123.5, Type::float()));
        $this->assertEquals(new IntValue(['value' => '10000']), AST::astFromValue(1e4, Type::float()));
        $this->assertEquals(new FloatValue(['value' => '1e+40']), AST::astFromValue(1e40, Type::float()));
    }

    /**
     * @it converts String values to String ASTs
     */
    public function testConvertsStringValuesToASTs()
    {
        $this->assertEquals(new StringValue(['value' => 'hello']), AST::astFromValue('hello', Type::string()));
        $this->assertEquals(new StringValue(['value' => 'VALUE']), AST::astFromValue('VALUE', Type::string()));
        $this->assertEquals(new StringValue(['value' => 'VA\\nLUE']), AST::astFromValue("VA\nLUE", Type::string()));
        $this->assertEquals(new StringValue(['value' => '123']), AST::astFromValue(123, Type::string()));
        $this->assertEquals(new StringValue(['value' => 'false']), AST::astFromValue(false, Type::string()));
        $this->assertEquals(null, AST::astFromValue(null, Type::string()));
    }

    /**
     * @it converts ID values to Int/String ASTs
     */
    public function testConvertIdValuesToIntOrStringASTs()
    {
        $this->assertEquals(new StringValue(['value' => 'hello']), AST::astFromValue('hello', Type::id()));
        $this->assertEquals(new StringValue(['value' => 'VALUE']), AST::astFromValue('VALUE', Type::id()));
        $this->assertEquals(new StringValue(['value' => 'VA\\nLUE']), AST::astFromValue("VA\nLUE", Type::id()));
        $this->assertEquals(new IntValue(['value' => '123']), AST::astFromValue(123, Type::id()));
        $this->assertEquals(new StringValue(['value' => 'false']), AST::astFromValue(false, Type::id()));
        $this->assertEquals(null, AST::astFromValue(null, Type::id()));
    }

    /**
     * @it converts string values to Enum ASTs if possible
     */
    public function testConvertsStringValuesToEnumASTsIfPossible()
    {
        $this->assertEquals(new EnumValue(['value' => 'HELLO']), AST::astFromValue('HELLO', $this->myEnum()));
        $this->assertEquals(new EnumValue(['value' => 'COMPLEX']), AST::astFromValue($this->complexValue(), $this->myEnum()));

        // Note: case sensitive
        $this->assertEquals(null, AST::astFromValue('hello', $this->myEnum()));

        // Note: Not a valid enum value
        $this->assertEquals(null, AST::astFromValue('VALUE', $this->myEnum()));
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
        $this->assertEquals($value1, AST::astFromValue(['FOO', 'BAR'], Type::listOf(Type::string())));

        $value2 = new ListValue([
            'values' => [
                new EnumValue(['value' => 'HELLO']),
                new EnumValue(['value' => 'GOODBYE']),
            ]
        ]);
        $this->assertEquals($value2, AST::astFromValue(['HELLO', 'GOODBYE'], Type::listOf($this->myEnum())));
    }

    /**
     * @it converts list singletons
     */
    public function testConvertsListSingletons()
    {
        $this->assertEquals(new StringValue(['value' => 'FOO']), AST::astFromValue('FOO', Type::listOf(Type::string())));
    }

    /**
     * @it converts input objects
     */
    public function testConvertsInputObjects()
    {
        $inputObj = new InputObjectType([
            'name' => 'MyInputObj',
            'fields' => [
                'foo' => Type::float(),
                'bar' => $this->myEnum()
            ]
        ]);

        $expected = new ObjectValue([
            'fields' => [
                $this->objectField('foo', new IntValue(['value' => '3'])),
                $this->objectField('bar', new EnumValue(['value' => 'HELLO']))
            ]
        ]);

        $data = ['foo' => 3, 'bar' => 'HELLO'];
        $this->assertEquals($expected, AST::astFromValue($data, $inputObj));
        $this->assertEquals($expected, AST::astFromValue((object) $data, $inputObj));
    }

    private $complexValue;

    private function complexValue()
    {
        if (!$this->complexValue) {
            $this->complexValue = new \stdClass();
            $this->complexValue->someArbitrary = 'complexValue';
        }
        return $this->complexValue;
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
                'COMPLEX' => ['value' => $this->complexValue()]
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
