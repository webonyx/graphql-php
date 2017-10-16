<?php
namespace GraphQL\Tests\Utils;

use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\StringValueNode;
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
        $this->assertEquals(new BooleanValueNode(['value' => true]), AST::astFromValue(true, Type::boolean()));
        $this->assertEquals(new BooleanValueNode(['value' => false]), AST::astFromValue(false, Type::boolean()));
        $this->assertEquals(new NullValueNode([]), AST::astFromValue(null, Type::boolean()));
        $this->assertEquals(new BooleanValueNode(['value' => false]), AST::astFromValue(0, Type::boolean()));
        $this->assertEquals(new BooleanValueNode(['value' => true]), AST::astFromValue(1, Type::boolean()));
        $this->assertEquals(new BooleanValueNode(['value' => false]), AST::astFromValue(0, Type::nonNull(Type::boolean())));
        $this->assertEquals(null, AST::astFromValue(null, Type::nonNull(Type::boolean()))); // Note: null means that AST cannot
    }

    /**
     * @it converts Int values to Int ASTs
     */
    public function testConvertsIntValuesToASTs()
    {
        $this->assertEquals(new IntValueNode(['value' => '123']), AST::astFromValue(123.0, Type::int()));
        $this->assertEquals(new IntValueNode(['value' => '10000']), AST::astFromValue(1e4, Type::int()));
        $this->assertEquals(new IntValueNode(['value' => '0']), AST::astFromValue(0e4, Type::int()));

        // GraphQL spec does not allow coercing non-integer values to Int to avoid
        // accidental data loss.
        try {
            AST::astFromValue(123.5, Type::int());
            $this->fail('Expected exception not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Int cannot represent non-integer value: 123.5', $e->getMessage());
        }

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
        $this->assertEquals(new IntValueNode(['value' => '123']), AST::astFromValue(123, Type::float()));
        $this->assertEquals(new IntValueNode(['value' => '123']), AST::astFromValue(123.0, Type::float()));
        $this->assertEquals(new FloatValueNode(['value' => '123.5']), AST::astFromValue(123.5, Type::float()));
        $this->assertEquals(new IntValueNode(['value' => '10000']), AST::astFromValue(1e4, Type::float()));
        $this->assertEquals(new FloatValueNode(['value' => '1e+40']), AST::astFromValue(1e40, Type::float()));
        $this->assertEquals(new IntValueNode(['value' => '0']), AST::astFromValue(0e40, Type::float()));
    }

    /**
     * @it converts String values to String ASTs
     */
    public function testConvertsStringValuesToASTs()
    {
        $this->assertEquals(new StringValueNode(['value' => 'hello']), AST::astFromValue('hello', Type::string()));
        $this->assertEquals(new StringValueNode(['value' => 'VALUE']), AST::astFromValue('VALUE', Type::string()));
        $this->assertEquals(new StringValueNode(['value' => 'VA\\nLUE']), AST::astFromValue("VA\nLUE", Type::string()));
        $this->assertEquals(new StringValueNode(['value' => '123']), AST::astFromValue(123, Type::string()));
        $this->assertEquals(new StringValueNode(['value' => 'false']), AST::astFromValue(false, Type::string()));
        $this->assertEquals(new NullValueNode([]), AST::astFromValue(null, Type::string()));
        $this->assertEquals(null, AST::astFromValue(null, Type::nonNull(Type::string())));
    }

    /**
     * @it converts ID values to Int/String ASTs
     */
    public function testConvertIdValuesToIntOrStringASTs()
    {
        $this->assertEquals(new StringValueNode(['value' => 'hello']), AST::astFromValue('hello', Type::id()));
        $this->assertEquals(new StringValueNode(['value' => 'VALUE']), AST::astFromValue('VALUE', Type::id()));
        $this->assertEquals(new StringValueNode(['value' => 'VA\\nLUE']), AST::astFromValue("VA\nLUE", Type::id()));
        $this->assertEquals(new IntValueNode(['value' => '123']), AST::astFromValue(123, Type::id()));
        $this->assertEquals(new StringValueNode(['value' => 'false']), AST::astFromValue(false, Type::id()));
        $this->assertEquals(new NullValueNode([]), AST::astFromValue(null, Type::id()));
        $this->assertEquals(null, AST::astFromValue(null, Type::nonNull(Type::id())));
    }

    /**
     * @it does not converts NonNull values to NullValue
     */
    public function testDoesNotConvertsNonNullValuestoNullValue()
    {
        $this->assertSame(null, AST::astFromValue(null, Type::nonNull(Type::boolean())));
    }

    /**
     * @it converts string values to Enum ASTs if possible
     */
    public function testConvertsStringValuesToEnumASTsIfPossible()
    {
        $this->assertEquals(new EnumValueNode(['value' => 'HELLO']), AST::astFromValue('HELLO', $this->myEnum()));
        $this->assertEquals(new EnumValueNode(['value' => 'COMPLEX']), AST::astFromValue($this->complexValue(), $this->myEnum()));

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
        $value1 = new ListValueNode([
            'values' => [
                new StringValueNode(['value' => 'FOO']),
                new StringValueNode(['value' => 'BAR'])
            ]
        ]);
        $this->assertEquals($value1, AST::astFromValue(['FOO', 'BAR'], Type::listOf(Type::string())));

        $value2 = new ListValueNode([
            'values' => [
                new EnumValueNode(['value' => 'HELLO']),
                new EnumValueNode(['value' => 'GOODBYE']),
            ]
        ]);
        $this->assertEquals($value2, AST::astFromValue(['HELLO', 'GOODBYE'], Type::listOf($this->myEnum())));
    }

    /**
     * @it converts list singletons
     */
    public function testConvertsListSingletons()
    {
        $this->assertEquals(new StringValueNode(['value' => 'FOO']), AST::astFromValue('FOO', Type::listOf(Type::string())));
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

        $expected = new ObjectValueNode([
            'fields' => [
                $this->objectField('foo', new IntValueNode(['value' => '3'])),
                $this->objectField('bar', new EnumValueNode(['value' => 'HELLO']))
            ]
        ]);

        $data = ['foo' => 3, 'bar' => 'HELLO'];
        $this->assertEquals($expected, AST::astFromValue($data, $inputObj));
        $this->assertEquals($expected, AST::astFromValue((object) $data, $inputObj));
    }

    /**
     * @it converts input objects with explicit nulls
     */
    public function testConvertsInputObjectsWithExplicitNulls()
    {
        $inputObj = new InputObjectType([
            'name' => 'MyInputObj',
            'fields' => [
                'foo' => Type::float(),
                'bar' => $this->myEnum()
            ]
        ]);

        $this->assertEquals(new ObjectValueNode([
            'fields' => [
                $this->objectField('foo', new NullValueNode([]))
            ]
        ]), AST::astFromValue(['foo' => null], $inputObj));
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
     * @return ObjectFieldNode
     */
    private function objectField($name, $value)
    {
        return new ObjectFieldNode([
            'name' => new NameNode(['value' => $name]),
            'value' => $value
        ]);
    }
}
