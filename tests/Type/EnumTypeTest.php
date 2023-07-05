<?php

declare(strict_types=1);

namespace GraphQL\Tests\Type;

use ArrayObject;
use GraphQL\Error\DebugFlag;
use GraphQL\GraphQL;
use GraphQL\Language\SourceLocation;
use GraphQL\Tests\PHPUnit\ArraySubsetAsserts;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;
use function count;
use function is_array;

class EnumTypeTest extends TestCase
{
    use ArraySubsetAsserts;

    /** @var Schema */
    private $schema;

    /** @var EnumType */
    private $ComplexEnum;

    /** @var mixed[] */
    private $Complex1;

    /** @var ArrayObject */
    private $Complex2;

    public function setUp() : void
    {
        $ColorType = new EnumType([
            'name'   => 'Color',
            'values' => [
                'RED'   => ['value' => 0],
                'GREEN' => ['value' => 1],
                'BLUE'  => ['value' => 2],
            ],
        ]);

        $simpleEnum = new EnumType([
            'name'   => 'SimpleEnum',
            'values' => [
                'ONE',
                'TWO',
                'THREE',
            ],
        ]);

        $Complex1 = [
            'someRandomFunction' => static function () : void {
            },
        ];
        $Complex2 = new ArrayObject(['someRandomValue' => 123]);

        $ComplexEnum = new EnumType([
            'name'   => 'Complex',
            'values' => [
                'ONE' => ['value' => $Complex1],
                'TWO' => ['value' => $Complex2],
            ],
        ]);

        $Array1          = ['one', 'ONE'];
        $ArrayValuesEnum = new EnumType([
            'name' => 'ArrayValuesEnum',
            'values' => [
                'ONE' => ['value' => $Array1],
                'TWO' => ['value' => ['two', 'TWO']],
            ],
        ]);

        $QueryType = new ObjectType([
            'name'   => 'Query',
            'fields' => [
                'colorEnum'   => [
                    'type'    => $ColorType,
                    'args'    => [
                        'fromEnum'   => ['type' => $ColorType],
                        'fromInt'    => ['type' => Type::int()],
                        'fromString' => ['type' => Type::string()],
                    ],
                    'resolve' => static function ($rootValue, $args) {
                        if (isset($args['fromInt'])) {
                            return $args['fromInt'];
                        }
                        if (isset($args['fromString'])) {
                            return $args['fromString'];
                        }
                        if (isset($args['fromEnum'])) {
                            return $args['fromEnum'];
                        }
                    },
                ],
                'simpleEnum'  => [
                    'type'    => $simpleEnum,
                    'args'    => [
                        'fromName'  => ['type' => Type::string()],
                        'fromValue' => ['type' => Type::string()],
                    ],
                    'resolve' => static function ($rootValue, $args) {
                        if (isset($args['fromName'])) {
                            return $args['fromName'];
                        }
                        if (isset($args['fromValue'])) {
                            return $args['fromValue'];
                        }
                    },
                ],
                'colorInt'    => [
                    'type'    => Type::int(),
                    'args'    => [
                        'fromEnum' => ['type' => $ColorType],
                        'fromInt'  => ['type' => Type::int()],
                    ],
                    'resolve' => static function ($rootValue, $args) {
                        if (isset($args['fromInt'])) {
                            return $args['fromInt'];
                        }
                        if (isset($args['fromEnum'])) {
                            return $args['fromEnum'];
                        }
                    },
                ],
                'complexEnum' => [
                    'type'    => $ComplexEnum,
                    'args'    => [
                        'fromEnum'         => [
                            'type'         => $ComplexEnum,
                            // Note: defaultValue is provided an *internal* representation for
                            // Enums, rather than the string name.
                            'defaultValue' => $Complex1,
                        ],
                        'provideGoodValue' => [
                            'type' => Type::boolean(),
                        ],
                        'provideBadValue'  => [
                            'type' => Type::boolean(),
                        ],
                    ],
                    'resolve' => static function ($rootValue, $args) use ($Complex2) {
                        if ($args['provideGoodValue'] ?? false) {
                            // Note: this is one of the references of the internal values which
                            // ComplexEnum allows.
                            return $Complex2;
                        }
                        if ($args['provideBadValue'] ?? false) {
                            // Note: similar shape, but not the same *reference*
                            // as Complex2 above. Enum internal values require === equality.
                            return new ArrayObject(['someRandomValue' => 123]);
                        }

                        return $args['fromEnum'];
                    },
                ],
                'arrayValuesEnum' => [
                    'type' => $ArrayValuesEnum,
                    'args'    => [
                        'fromEnum'         => [
                            'type'         => $ArrayValuesEnum,
                            // Note: defaultValue is provided an *internal* representation for
                            // Enums, rather than the string name.
                            'defaultValue' => $Array1,
                        ],
                        'provideOneByReference' => [
                            'type' => Type::boolean(),
                        ],
                        'provideTwo'  => [
                            'type' => Type::boolean(),
                        ],
                    ],
                    'resolve' => static function ($rootValue, $args) use (&$Array1) {
                        if ($args['provideOneByReference'] ?? false) {
                            return $Array1;
                        }
                        if ($args['provideTwo'] ?? false) {
                            return ['two', 'TWO'];
                        }

                        return $args['fromEnum'];
                    },
                ],
            ],
        ]);

        $MutationType = new ObjectType([
            'name'   => 'Mutation',
            'fields' => [
                'favoriteEnum' => [
                    'type'    => $ColorType,
                    'args'    => ['color' => ['type' => $ColorType]],
                    'resolve' => static function ($rootValue, $args) {
                        return $args['color'] ?? null;
                    },
                ],
            ],
        ]);

        $SubscriptionType = new ObjectType([
            'name'   => 'Subscription',
            'fields' => [
                'subscribeToEnum' => [
                    'type'    => $ColorType,
                    'args'    => ['color' => ['type' => $ColorType]],
                    'resolve' => static function ($rootValue, $args) {
                        return $args['color'] ?? null;
                    },
                ],
            ],
        ]);

        $this->Complex1    = $Complex1;
        $this->Complex2    = $Complex2;
        $this->ComplexEnum = $ComplexEnum;

        $this->schema = new Schema([
            'query'        => $QueryType,
            'mutation'     => $MutationType,
            'subscription' => $SubscriptionType,
        ]);
    }

