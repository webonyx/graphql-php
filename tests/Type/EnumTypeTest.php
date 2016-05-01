<?php
namespace GraphQL\Tests\Type;

use GraphQL\Error;
use GraphQL\GraphQL;
use GraphQL\Schema;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class EnumTypeTest extends \PHPUnit_Framework_TestCase
{
    private $schema;

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
            GraphQL::execute($this->schema, '{ colorInt(fromEnum: GREEN) }')
        );
    }

    /**
     * @it enum may be output type
     */
    public function testEnumMayBeOutputType()
    {
        $this->assertEquals(
            ['data' => ['colorEnum' => 'GREEN']],
            GraphQL::execute($this->schema, '{ colorEnum(fromInt: 1) }')
        );
    }

    /**
     * @it enum may be both input and output type
     */
    public function testEnumMayBeBothInputAndOutputType()
    {
        $this->assertEquals(
            ['data' => ['colorEnum' => 'GREEN']],
            GraphQL::execute($this->schema, '{ colorEnum(fromEnum: GREEN) }')
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
            "Argument \"fromEnum\" has invalid value \"GREEN\".\nExpected type \"Color\", found \"GREEN\"."
        );
    }

    /**
     * @it does not accept incorrect internal value
     */
    public function testDoesNotAcceptIncorrectInternalValue()
    {
        $this->assertEquals(
            ['data' => ['colorEnum' => null]],
            GraphQL::execute($this->schema, '{ colorEnum(fromString: "GREEN") }')
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
            "Argument \"fromEnum\" has invalid value 1.\nExpected type \"Color\", found 1."
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
            "Argument \"fromInt\" has invalid value GREEN.\nExpected type \"Int\", found GREEN."
        );
    }

    /**
     * @it accepts JSON string as enum variable
     */
    public function testAcceptsJSONStringAsEnumVariable()
    {
        $this->assertEquals(
            ['data' => ['colorEnum' => 'BLUE']],
            GraphQL::execute(
                $this->schema,
                'query test($color: Color!) { colorEnum(fromEnum: $color) }',
                null,
                null,
                ['color' => 'BLUE']
            )
        );
    }

    /**
     * @it accepts enum literals as input arguments to mutations
     */
    public function testAcceptsEnumLiteralsAsInputArgumentsToMutations()
    {
        $this->assertEquals(
            ['data' => ['favoriteEnum' => 'GREEN']],
            GraphQL::execute(
                $this->schema,
                'mutation x($color: Color!) { favoriteEnum(color: $color) }',
                null,
                null,
                ['color' => 'GREEN']
            )
        );
    }

    /**
     * @it accepts enum literals as input arguments to subscriptions
     * @todo
     */
    public function testAcceptsEnumLiteralsAsInputArgumentsToSubscriptions()
    {
        $this->markTestIncomplete('Enable when subscription support is implemented');

        $this->assertEquals(
            ['data' => ['subscribeToEnum' => 'GREEN'], 'errors' => [[]]],
            GraphQL::execute(
                $this->schema,
                'subscription x($color: Color!) { subscribeToEnum(color: $color) }',
                null,
                null,
                ['color' => 'GREEN']
            )
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
            "Variable \"\$color\" got invalid value 2.\nExpected type \"Color\", found 2."
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
            GraphQL::execute($this->schema, "{
                colorEnum(fromEnum: RED)
                colorInt(fromEnum: RED)
            }")
        );
    }

    /**
     * @it enum inputs may be nullable
     */
    public function testEnumInputsMayBeNullable()
    {
        $this->assertEquals(
            ['data' => ['colorEnum' => null, 'colorInt' => null]],
            GraphQL::execute($this->schema, "{
                colorEnum
                colorInt
            }")
        );
    }

    private function expectFailure($query, $vars, $err)
    {
        $result = GraphQL::executeAndReturnResult($this->schema, $query, null, null, $vars);
        $this->assertEquals(1, count($result->errors));

        $this->assertEquals(
            $err,
            $result->errors[0]->getMessage()
        );
    }
}
