<?php declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Error\InvariantViolation;
use GraphQL\Error\SerializationError;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\AST;
use PHPUnit\Framework\TestCase;

final class AstFromValueTest extends TestCase
{
    private \stdClass $complexValue;

    /** @see it('converts boolean values to ASTs') */
    public function testConvertsBooleanValueToASTs(): void
    {
        self::assertEquals(new BooleanValueNode(['value' => true]), AST::astFromValue(true, Type::boolean()));
        self::assertEquals(new BooleanValueNode(['value' => false]), AST::astFromValue(false, Type::boolean()));
        self::assertEquals(new NullValueNode([]), AST::astFromValue(null, Type::boolean()));
        self::assertEquals(new BooleanValueNode(['value' => false]), AST::astFromValue(0, Type::boolean()));
        self::assertEquals(new BooleanValueNode(['value' => true]), AST::astFromValue(1, Type::boolean()));
        self::assertEquals(new BooleanValueNode(['value' => false]), AST::astFromValue(0, Type::nonNull(Type::boolean())));
        self::assertNull(AST::astFromValue(null, Type::nonNull(Type::boolean())));
    }

    /** @see it('converts Int values to Int ASTs') */
    public function testConvertsIntValuesToASTs(): void
    {
        self::assertEquals(new IntValueNode(['value' => '-1']), AST::astFromValue(-1, Type::int()));
        self::assertEquals(new IntValueNode(['value' => '123']), AST::astFromValue(123.0, Type::int()));
        self::assertEquals(new IntValueNode(['value' => '10000']), AST::astFromValue(1e4, Type::int()));
        self::assertEquals(new IntValueNode(['value' => '0']), AST::astFromValue(0e4, Type::int()));
    }

    public function testConvertsIntValuesToASTsCannotRepresentNonInteger(): void
    {
        // GraphQL spec does not allow coercing non-integer values to Int to avoid
        // accidental data loss.
        $this->expectException(\Throwable::class);
        $this->expectExceptionMessage('Int cannot represent non-integer value: 123.5');
        AST::astFromValue(123.5, Type::int());
    }

    public function testConvertsIntValuesToASTsCannotRepresentNon32bitsInteger(): void
    {
        $this->expectException(\Throwable::class);
        $this->expectExceptionMessage('Int cannot represent non 32-bit signed integer value: 1.0E+40');
        AST::astFromValue(
            1e40,
            Type::int()
        ); // Note: js version will produce 1e+40, both values are valid GraphQL floats
    }

    /** @see it('converts Float values to Int/Float ASTs') */
    public function testConvertsFloatValuesToIntOrFloatASTs(): void
    {
        self::assertEquals(new IntValueNode(['value' => '-1']), AST::astFromValue(-1, Type::float()));
        self::assertEquals(new IntValueNode(['value' => '123']), AST::astFromValue(123, Type::float()));
        self::assertEquals(new IntValueNode(['value' => '123']), AST::astFromValue(123.0, Type::float()));
        self::assertEquals(new FloatValueNode(['value' => '123.5']), AST::astFromValue(123.5, Type::float()));
        self::assertEquals(new IntValueNode(['value' => '10000']), AST::astFromValue(1e4, Type::float()));
        self::assertEquals(new FloatValueNode(['value' => '1.0E+40']), AST::astFromValue(1e40, Type::float()));
        self::assertEquals(new IntValueNode(['value' => '0']), AST::astFromValue(0e40, Type::float()));
    }

    /** @see it('converts String values to String ASTs') */
    public function testConvertsStringValuesToASTs(): void
    {
        self::assertEquals(new StringValueNode(['value' => 'hello']), AST::astFromValue('hello', Type::string()));
        self::assertEquals(new StringValueNode(['value' => 'VALUE']), AST::astFromValue('VALUE', Type::string()));
        self::assertEquals(new StringValueNode(['value' => "VA\nLUE"]), AST::astFromValue("VA\nLUE", Type::string()));
        self::assertEquals(new StringValueNode(['value' => '123']), AST::astFromValue(123, Type::string()));
        self::assertEquals(new StringValueNode(['value' => '']), AST::astFromValue(false, Type::string()));
        self::assertEquals(new NullValueNode([]), AST::astFromValue(null, Type::string()));
        self::assertEquals(null, AST::astFromValue(null, Type::nonNull(Type::string())));
    }