    // Describe: Type System: Enum Values

    /**
     * @see it('accepts enum literals as input')
     */
    public function testAcceptsEnumLiteralsAsInput() : void
    {
        self::assertEquals(
            ['data' => ['colorInt' => 1]],
            GraphQL::executeQuery($this->schema, '{ colorInt(fromEnum: GREEN) }')->toArray()
        );
    }

    /**
     * @see it('enum may be output type')
     */
    public function testEnumMayBeOutputType() : void
    {
        self::assertEquals(
            ['data' => ['colorEnum' => 'GREEN']],
            GraphQL::executeQuery($this->schema, '{ colorEnum(fromInt: 1) }')->toArray()
        );
    }

    /**
     * @see it('enum may be both input and output type')
     */
    public function testEnumMayBeBothInputAndOutputType() : void
    {
        self::assertEquals(
            ['data' => ['colorEnum' => 'GREEN']],
            GraphQL::executeQuery($this->schema, '{ colorEnum(fromEnum: GREEN) }')->toArray()
        );
    }

    /**
     * @see it('does not accept string literals')
     */
    public function testDoesNotAcceptStringLiterals() : void
    {
        $this->expectFailure(
            '{ colorEnum(fromEnum: "GREEN") }',
            null,
            [
                'message'   => 'Field "colorEnum" argument "fromEnum" requires type Color, found "GREEN"; Did you mean the enum value GREEN?',
                'locations' => [new SourceLocation(1, 23)],
            ]
        );
    }

    private function expectFailure($query, $vars, $err)
    {
        $result = GraphQL::executeQuery($this->schema, $query, null, null, $vars);
        self::assertCount(1, $result->errors);

        if (is_array($err)) {
            self::assertEquals(
                $err['message'],
                $result->errors[0]->getMessage()
            );
            self::assertEquals(
                $err['locations'],
                $result->errors[0]->getLocations()
            );
        } else {
            self::assertEquals(
                $err,
                $result->errors[0]->getMessage()
            );
        }
    }

