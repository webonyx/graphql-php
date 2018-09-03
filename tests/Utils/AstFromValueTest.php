<?php

declare(strict_types=1);

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
use PHPUnit\Framework\TestCase;
use stdClass;

class AstFromValueTest extends TestCase
{
    /** @var stdClass */
    private $complexValue;

    /**
     * @see it('converts boolean values to ASTs')
     */
    public function testConvertsBooleanValueToASTs() : void
    {
        $this->assertEquals(new BooleanValueNode(['value' => true]), AST::astFromValue(true, Type::boolean()));
        $this->assertEquals(new BooleanValueNode(['value' => false]), AST::astFromValue(false, Type::boolean()));
        $this->assertEquals(new NullValueNode([]), AST::astFromValue(null, Type::boolean()));
        $this->assertEquals(new BooleanValueNode(['value' => false]), AST::astFromValue(0, Type::boolean()));
        $this->assertEquals(new BooleanValueNode(['value' => true]), AST::astFromValue(1, Type::boolean()));
        $this->assertEquals(
            new BooleanValueNode(['value' => false]),
            AST::astFromValue(0, Type::nonNull(Type::boolean()))
        );
        $this->assertEquals(
            null,
            AST::astFromValue(null, Type::nonNull(Type::boolean()))
        ); // Note: null means that AST cannot
    }

    /**
     * @see it('converts Int values to Int ASTs')
     */
    public function testConvertsIntValuesToASTs() : void
    {
        $this->assertEquals(new IntValueNode(['value' => '-1']), AST::astFromValue(-1, Type::int()));
        $this->assertEquals(new IntValueNode(['value' => '123']), AST::astFromValue(123.0, Type::int()));
        $this->assertEquals(new IntValueNode(['value' => '10000']), AST::astFromValue(1e4, Type::int()));
        $this->assertEquals(new IntValueNode(['value' => '0']), AST::astFromValue(0e4, Type::int()));
    }

    public function testConvertsIntValuesToASTsCannotRepresentNonInteger() : void
    {
        // GraphQL spec does not allow coercing non-integer values to Int to avoid
        // accidental data loss.
        $this->expectException(\Throwable::class);
        $this->expectExceptionMessage('Int cannot represent non-integer value: 123.5');
        AST::astFromValue(123.5, Type::int());
    }

    public function testConvertsIntValuesToASTsCannotRepresentNon32bitsInteger() : void
    {
        $this->expectException(\Throwable::class);
        $this->expectExceptionMessage('Int cannot represent non 32-bit signed integer value: 1.0E+40');
        AST::astFromValue(
            1e40,
            Type::int()
        ); // Note: js version will produce 1e+40, both values are valid GraphQL floats
    }

    /**
     * @see it('converts Float values to Int/Float ASTs')
     */
    public function testConvertsFloatValuesToIntOrFloatASTs() : void
    {
        $this->assertEquals(new IntValueNode(['value' => '-1']), AST::astFromValue(-1, Type::float()));
        $this->assertEquals(new IntValueNode(['value' => '123']), AST::astFromValue(123, Type::float()));
        $this->assertEquals(new IntValueNode(['value' => '123']), AST::astFromValue(123.0, Type::float()));
        $this->assertEquals(new FloatValueNode(['value' => '123.5']), AST::astFromValue(123.5, Type::float()));
        $this->assertEquals(new IntValueNode(['value' => '10000']), AST::astFromValue(1e4, Type::float()));
        $this->assertEquals(new FloatValueNode(['value' => '1e+40']), AST::astFromValue(1e40, Type::float()));
        $this->assertEquals(new IntValueNode(['value' => '0']), AST::astFromValue(0e40, Type::float()));
    }

    /**
     * @see it('converts String values to String ASTs')
     */
    public function testConvertsStringValuesToASTs() : void
    {
        $this->assertEquals(new StringValueNode(['value' => 'hello']), AST::astFromValue('hello', Type::string()));
        $this->assertEquals(new StringValueNode(['value' => 'VALUE']), AST::astFromValue('VALUE', Type::string()));
        $this->assertEquals(new StringValueNode(['value' => "VA\nLUE"]), AST::astFromValue("VA\nLUE", Type::string()));
        $this->assertEquals(new StringValueNode(['value' => '123']), AST::astFromValue(123, Type::string()));
        $this->assertEquals(new StringValueNode(['value' => 'false']), AST::astFromValue(false, Type::string()));
        $this->assertEquals(new NullValueNode([]), AST::astFromValue(null, Type::string()));
        $this->assertEquals(null, AST::astFromValue(null, Type::nonNull(Type::string())));
    }

