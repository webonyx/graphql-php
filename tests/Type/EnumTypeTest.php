<?php
namespace GraphQL\Tests\Type;

use GraphQL\GraphQL;
use GraphQL\Language\SourceLocation;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Introspection;

class EnumTypeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Schema
     */
    private $schema;

    /**
     * @var EnumType
     */
    private $ComplexEnum;

    private $Complex1;

    private $Complex2;

    public function setUp()
    {
        $ColorType = new EnumType([
            'name' => 'Color',
            'values' => [
                'RED' => ['value' => 0],
                'GREEN' => ['value' => 1],
                'BLUE' => ['value' => 2],
            ]
        ]);

        $simpleEnum = new EnumType([
            'name' => 'SimpleEnum',
            'values' => [
                'ONE', 'TWO', 'THREE'
            ]
        ]);

        $Complex1 = ['someRandomFunction' => function() {}];
        $Complex2 = new \ArrayObject(['someRandomValue' => 123]);

        $ComplexEnum = new EnumType([
            'name' => 'Complex',
            'values' => [
                'ONE' => ['value' => $Complex1],
                'TWO' => ['value' => $Complex2]
            ]
        ]);

        $QueryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'colorEnum' => [
                    'type' => $ColorType,
                    'args' => [
                        'fromEnum' => ['type' => $ColorType],
                        'fromInt' => ['type' => Type::int()],
                        'fromString' => ['type' => Type::string()],
                    ],
                    'resolve' => function ($value, $args) {
                        if (isset($args['fromInt'])) {
                            return $args['fromInt'];
                        }
                        if (isset($args['fromString'])) {
                            return $args['fromString'];
                        }
                        if (isset($args['fromEnum'])) {
                            return $args['fromEnum'];
                        }
                    }
                ],
                'simpleEnum' => [
                    'type' => $simpleEnum,
                    'args' => [
                        'fromName' => ['type' => Type::string()],
                        'fromValue' => ['type' => Type::string()]
                    ],
                    'resolve' => function($value, $args) {
                        if (isset($args['fromName'])) {
                            return $args['fromName'];
                        }
                        if (isset($args['fromValue'])) {
                            return $args['fromValue'];
                        }
                    }
                ],
                'colorInt' => [
                    'type' => Type::int(),
                    'args' => [
                        'fromEnum' => ['type' => $ColorType],
                        'fromInt' => ['type' => Type::int()],
                    ],
                    'resolve' => function ($value, $args) {
                        if (isset($args['fromInt'])) {
                            return $args['fromInt'];
                        }
                        if (isset($args['fromEnum'])) {
                            return $args['fromEnum'];
                        }
                    }
                ],
                'complexEnum' => [
                    'type' => $ComplexEnum,
                    'args' => [
                        'fromEnum' => [
                            'type' => $ComplexEnum,
                            // Note: defaultValue is provided an *internal* representation for
                            // Enums, rather than the string name.
                            'defaultValue' => $Complex1
                        ],
                        'provideGoodValue' => [
                            'type' => Type::boolean(),
                        ],
                        'provideBadValue' => [
                            'type' => Type::boolean()
                        ]
                    ],
                    'resolve' => function($value, $args) use ($Complex1, $Complex2) {
                        if (!empty($args['provideGoodValue'])) {
                            // Note: this is one of the references of the internal values which
                            // ComplexEnum allows.
                            return $Complex2;
                        }
                        if (!empty($args['provideBadValue'])) {
                            // Note: similar shape, but not the same *reference*
                            // as Complex2 above. Enum internal values require === equality.
                            return new \ArrayObject(['someRandomValue' => 123]);
                        }
                        return $args['fromEnum'];
                    }
                ]
            ]
        ]);

        $MutationType = new ObjectType([
            'name' => 'Mutation',
            'fields' => [
                'favoriteEnum' => [
                    'type' => $ColorType,
                    'args' => ['color' => ['type' => $ColorType]],
                    'resolve' => function ($value, $args) {
                        return isset($args['color']) ? $args['color'] : null;
                    }
                ]
            ]
        ]);

        $SubscriptionType = new ObjectType([
            'name' => 'Subscription',
            'fields' => [
                'subscribeToEnum' => [
                    'type' => $ColorType,
                    'args' => ['color' => ['type' => $ColorType]],
                    'resolve' => function ($value, $args) {
                        return isset($args['color']) ? $args['color'] : null;
                    }
                ]
            ]
        ]);

        $this->Complex1 = $Complex1;
        $this->Complex2 = $Complex2;
        $this->ComplexEnum = $ComplexEnum;

        $this->schema = new Schema([
            'query' => $QueryType,
            'mutation' => $MutationType,
            'subscription' => $SubscriptionType
        ]);
    }

    // Describe: Type System: Enum Values

    /**
     * @it accepts enum literals as input
     */
    public function testAcceptsEnumLiteralsAsInput()
    {
        $this->assertEquals(
            ['data' => ['colorInt' => 1]],
            GraphQL::executeQuery($this->schema, '{ colorInt(fromEnum: GREEN) }')->toArray()
        );
    }

    /**
     * @it enum may be output type
     */
    public function testEnumMayBeOutputType()
    {
        $this->assertEquals(
            ['data' => ['colorEnum' => 'GREEN']],
            GraphQL::executeQuery($this->schema, '{ colorEnum(fromInt: 1) }')->toArray()
        );
    }

    /**
     * @it enum may be both input and output type
     */
    public function testEnumMayBeBothInputAndOutputType()
    {
        $this->assertEquals(
            ['data' => ['colorEnum' => 'GREEN']],
            GraphQL::executeQuery($this->schema, '{ colorEnum(fromEnum: GREEN) }')->toArray()
        );
    }

    /**
     * @it does not accept string literals
     */
    public function testDoesNotAcceptStringLiterals()
    {
        $this->expectFailure(
            '{ colorEnum(fromEnum: "GREEN") }',
            null,
            [
                'message' => "Expected type Color, found \"GREEN\"; Did you mean the enum value GREEN?",
                'locations' => [new SourceLocation(1, 23)]
            ]
        );
    }

    /**
     * @it does not accept valuesNotInTheEnum
     */
    public function testDoesNotAcceptValuesNotInTheEnum()
    {
        $this->expectFailure(
            '{ colorEnum(fromEnum: GREENISH) }',
            null,
            [
                'message' => "Expected type Color, found GREENISH; Did you mean the enum value GREEN?",
                'locations' => [new SourceLocation(1, 23)]
            ]
        );
    }

    /**
     * @it does not accept values with incorrect casing
     */
    public function testDoesNotAcceptValuesWithIncorrectCasing()
    {
        $this->expectFailure(
            '{ colorEnum(fromEnum: green) }',
            null,
            [
                'message' => "Expected type Color, found green; Did you mean the enum value GREEN?",
                'locations' => [new SourceLocation(1, 23)]
            ]
        );
    }

    /**
     * @it does not accept incorrect internal value
     */
    public function testDoesNotAcceptIncorrectInternalValue()
    {
        $this->expectFailure(
            '{ colorEnum(fromString: "GREEN") }',
            null,
            [
                'message' => 'Expected a value of type "Color" but received: GREEN',
                'locations' => [new SourceLocation(1, 3)],
                'path' => ['colorEnum'],
            ]
        );
    }

    /**
     * @it does not accept internal value in place of enum literal
     */
    public function testDoesNotAcceptInternalValueInPlaceOfEnumLiteral()
    {
        $this->expectFailure(
            '{ colorEnum(fromEnum: 1) }',
            null,
            "Expected type Color, found 1."
        );
    }

    /**
     * @it does not accept enum literal in place of int
     */
    public function testDoesNotAcceptEnumLiteralInPlaceOfInt()
    {
        $this->expectFailure(
            '{ colorEnum(fromInt: GREEN) }',
            null,
            "Expected type Int, found GREEN."
        );
    }

    /**
     * @it accepts JSON string as enum variable
     */
    public function testAcceptsJSONStringAsEnumVariable()
    {
        $this->assertEquals(
            ['data' => ['colorEnum' => 'BLUE']],
            GraphQL::executeQuery(
                $this->schema,
                'query test($color: Color!) { colorEnum(fromEnum: $color) }',
                null,
                null,
                ['color' => 'BLUE']
            )->toArray()
        );
    }

    /**
     * @it accepts enum literals as input arguments to mutations
     */
    public function testAcceptsEnumLiteralsAsInputArgumentsToMutations()
    {
        $this->assertEquals(
            ['data' => ['favoriteEnum' => 'GREEN']],
            GraphQL::executeQuery(
                $this->schema,
                'mutation x($color: Color!) { favoriteEnum(color: $color) }',
                null,
                null,
                ['color' => 'GREEN']
            )->toArray()
        );
    }

    /**
     * @it accepts enum literals as input arguments to subscriptions
     * @todo
     */
    public function testAcceptsEnumLiteralsAsInputArgumentsToSubscriptions()
    {
        $this->assertEquals(
            ['data' => ['subscribeToEnum' => 'GREEN']],
            GraphQL::executeQuery(
                $this->schema,
                'subscription x($color: Color!) { subscribeToEnum(color: $color) }',
                null,
                null,
                ['color' => 'GREEN']
            )->toArray()
        );
    }

    /**
     * @it does not accept internal value as enum variable
     */
    public function testDoesNotAcceptInternalValueAsEnumVariable()
    {
        $this->expectFailure(
            'query test($color: Color!) { colorEnum(fromEnum: $color) }',
            ['color' => 2],
            'Variable "$color" got invalid value 2; Expected type Color.'
        );
    }

    /**
     * @it does not accept string variables as enum input
     */
    public function testDoesNotAcceptStringVariablesAsEnumInput()
    {
        $this->expectFailure(
            'query test($color: String!) { colorEnum(fromEnum: $color) }',
            ['color' => 'BLUE'],
            'Variable "$color" of type "String!" used in position expecting type "Color".'
        );
    }

    /**
     * @it does not accept internal value variable as enum input
     */
    public function testDoesNotAcceptInternalValueVariableSsEnumInput()
    {
        $this->expectFailure(
            'query test($color: Int!) { colorEnum(fromEnum: $color) }',
            ['color' => 2],
            'Variable "$color" of type "Int!" used in position ' . 'expecting type "Color".'
        );
    }

    /**
     * @it enum value may have an internal value of 0
     */
    public function testEnumValueMayHaveAnInternalValueOf0()
    {
        $this->assertEquals(
            ['data' => ['colorEnum' => 'RED', 'colorInt' => 0]],
            GraphQL::executeQuery($this->schema, "{
                colorEnum(fromEnum: RED)
                colorInt(fromEnum: RED)
            }")->toArray()
        );
    }

    /**
     * @it enum inputs may be nullable
     */
    public function testEnumInputsMayBeNullable()
    {
        $this->assertEquals(
            ['data' => ['colorEnum' => null, 'colorInt' => null]],
            GraphQL::executeQuery($this->schema, "{
                colorEnum
                colorInt
            }")->toArray()
        );
    }

    /**
     * @it presents a getValues() API for complex enums
     */
    public function testPresentsGetValuesAPIForComplexEnums()
    {
        $ComplexEnum = $this->ComplexEnum;
        $values = $ComplexEnum->getValues();

        $this->assertEquals(2, count($values));
        $this->assertEquals('ONE', $values[0]->name);
        $this->assertEquals($this->Complex1, $values[0]->value);
        $this->assertEquals('TWO', $values[1]->name);
        $this->assertEquals($this->Complex2, $values[1]->value);
    }

    /**
     * @it presents a getValue() API for complex enums
     */
    public function testPresentsGetValueAPIForComplexEnums()
    {
        $oneValue = $this->ComplexEnum->getValue('ONE');
        $this->assertEquals('ONE', $oneValue->name);
        $this->assertEquals($this->Complex1, $oneValue->value);

        $badUsage = $this->ComplexEnum->getValue($this->Complex1);
        $this->assertEquals(null, $badUsage);
    }

    /**
     * @it may be internally represented with complex values
     */
    public function testMayBeInternallyRepresentedWithComplexValues()
    {
        $result = GraphQL::executeQuery($this->schema, '{
        first: complexEnum
        second: complexEnum(fromEnum: TWO)
        good: complexEnum(provideGoodValue: true)
        bad: complexEnum(provideBadValue: true)
        }')->toArray(true);

        $expected = [
            'data' => [
                'first' => 'ONE',
                'second' => 'TWO',
                'good' => 'TWO',
                'bad' => null
            ],
            'errors' => [[
                'debugMessage' =>
                    'Expected a value of type "Complex" but received: instance of ArrayObject',
                'locations' => [['line' => 5, 'column' => 9]]
            ]]
        ];

        $this->assertArraySubset($expected, $result);
    }

    /**
     * @it can be introspected without error
     */
    public function testCanBeIntrospectedWithoutError()
    {
        $result = GraphQL::executeQuery($this->schema, Introspection::getIntrospectionQuery())->toArray();
        $this->assertArrayNotHasKey('errors', $result);
    }

    public function testAllowsSimpleArrayAsValues()
    {
        $q = '{
            first: simpleEnum(fromName: "ONE")
            second: simpleEnum(fromValue: "TWO")
            third: simpleEnum(fromValue: "WRONG")
        }';

        $this->assertArraySubset(
            [
                'data' => ['first' => 'ONE', 'second' => 'TWO', 'third' => null],
                'errors' => [[
                    'debugMessage' => 'Expected a value of type "SimpleEnum" but received: WRONG',
                    'locations' => [['line' => 4, 'column' => 13]]
                ]]
            ],
            GraphQL::executeQuery($this->schema, $q)->toArray(true)
        );
    }

    private function expectFailure($query, $vars, $err)
    {
        $result = GraphQL::executeQuery($this->schema, $query, null, null, $vars);
        $this->assertEquals(1, count($result->errors));

        if (is_array($err)) {
            $this->assertEquals(
                $err['message'],
                $result->errors[0]->getMessage()
            );
            $this->assertEquals(
                $err['locations'],
                $result->errors[0]->getLocations()
            );
        } else {
            $this->assertEquals(
                $err,
                $result->errors[0]->getMessage()
            );
        }
    }
}