    /**
     * @see it('does not accept valuesNotInTheEnum')
     */
    public function testDoesNotAcceptValuesNotInTheEnum() : void
    {
        $this->expectFailure(
            '{ colorEnum(fromEnum: GREENISH) }',
            null,
            [
                'message'   => 'Field "colorEnum" argument "fromEnum" requires type Color, found GREENISH; Did you mean the enum value GREEN?',
                'locations' => [new SourceLocation(1, 23)],
            ]
        );
    }

    /**
     * @see it('does not accept values with incorrect casing')
     */
    public function testDoesNotAcceptValuesWithIncorrectCasing() : void
    {
        $this->expectFailure(
            '{ colorEnum(fromEnum: green) }',
            null,
            [
                'message'   => 'Field "colorEnum" argument "fromEnum" requires type Color, found green; Did you mean the enum value GREEN?',
                'locations' => [new SourceLocation(1, 23)],
            ]
        );
    }

    /**
     * @see it('does not accept incorrect internal value')
     */
    public function testDoesNotAcceptIncorrectInternalValue() : void
    {
        $this->expectFailure(
            '{ colorEnum(fromString: "GREEN") }',
            null,
            [
                'message'   => 'Expected a value of type "Color" but received: GREEN',
                'locations' => [new SourceLocation(1, 3)],
                'path'      => ['colorEnum'],
            ]
        );
    }

    /**
     * @see it('does not accept internal value in place of enum literal')
     */
    public function testDoesNotAcceptInternalValueInPlaceOfEnumLiteral() : void
    {
        $this->expectFailure(
            '{ colorEnum(fromEnum: 1) }',
            null,
            'Field "colorEnum" argument "fromEnum" requires type Color, found 1.'
        );
    }

    /**
     * @see it('does not accept enum literal in place of int')
     */
    public function testDoesNotAcceptEnumLiteralInPlaceOfInt() : void
    {
        $this->expectFailure(
            '{ colorEnum(fromInt: GREEN) }',
            null,
            'Field "colorEnum" argument "fromInt" requires type Int, found GREEN.'
        );
    }

