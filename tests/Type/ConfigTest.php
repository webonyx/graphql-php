<?php
namespace GraphQL\Tests\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\Error\Warning;
use GraphQL\Type\Definition\Config;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\Utils;

class ConfigTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        Warning::suppress(Warning::WARNING_CONFIG_DEPRECATION);
    }

    public static function tearDownAfterClass()
    {
        Config::disableValidation();
        Warning::enable(Warning::WARNING_CONFIG_DEPRECATION);
    }

    public function testToggling()
    {
        // Disabled by default
        $this->assertEquals(false, Config::isValidationEnabled());
        Config::validate(['test' => []], ['test' => Config::STRING]); // must not throw

        Config::enableValidation();
        $this->assertEquals(true, Config::isValidationEnabled());

        try {
            Config::validate(['test' => []], ['test' => Config::STRING]);
            $this->fail('Expected exception not thrown');
        } catch (\Exception $e) {
        }

        Config::disableValidation();
        $this->assertEquals(false, Config::isValidationEnabled());
        Config::validate(['test' => []], ['test' => Config::STRING]);
    }

    public function testValidateString()
    {
        $this->expectValidationPasses(
            [
                'test' => 'string',
                'empty' => ''
            ],
            [
                'test' => Config::STRING,
                'empty' => Config::STRING
            ]
        );

        $this->expectValidationThrows(
            ['test' => 1],
            ['test' => Config::STRING],
            $this->typeError('expecting "string" at "test", but got "integer"')
        );
    }

    public function testArray()
    {
        $this->expectValidationPasses(
            ['test' => [
                [],
                ['nested' => 'A'],
                ['nested' => null]
            ]],
            ['test' => Config::arrayOf(
                ['nested' => Config::STRING]
            )]
        );

        $this->expectValidationThrows(
            ['test' => [
                null
            ]],
            ['test' => Config::arrayOf(
                ['nested' => Config::STRING]
            )],
            $this->typeError("Each entry at 'test' must be an array, but entry at '0' is 'NULL'")
        );

        $this->expectValidationPasses(
            ['test' => null],
            ['test' => Config::arrayOf(
                ['nested' => Config::STRING]
            )]
        );

        $this->expectValidationThrows(
            ['test' => null],
            ['test' => Config::arrayOf(
                ['nested' => Config::STRING],
                Config::REQUIRED
            )],
            $this->typeError('expecting "array" at "test", but got "NULL"')
        );

        // Check validation nesting:
        $this->expectValidationPasses(
            ['nest' => [
                ['nest' => [
                    ['test' => 'value']
                ]]
            ]],
            ['nest' => Config::arrayOf([
                'nest' => Config::arrayOf([
                    'test' => Config::STRING
                ])
            ])]
        );

        $this->expectValidationThrows(
            ['nest' => [
                ['nest' => [
                    ['test' => 'notInt']
                ]]
            ]],
            ['nest' => Config::arrayOf([
                'nest' => Config::arrayOf([
                    'test' => Config::INT
                ])
            ])],
            $this->typeError('expecting "int" at "nest:0:nest:0:test", but got "string"')
        );

        // Check arrays of types:
        $this->expectValidationPasses(
            ['nest' => [
                Type::string(),
                Type::int()
            ]],
            ['nest' => Config::arrayOf(
                Config::OUTPUT_TYPE, Config::REQUIRED
            )]
        );

        // Check arrays of types:
        $this->expectValidationThrows(
            ['nest' => [
                Type::string(),
                new InputObjectType(['name' => 'test', 'fields' => []])
            ]],
            ['nest' => Config::arrayOf(
                Config::OUTPUT_TYPE, Config::REQUIRED
            )],
            $this->typeError('expecting "OutputType definition" at "nest:1", but got "test"')
        );
    }

    public function testRequired()
    {
        $this->expectValidationPasses(
            ['required' => ''],
            ['required' => Config::STRING | Config::REQUIRED]
        );

        $this->expectValidationThrows(
            [],
            ['required' => Config::STRING | Config::REQUIRED],
            $this->typeError('Required keys missing: "required" ')
        );

        $this->expectValidationThrows(
            ['required' => null],
            ['required' => Config::STRING | Config::REQUIRED],
            $this->typeError('Value at "required" can not be null')
        );

        $this->expectValidationPasses(
            ['test' => [
                ['nested' => '']
            ]],
            ['test' => Config::arrayOf([
                'nested' => Config::STRING | Config::REQUIRED
            ])]
        );

        $this->expectValidationThrows(
            ['test' => [
                []
            ]],
            ['test' => Config::arrayOf([
                'nested' => Config::STRING | Config::REQUIRED
            ])],
            $this->typeError('Required keys missing: "nested"  at test:0')
        );

        $this->expectValidationThrows(
            ['test' => [
                ['nested' => null]
            ]],
            ['test' => Config::arrayOf([
                'nested' => Config::STRING | Config::REQUIRED
            ])],
            $this->typeError('Value at "test:0:nested" can not be null')
        );

        $this->expectValidationPasses(
            ['test' => [
                ['nested' => null]
            ]],
            ['test' => Config::arrayOf(
                ['nested' => Config::STRING],
                Config::REQUIRED
            )]
        );

        $this->expectValidationThrows(
            ['test' => [

            ]],
            ['test' => Config::arrayOf(
                ['nested' => Config::STRING],
                Config::REQUIRED
            )],
            $this->typeError("Value at 'test' cannot be empty array")
        );
    }

    public function testKeyAsName()
    {
        $this->expectValidationPasses(
            ['test' => [
                'name1' => ['key1' => null],
                ['name' => 'name1'],
            ]],
            ['test' => Config::arrayOf(
                ['name' => Config::STRING | Config::REQUIRED, 'key1' => Config::STRING],
                Config::KEY_AS_NAME
            )]
        );

        $this->expectValidationThrows(
            ['test' => [
                'name1' => ['key1' => null]
            ]],
            ['test' => Config::arrayOf(
                ['name' => Config::STRING | Config::REQUIRED, 'key1' => Config::STRING]
            )],
            $this->typeError('Required keys missing: "name"  at test:name1')
        );

        $this->expectValidationThrows(
            ['test' => [
                ['key1' => null]
            ]],
            ['test' => Config::arrayOf(
                ['name' => Config::STRING | Config::REQUIRED, 'key1' => Config::STRING],
                Config::KEY_AS_NAME
            )],
            $this->typeError('Required keys missing: "name"  at test:0')
        );
    }

    public function testMaybeThunk()
    {
        $this->expectValidationPasses(
            [
                'test' => [
                    ['nested' => ''],
                    ['nested' => '1'],
                ],
                'testThunk' => function() {
                    // Currently config won't validate thunk return value
                }
            ],
            [
                'test' => Config::arrayOf(
                    ['nested' => Config::STRING | Config::REQUIRED],
                    Config::MAYBE_THUNK
                ),
                'testThunk' => Config::arrayOf(
                    ['nested' => Config::STRING | Config::REQUIRED],
                    Config::MAYBE_THUNK
                )
            ]
        );

        $this->expectValidationThrows(
            [
                'testThunk' => $closure = function() {}
            ],
            [
                'testThunk' => Config::arrayOf(
                    ['nested' => Config::STRING | Config::REQUIRED]
                )
            ],
            $this->typeError('expecting "array" at "testThunk", but got "' . Utils::getVariableType($closure) . '"')
        );

        $this->expectValidationThrows(
            [
                'testThunk' => 1
            ],
            [
                'testThunk' => Config::arrayOf(
                    ['nested' => Config::STRING | Config::REQUIRED],
                    Config::MAYBE_THUNK
                )
            ],
            $this->typeError('expecting "array or callable" at "testThunk", but got "integer"')
        );
    }

    public function testMaybeType()
    {
        $type = new ObjectType([
            'name' => 'Test',
            'fields' => []
        ]);

        $this->expectValidationPasses(
            ['test' => [
                $type,
                ['type' => $type],
            ]],
            ['test' => Config::arrayOf(
                ['type' => Config::OBJECT_TYPE | Config::REQUIRED],
                Config::MAYBE_TYPE
            )]
        );

        $this->expectValidationThrows(
            ['test' => [
                ['type' => 'str']
            ]],
            ['test' => Config::arrayOf(
                ['type' => Config::OBJECT_TYPE | Config::REQUIRED],
                Config::MAYBE_TYPE
            )],
            $this->typeError('expecting "ObjectType definition" at "test:0:type", but got "string"')
        );

        $this->expectValidationThrows(
            ['test' => [
                $type
            ]],
            ['test' => Config::arrayOf(
                ['name' => Config::OBJECT_TYPE | Config::REQUIRED]
            )],
            $this->typeError("Each entry at 'test' must be an array, but entry at '0' is 'Test'")
        );
    }

    public function testMaybeName()
    {
        $this->expectValidationPasses(
            ['test' => [
                'some-name',
                ['name' => 'other-name'],
            ]],
            ['test' => Config::arrayOf(
                ['name' => Config::STRING | Config::REQUIRED],
                Config::MAYBE_NAME
            )]
        );

        $this->expectValidationThrows(
            ['test' => [
                'some-name'
            ]],
            ['test' => Config::arrayOf(
                ['name' => Config::OBJECT_TYPE | Config::REQUIRED]
            )],
            $this->typeError("Each entry at 'test' must be an array, but entry at '0' is 'string'")
        );

        $this->expectValidationPasses(
            ['test' => [
                'some-key' => 'some-name',
                'some-name'
            ]],
            ['test' => Config::arrayOf(
                ['name' => Config::STRING | Config::REQUIRED],
                Config::MAYBE_NAME | Config::KEY_AS_NAME
            )]
        );
    }

    public function getValidValues()
    {
        return [
            // $type, $validValue
            [Config::ANY, null],
            [Config::ANY, 0],
            [Config::ANY, ''],
            [Config::ANY, '0'],
            [Config::ANY, 1],
            [Config::ANY, function() {}],
            [Config::ANY, []],
            [Config::ANY, new \stdClass()],
            [Config::STRING, null],
            [Config::STRING, ''],
            [Config::STRING, '0'],
            [Config::STRING, 'anything'],
            [Config::BOOLEAN, null],
            [Config::BOOLEAN, false],
            [Config::BOOLEAN, true],
            [Config::INT, null],
            [Config::INT, 0],
            [Config::INT, 1],
            [Config::INT, -1],
            [Config::INT, 5000000],
            [Config::INT, -5000000],
            [Config::FLOAT, null],
            [Config::FLOAT, 0],
            [Config::FLOAT, 0.0],
            [Config::FLOAT, 0.1],
            [Config::FLOAT, -12.5],
            [Config::NUMERIC, null],
            [Config::NUMERIC, '0'],
            [Config::NUMERIC, 0],
            [Config::NUMERIC, 1],
            [Config::NUMERIC, 0.0],
            [Config::NUMERIC, 1.0],
            [Config::NUMERIC, -1.0],
            [Config::NUMERIC, -1],
            [Config::NUMERIC, 1],
            [Config::CALLBACK, null],
            [Config::CALLBACK, function() {}],
            [Config::CALLBACK, [$this, 'getValidValues']],
            [Config::SCALAR, null],
            [Config::SCALAR, 0],
            [Config::SCALAR, 1],
            [Config::SCALAR, 0.0],
            [Config::SCALAR, 1.0],
            [Config::SCALAR, true],
            [Config::SCALAR, false],
            [Config::SCALAR, ''],
            [Config::SCALAR, '0'],
            [Config::SCALAR, 'anything'],
            [Config::NAME, null],
            [Config::NAME, 'CamelCaseIsOk'],
            [Config::NAME, 'underscore_is_ok'],
            [Config::NAME, 'numbersAreOk0123456789'],
            [Config::INPUT_TYPE, null],
            [Config::INPUT_TYPE, new InputObjectType(['name' => 'test', 'fields' => []])],
            [Config::INPUT_TYPE, new EnumType(['name' => 'test2', 'values' => ['A', 'B', 'C']])],
            [Config::INPUT_TYPE, Type::string()],
            [Config::INPUT_TYPE, Type::int()],
            [Config::INPUT_TYPE, Type::float()],
            [Config::INPUT_TYPE, Type::boolean()],
            [Config::INPUT_TYPE, Type::id()],
            [Config::INPUT_TYPE, Type::listOf(Type::string())],
            [Config::INPUT_TYPE, Type::nonNull(Type::string())],
            [Config::OUTPUT_TYPE, null],
            [Config::OUTPUT_TYPE, new ObjectType(['name' => 'test3', 'fields' => []])],
            [Config::OUTPUT_TYPE, new EnumType(['name' => 'test4', 'values' => ['A', 'B', 'C']])],
            [Config::OUTPUT_TYPE, Type::string()],
            [Config::OUTPUT_TYPE, Type::int()],
            [Config::OUTPUT_TYPE, Type::float()],
            [Config::OUTPUT_TYPE, Type::boolean()],
            [Config::OUTPUT_TYPE, Type::id()],
            [Config::OBJECT_TYPE, null],
            [Config::OBJECT_TYPE, new ObjectType(['name' => 'test6', 'fields' => []])],
            [Config::INTERFACE_TYPE, null],
            [Config::INTERFACE_TYPE, new InterfaceType(['name' => 'test7', 'fields' => []])],
        ];
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($type, $validValue)
    {
        $this->expectValidationPasses(
            ['test' => $validValue],
            ['test' => $type]
        );
    }

    public function getInvalidValues()
    {
        return [
            // $type, $typeLabel, $invalidValue, $actualTypeLabel
            [Config::STRING, 'string', 1, 'integer'],
            [Config::STRING, 'string', 0, 'integer'],
            [Config::STRING, 'string', false, 'boolean'],
            [Config::STRING, 'string', $tmp = function() {}, Utils::getVariableType($tmp)], // Note: can't use "Closure" as HHVM returns different string
            [Config::STRING, 'string', [], 'array'],
            [Config::STRING, 'string', new \stdClass(), 'stdClass'],
            [Config::BOOLEAN, 'boolean', '', 'string'],
            [Config::BOOLEAN, 'boolean', 1, 'integer'],
            [Config::BOOLEAN, 'boolean', $tmp = function() {}, Utils::getVariableType($tmp)],
            [Config::BOOLEAN, 'boolean', [], 'array'],
            [Config::BOOLEAN, 'boolean', new \stdClass(), 'stdClass'],
            [Config::INT, 'int', false, 'boolean'],
            [Config::INT, 'int', '', 'string'],
            [Config::INT, 'int', '0', 'string'],
            [Config::INT, 'int', '1', 'string'],
            [Config::INT, 'int', $tmp = function() {}, Utils::getVariableType($tmp)],
            [Config::INT, 'int', [], 'array'],
            [Config::INT, 'int', new \stdClass(), 'stdClass'],
            [Config::FLOAT, 'float', '', 'string'],
            [Config::FLOAT, 'float', '0', 'string'],
            [Config::FLOAT, 'float', $tmp = function() {}, Utils::getVariableType($tmp)],
            [Config::FLOAT, 'float', [], 'array'],
            [Config::FLOAT, 'float', new \stdClass(), 'stdClass'],
            [Config::NUMERIC, 'numeric', '', 'string'],
            [Config::NUMERIC, 'numeric', 'tmp', 'string'],
            [Config::NUMERIC, 'numeric', [], 'array'],
            [Config::NUMERIC, 'numeric', new \stdClass(), 'stdClass'],
            [Config::NUMERIC, 'numeric', $tmp = function() {}, Utils::getVariableType($tmp)],
            [Config::CALLBACK, 'callable', 1, 'integer'],
            [Config::CALLBACK, 'callable', '', 'string'],
            [Config::CALLBACK, 'callable', [], 'array'],
            [Config::CALLBACK, 'callable', new \stdClass(), 'stdClass'],
            [Config::SCALAR, 'scalar', [], 'array'],
            [Config::SCALAR, 'scalar', new \stdClass(), 'stdClass'],
            [Config::SCALAR, 'scalar', $tmp = function() {}, Utils::getVariableType($tmp)],
            [Config::NAME, 'name', 5, 'integer'],
            [Config::NAME, 'name', $tmp = function() {}, Utils::getVariableType($tmp)],
            [Config::NAME, 'name', [], 'array'],
            [Config::NAME, 'name', new \stdClass(), 'stdClass'],
            [Config::NAME, 'name', '', null, 'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "" does not.'],
            [Config::NAME, 'name', '0', null, 'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "0" does not.'],
            [Config::NAME, 'name', '4abc', null, 'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "4abc" does not.'],
            [Config::NAME, 'name', 'specialCharsAreBad!', null, 'Names must match /^[_a-zA-Z][_a-zA-Z0-9]*$/ but "specialCharsAreBad!" does not.'],
            [Config::INPUT_TYPE, 'InputType definition', new ObjectType(['name' => 'test3', 'fields' => []]), 'test3'],
            [Config::INPUT_TYPE, 'InputType definition', '', 'string'],
            [Config::INPUT_TYPE, 'InputType definition', 'test', 'string'],
            [Config::INPUT_TYPE, 'InputType definition', 1, 'integer'],
            [Config::INPUT_TYPE, 'InputType definition', 0.5, 'double'],
            [Config::INPUT_TYPE, 'InputType definition', false, 'boolean'],
            [Config::INPUT_TYPE, 'InputType definition', [], 'array'],
            [Config::INPUT_TYPE, 'InputType definition', new \stdClass(), 'stdClass'],
            [Config::OUTPUT_TYPE, 'OutputType definition', new InputObjectType(['name' => 'InputTypeTest']), 'InputTypeTest'],
            [Config::OBJECT_TYPE, 'ObjectType definition', '', 'string'],
            [Config::OBJECT_TYPE, 'ObjectType definition', new InputObjectType(['name' => 'InputTypeTest2']), 'InputTypeTest2'],
            [Config::INTERFACE_TYPE, 'InterfaceType definition', new ObjectType(['name' => 'ObjectTypeTest']), 'ObjectTypeTest'],
            [Config::INTERFACE_TYPE, 'InterfaceType definition', 'InputTypeTest2', 'string'],
        ];
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($type, $typeLabel, $invalidValue, $actualTypeLabel = null, $expectedFullError = null)
    {
        if (!$expectedFullError) {
            $expectedFullError = $this->typeError(
                $invalidValue === null ?
                    'Value at "test" can not be null' :
                    'expecting "' . $typeLabel . '" at "test", but got "' . $actualTypeLabel . '"'
            );
        }

        $this->expectValidationThrows(
            ['test' => $invalidValue],
            ['test' => $type],
            $expectedFullError
        );
    }

    public function testErrorMessageContainsTypeName()
    {
        $this->expectValidationThrows(
            [
                'name' => 'TypeName',
                'test' => 'notInt'
            ],
            [
                'name' => Config::STRING | Config::REQUIRED,
                'test' => Config::INT
            ],
            $this->typeError('expecting "int" at "test", but got "string"', 'TypeName')
        );
    }

    public function testValidateField()
    {
        Config::enableValidation();

        // Should just validate:
        Config::validateField(
            'TypeName',
            ['test' => 'value'],
            ['test' => Config::STRING]
        );

        // Should include type name in error
        try {
            Config::validateField(
                'TypeName',
                ['test' => 'notInt'],
                ['test' => Config::INT]
            );
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals(
                $this->typeError('expecting "int" at "(Unknown Field):test", but got "string"', 'TypeName'),
                $e->getMessage()
            );
        }

        // Should include field type in error when field name is unknown:
        try {
            Config::validateField(
                'TypeName',
                ['type' => Type::string()],
                ['name' => Config::STRING | Config::REQUIRED, 'type' => Config::OUTPUT_TYPE]
            );
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals(
                $this->typeError('Required keys missing: "name"  at (Unknown Field of type: String)', 'TypeName'),
                $e->getMessage()
            );
        }

        // Should include field name in error when field name is set:
        try {
            Config::validateField(
                'TypeName',
                ['name' => 'fieldName', 'test' => 'notInt'],
                ['name' => Config::STRING, 'test' => Config::INT]
            );
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals(
                $this->typeError('expecting "int" at "test", but got "string"', 'TypeName'),
                $e->getMessage()
            );
        }
    }

    public function testAllowCustomOptions()
    {
        // Disabled by default when validation is enabled
        Config::enableValidation(true);

        Config::validate(
            ['test' => 'value', 'test2' => 'value'],
            ['test' => Config::STRING]
        );

        Config::enableValidation(false);

        try {
            Config::validate(
                ['test' => 'value', 'test2' => 'value'],
                ['test' => Config::STRING]
            );
            $this->fail('Expected exception not thrown');
        } catch (\PHPUnit_Framework_Error_Warning $e) {
            $this->assertEquals(
                $this->typeError('Non-standard keys "test2" '),
                $e->getMessage()
            );
        }
    }

    private function expectValidationPasses($config, $definition)
    {
        Config::enableValidation(false);
        Config::validate($config, $definition);
    }

    private function expectValidationThrows($config, $definition, $expectedError)
    {
        Config::enableValidation(false);
        try {
            Config::validate($config, $definition);
            $this->fail('Expected exception not thrown: ' . $expectedError);
        } catch (InvariantViolation $e) {
            $this->assertEquals($expectedError, $e->getMessage());
        }
    }

    private function typeError($err, $typeName = null)
    {
        return 'Error in "'. ($typeName ?: '(Unnamed Type)') . '" type definition: ' . $err;
    }
}