    /** @see it('converts ID values to Int/String ASTs') */
    public function testConvertIdValuesToIntOrStringASTs(): void
    {
        self::assertEquals(new StringValueNode(['value' => 'hello']), AST::astFromValue('hello', Type::id()));
        self::assertEquals(new StringValueNode(['value' => 'VALUE']), AST::astFromValue('VALUE', Type::id()));
        self::assertEquals(new StringValueNode(['value' => "VA\nLUE"]), AST::astFromValue("VA\nLUE", Type::id()));
        self::assertEquals(new IntValueNode(['value' => '-1']), AST::astFromValue(-1, Type::id()));
        self::assertEquals(new IntValueNode(['value' => '123']), AST::astFromValue(123, Type::id()));
        self::assertEquals(new IntValueNode(['value' => '123']), AST::astFromValue('123', Type::id()));
        self::assertEquals(new StringValueNode(['value' => '01']), AST::astFromValue('01', Type::id()));
        self::assertEquals(new NullValueNode([]), AST::astFromValue(null, Type::id()));
        self::assertEquals(null, AST::astFromValue(null, Type::nonNull(Type::id())));
    }

    /** @see it('does not converts NonNull values to NullValue') */
    public function testDoesNotConvertsNonNullValuestoNullValue(): void
    {
        self::assertNull(AST::astFromValue(null, Type::nonNull(Type::boolean())));
    }

    /** @see it('converts string values to Enum ASTs if possible') */
    public function testConvertsStringValuesToEnumAST(): void
    {
        self::assertEquals(
            new EnumValueNode(['value' => 'HELLO']),
            AST::astFromValue('HELLO', $this->myEnum())
        );

        self::assertEquals(
            new EnumValueNode(['value' => 'COMPLEX']),
            AST::astFromValue($this->complexValue(), $this->myEnum())
        );
    }

    public function testEnumsAreCaseSensitive(): void
    {
        self::expectException(SerializationError::class);
        AST::astFromValue('hello', $this->myEnum());
    }

    public function testRejectsInvalidEnumValues(): void
    {
        self::expectException(SerializationError::class);
        self::assertNull(AST::astFromValue('some-totally-invalid-value', $this->myEnum()));
    }

    /** @throws InvariantViolation */
    private function myEnum(): EnumType
    {
        return new EnumType([
            'name' => 'MyEnum',
            'values' => [
                'HELLO' => [],
                'GOODBYE' => [],
                'COMPLEX' => ['value' => $this->complexValue()],
            ],
        ]);
    }

    private function complexValue(): \stdClass
    {
        return $this->complexValue ??= (object) ['someArbitrary' => 'complexValue'];
    }

    /** @see it('converts array values to List ASTs') */
    public function testConvertsArrayValuesToListASTs(): void
    {
        $value1 = new ListValueNode([
            'values' => new NodeList([
                new StringValueNode(['value' => 'FOO']),
                new StringValueNode(['value' => 'BAR']),
            ]),
        ]);
        self::assertEquals($value1, AST::astFromValue(['FOO', 'BAR'], Type::listOf(Type::string())));

        $value2 = new ListValueNode([
            'values' => new NodeList([
                new EnumValueNode(['value' => 'HELLO']),
                new EnumValueNode(['value' => 'GOODBYE']),
            ]),
        ]);
        self::assertEquals($value2, AST::astFromValue(['HELLO', 'GOODBYE'], Type::listOf($this->myEnum())));
    }

    /** @see it('converts list singletons') */
    public function testConvertsListSingletons(): void
    {
        self::assertEquals(
            new StringValueNode(['value' => 'FOO']),
            AST::astFromValue('FOO', Type::listOf(Type::string()))
        );
    }

    /** @see it('converts input objects') */
    public function testConvertsInputObjects(): void
    {
        $inputObj = new InputObjectType([
            'name' => 'MyInputObj',
            'fields' => [
                'foo' => Type::float(),
                'bar' => $this->myEnum(),
            ],
        ]);

        $expected = new ObjectValueNode([
            'fields' => new NodeList([
                $this->objectField('foo', new IntValueNode(['value' => '3'])),
                $this->objectField('bar', new EnumValueNode(['value' => 'HELLO'])),
            ]),
        ]);

        $data = ['foo' => 3, 'bar' => 'HELLO'];
        self::assertEquals($expected, AST::astFromValue($data, $inputObj));
        self::assertEquals($expected, AST::astFromValue((object) $data, $inputObj));
    }

    /** @param mixed $value */
    private function objectField(string $name, $value): ObjectFieldNode
    {
        return new ObjectFieldNode([
            'name' => new NameNode(['value' => $name]),
            'value' => $value,
        ]);
    }

    /** @see it('converts input objects with explicit nulls') */
    public function testConvertsInputObjectsWithExplicitNulls(): void
    {
        $inputObj = new InputObjectType([
            'name' => 'MyInputObj',
            'fields' => [
                'foo' => Type::float(),
                'bar' => $this->myEnum(),
            ],
        ]);

        self::assertEquals(
            new ObjectValueNode([
                'fields' => new NodeList([
                    $this->objectField('foo', new NullValueNode([])),
                ]),
            ]),
            AST::astFromValue(['foo' => null], $inputObj)
        );
    }
}