    /**
     * @see it('accepts JSON string as enum variable')
     */
    public function testAcceptsJSONStringAsEnumVariable() : void
    {
        self::assertEquals(
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
     * @see it('accepts enum literals as input arguments to mutations')
     */
    public function testAcceptsEnumLiteralsAsInputArgumentsToMutations() : void
    {
        self::assertEquals(
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
     * @see it('accepts enum literals as input arguments to subscriptions')
     *
     * @todo
     */
    public function testAcceptsEnumLiteralsAsInputArgumentsToSubscriptions() : void
    {
        self::assertEquals(
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
     * @see it('does not accept internal value as enum variable')
     */
    public function testDoesNotAcceptInternalValueAsEnumVariable() : void
    {
        $this->expectFailure(
            'query test($color: Color!) { colorEnum(fromEnum: $color) }',
            ['color' => 2],
            'Variable "$color" got invalid value 2; Expected type Color.'
        );
    }

    /**
     * @see it('does not accept string variables as enum input')
     */
    public function testDoesNotAcceptStringVariablesAsEnumInput() : void
    {
        $this->expectFailure(
            'query test($color: String!) { colorEnum(fromEnum: $color) }',
            ['color' => 'BLUE'],
            'Variable "$color" of type "String!" used in position expecting type "Color".'
        );
    }

    /**
     * @see it('does not accept internal value variable as enum input')
     */
    public function testDoesNotAcceptInternalValueVariableSsEnumInput() : void
    {
        $this->expectFailure(
            'query test($color: Int!) { colorEnum(fromEnum: $color) }',
            ['color' => 2],
            'Variable "$color" of type "Int!" used in position expecting type "Color".'
        );
    }

    /**
     * @see it('enum value may have an internal value of 0')
     */
    public function testEnumValueMayHaveAnInternalValueOf0() : void
    {
        self::assertEquals(
            ['data' => ['colorEnum' => 'RED', 'colorInt' => 0]],
            GraphQL::executeQuery(
                $this->schema,
                '{
                colorEnum(fromEnum: RED)
                colorInt(fromEnum: RED)
            }'
            )->toArray()
        );
    }

    /**
     * @see it('enum inputs may be nullable')
     */
    public function testEnumInputsMayBeNullable() : void
    {
        self::assertEquals(
            ['data' => ['colorEnum' => null, 'colorInt' => null]],
            GraphQL::executeQuery(
                $this->schema,
                '{
                colorEnum
                colorInt
            }'
            )->toArray()
        );
    }

    /**
     * @see it('presents a getValues() API for complex enums')
     */
    public function testPresentsGetValuesAPIForComplexEnums() : void
    {
        $ComplexEnum = $this->ComplexEnum;
        $values      = $ComplexEnum->getValues();

        self::assertEquals(2, count($values));
        self::assertEquals('ONE', $values[0]->name);
        self::assertEquals($this->Complex1, $values[0]->value);
        self::assertEquals('TWO', $values[1]->name);
        self::assertEquals($this->Complex2, $values[1]->value);
    }

    /**
     * @see it('presents a getValue() API for complex enums')
     */
    public function testPresentsGetValueAPIForComplexEnums() : void
    {
        $oneValue = $this->ComplexEnum->getValue('ONE');
        self::assertEquals('ONE', $oneValue->name);
        self::assertEquals($this->Complex1, $oneValue->value);

        $badUsage = $this->ComplexEnum->getValue($this->Complex1);
        self::assertEquals(null, $badUsage);
    }

    /**
     * @see it('may be internally represented with complex values')
     */
    public function testMayBeInternallyRepresentedWithComplexValues() : void
    {
        $result = GraphQL::executeQuery(
            $this->schema,
            '{
        first: complexEnum
        second: complexEnum(fromEnum: TWO)
        good: complexEnum(provideGoodValue: true)
        bad: complexEnum(provideBadValue: true)
        }'
        )->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE);

        $expected = [
            'data'   => [
                'first'  => 'ONE',
                'second' => 'TWO',
                'good'   => 'TWO',
                'bad'    => null,
            ],
            'errors' => [
                [
                    'debugMessage' =>
                    'Expected a value of type "Complex" but received: instance of ArrayObject',
                    'locations'    => [['line' => 5, 'column' => 9]],
                ],
            ],
        ];

        self::assertArraySubset($expected, $result);
    }

    public function testMayBeInternallyRepresentedWithArrayValues() : void
    {
        $result = GraphQL::executeQuery(
            $this->schema,
            '{
                defaultValue: arrayValuesEnum
                fromName: arrayValuesEnum(fromEnum: TWO)
                oneRef: arrayValuesEnum(provideOneByReference: true)
                two: arrayValuesEnum(provideTwo: true)
            }'
        )->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE);

        $expected = [
            'data'   => [
                'defaultValue'  => 'ONE',
                'fromName' => 'TWO',
                'oneRef'   => 'ONE',
                'two'    => 'TWO',
            ],
        ];

        self::assertEquals($expected, $result);
    }

    /**
     * @see it('can be introspected without error')
     */
    public function testCanBeIntrospectedWithoutError() : void
    {
        $result = GraphQL::executeQuery($this->schema, Introspection::getIntrospectionQuery())->toArray();
        self::assertArrayNotHasKey('errors', $result);
    }

    public function testAllowsSimpleArrayAsValues() : void
    {
        $q = '{
            first: simpleEnum(fromName: "ONE")
            second: simpleEnum(fromValue: "TWO")
            third: simpleEnum(fromValue: "WRONG")
        }';

        self::assertArraySubset(
            [
                'data'   => ['first' => 'ONE', 'second' => 'TWO', 'third' => null],
                'errors' => [
                    [
                        'debugMessage' => 'Expected a value of type "SimpleEnum" but received: WRONG',
                        'locations'    => [['line' => 4, 'column' => 13]],
                    ],
                ],
            ],
            GraphQL::executeQuery($this->schema, $q)->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE)
        );
    }
}