    /**
     * @see it('converts ID values to Int/String ASTs')
     */
    public function testConvertIdValuesToIntOrStringASTs() : void
    {
        $this->assertEquals(new StringValueNode(['value' => 'hello']), AST::astFromValue('hello', Type::id()));
        $this->assertEquals(new StringValueNode(['value' => 'VALUE']), AST::astFromValue('VALUE', Type::id()));
        $this->assertEquals(new StringValueNode(['value' => "VA\nLUE"]), AST::astFromValue("VA\nLUE", Type::id()));
        $this->assertEquals(new IntValueNode(['value' => '-1']), AST::astFromValue(-1, Type::id()));
        $this->assertEquals(new IntValueNode(['value' => '123']), AST::astFromValue(123, Type::id()));
        $this->assertEquals(new IntValueNode(['value' => '123']), AST::astFromValue('123', Type::id()));
        $this->assertEquals(new StringValueNode(['value' => '01']), AST::astFromValue('01', Type::id()));
        $this->assertEquals(new StringValueNode(['value' => 'false']), AST::astFromValue(false, Type::id()));
        $this->assertEquals(new NullValueNode([]), AST::astFromValue(null, Type::id()));
        $this->assertEquals(null, AST::astFromValue(null, Type::nonNull(Type::id())));
    }

    /**
     * @see it('does not converts NonNull values to NullValue')
     */
    public function testDoesNotConvertsNonNullValuestoNullValue() : void
    {
        $this->assertNull(AST::astFromValue(null, Type::nonNull(Type::boolean())));
    }

    /**
     * @see it('converts string values to Enum ASTs if possible')
     */
    public function testConvertsStringValuesToEnumASTsIfPossible() : void
    {
        $this->assertEquals(new EnumValueNode(['value' => 'HELLO']), AST::astFromValue('HELLO', $this->myEnum()));
        $this->assertEquals(
            new EnumValueNode(['value' => 'COMPLEX']),
            AST::astFromValue($this->complexValue(), $this->myEnum())
        );

        // Note: case sensitive
        $this->assertNull(AST::astFromValue('hello', $this->myEnum()));

        // Note: Not a valid enum value
        $this->assertNull(AST::astFromValue('VALUE', $this->myEnum()));
    }

    /**
     * @return EnumType
     */
    private function myEnum()
    {
        return new EnumType([
            'name'   => 'MyEnum',
            'values' => [
                'HELLO'   => [],
                'GOODBYE' => [],
                'COMPLEX' => ['value' => $this->complexValue()],
            ],
        ]);
    }

    private function complexValue()
    {
        if (! $this->complexValue) {
            $this->complexValue                = new \stdClass();
            $this->complexValue->someArbitrary = 'complexValue';
        }

        return $this->complexValue;
    }

    /**
     * @see it('converts array values to List ASTs')
     */
    public function testConvertsArrayValuesToListASTs() : void
    {
        $value1 = new ListValueNode([
            'values' => [
                new StringValueNode(['value' => 'FOO']),
                new StringValueNode(['value' => 'BAR']),
            ],
        ]);
        $this->assertEquals($value1, AST::astFromValue(['FOO', 'BAR'], Type::listOf(Type::string())));

        $value2 = new ListValueNode([
            'values' => [
                new EnumValueNode(['value' => 'HELLO']),
                new EnumValueNode(['value' => 'GOODBYE']),
            ],
        ]);
        $this->assertEquals($value2, AST::astFromValue(['HELLO', 'GOODBYE'], Type::listOf($this->myEnum())));
    }

    /**
     * @see it('converts list singletons')
     */
    public function testConvertsListSingletons() : void
    {
        $this->assertEquals(
            new StringValueNode(['value' => 'FOO']),
            AST::astFromValue('FOO', Type::listOf(Type::string()))
        );
    }

    /**
     * @see it('converts input objects')
     */
    public function testConvertsInputObjects() : void
    {
        $inputObj = new InputObjectType([
            'name'   => 'MyInputObj',
            'fields' => [
                'foo' => Type::float(),
                'bar' => $this->myEnum(),
            ],
        ]);

        $expected = new ObjectValueNode([
            'fields' => [
                $this->objectField('foo', new IntValueNode(['value' => '3'])),
                $this->objectField('bar', new EnumValueNode(['value' => 'HELLO'])),
            ],
        ]);

        $data = ['foo' => 3, 'bar' => 'HELLO'];
        $this->assertEquals($expected, AST::astFromValue($data, $inputObj));
        $this->assertEquals($expected, AST::astFromValue((object) $data, $inputObj));
    }

    /**
     * @param mixed $value
     * @return ObjectFieldNode
     */
    private function objectField(string $name, $value)
    {
        return new ObjectFieldNode([
            'name'  => new NameNode(['value' => $name]),
            'value' => $value,
        ]);
    }

    /**
     * @see it('converts input objects with explicit nulls')
     */
    public function testConvertsInputObjectsWithExplicitNulls() : void
    {
        $inputObj = new InputObjectType([
            'name'   => 'MyInputObj',
            'fields' => [
                'foo' => Type::float(),
                'bar' => $this->myEnum(),
            ],
        ]);

        $this->assertEquals(
            new ObjectValueNode([
            'fields' => [
                $this->objectField('foo', new NullValueNode([])),
            ],
            ]),
            AST::astFromValue(['foo' => null], $inputObj)
        );
    }
}
