<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Validator\DocumentValidator;

abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @return Schema
     */
    public static function getTestSchema()
    {
        $FurColor = null;

        $Being = new InterfaceType([
            'name' => 'Being',
            'fields' => [
                'name' => [
                    'type' => Type::string(),
                    'args' => [ 'surname' => [ 'type' => Type::boolean() ] ]
                ]
            ],
        ]);

        $Pet = new InterfaceType([
            'name' => 'Pet',
            'fields' => [
                'name' => [
                    'type' => Type::string(),
                    'args' => [ 'surname' => [ 'type' => Type::boolean() ] ]
                ]
            ],
        ]);

        $Canine = new InterfaceType([
            'name' => 'Canine',
            'fields' => function() {
                return [
                    'name' => [
                        'type' => Type::string(),
                        'args' => ['surname' => ['type' => Type::boolean()]]
                    ]
                ];
            }
        ]);

        $DogCommand = new EnumType([
            'name' => 'DogCommand',
            'values' => [
                'SIT' => ['value' => 0],
                'HEEL' => ['value' => 1],
                'DOWN' => ['value' => 2]
            ]
        ]);

        $Dog = new ObjectType([
            'name' => 'Dog',
            'fields' => [
                'name' => [
                    'type' => Type::string(),
                    'args' => [ 'surname' => [ 'type' => Type::boolean() ] ]
                ],
                'nickname' => ['type' => Type::string()],
                'barkVolume' => ['type' => Type::int()],
                'barks' => ['type' => Type::boolean()],
                'doesKnowCommand' => [
                    'type' => Type::boolean(),
                    'args' => ['dogCommand' => ['type' => $DogCommand]]
                ],
                'isHousetrained' => [
                    'type' => Type::boolean(),
                    'args' => ['atOtherHomes' => ['type' => Type::boolean(), 'defaultValue' => true]]
                ],
                'isAtLocation' => [
                    'type' => Type::boolean(),
                    'args' => ['x' => ['type' => Type::int()], 'y' => ['type' => Type::int()]]
                ]
            ],
            'interfaces' => [$Being, $Pet, $Canine]
        ]);

        $Cat = new ObjectType([
            'name' => 'Cat',
            'fields' => function() use (&$FurColor) {
                return [
                    'name' => [
                        'type' => Type::string(),
                        'args' => [ 'surname' => [ 'type' => Type::boolean() ] ]
                    ],
                    'nickname' => ['type' => Type::string()],
                    'meows' => ['type' => Type::boolean()],
                    'meowVolume' => ['type' => Type::int()],
                    'furColor' => $FurColor
                ];
            },
            'interfaces' => [$Being, $Pet]
        ]);

        $CatOrDog = new UnionType([
            'name' => 'CatOrDog',
            'types' => [$Dog, $Cat],
        ]);

        $Intelligent = new InterfaceType([
            'name' => 'Intelligent',
            'fields' => [
                'iq' => ['type' => Type::int()]
            ]
        ]);

        $Human = null;
        $Human = new ObjectType([
            'name' => 'Human',
            'interfaces' => [$Being, $Intelligent],
            'fields' => function() use (&$Human, $Pet) {
                return [
                    'name' => [
                        'type' => Type::string(),
                        'args' => ['surname' => ['type' => Type::boolean()]]
                    ],
                    'pets' => ['type' => Type::listOf($Pet)],
                    'relatives' => ['type' => Type::listOf($Human)],
                    'iq' => ['type' => Type::int()]
                ];
            }
        ]);

        $Alien = new ObjectType([
            'name' => 'Alien',
            'interfaces' => [$Being, $Intelligent],
            'fields' => [
                'iq' => ['type' => Type::int()],
                'name' => [
                    'type' => Type::string(),
                    'args' => ['surname' => ['type' => Type::boolean()]]
                ],
                'numEyes' => ['type' => Type::int()]
            ]
        ]);

        $DogOrHuman = new UnionType([
            'name' => 'DogOrHuman',
            'types' => [$Dog, $Human],
        ]);

        $HumanOrAlien = new UnionType([
            'name' => 'HumanOrAlien',
            'types' => [$Human, $Alien],
        ]);

        $FurColor = new EnumType([
            'name' => 'FurColor',
            'values' => [
                'BROWN' => [ 'value' => 0 ],
                'BLACK' => [ 'value' => 1 ],
                'TAN' => [ 'value' => 2 ],
                'SPOTTED' => [ 'value' => 3 ],
                'NO_FUR' => [ 'value' => null ],
            ],
        ]);

        $ComplexInput = new InputObjectType([
            'name' => 'ComplexInput',
            'fields' => [
                'requiredField' => ['type' => Type::nonNull(Type::boolean())],
                'intField' => ['type' => Type::int()],
                'stringField' => ['type' => Type::string()],
                'booleanField' => ['type' => Type::boolean()],
                'stringListField' => ['type' => Type::listOf(Type::string())]
            ]
        ]);

        $ComplicatedArgs = new ObjectType([
            'name' => 'ComplicatedArgs',
            // TODO List
            // TODO Coercion
            // TODO NotNulls
            'fields' => [
                'intArgField' => [
                    'type' => Type::string(),
                    'args' => ['intArg' => ['type' => Type::int()]],
                ],
                'nonNullIntArgField' => [
                    'type' => Type::string(),
                    'args' => [ 'nonNullIntArg' => [ 'type' => Type::nonNull(Type::int())]],
                ],
                'stringArgField' => [
                    'type' => Type::string(),
                    'args' => [ 'stringArg' => [ 'type' => Type::string()]],
                ],
                'booleanArgField' => [
                    'type' => Type::string(),
                    'args' => ['booleanArg' => [ 'type' => Type::boolean() ]],
                ],
                'enumArgField' => [
                    'type' => Type::string(),
                    'args' => [ 'enumArg' => ['type' => $FurColor ]],
                ],
                'floatArgField' => [
                    'type' => Type::string(),
                    'args' => [ 'floatArg' => [ 'type' => Type::float()]],
                ],
                'idArgField' => [
                    'type' => Type::string(),
                    'args' => [ 'idArg' => [ 'type' => Type::id() ]],
                ],
                'stringListArgField' => [
                    'type' => Type::string(),
                    'args' => [ 'stringListArg' => [ 'type' => Type::listOf(Type::string())]],
                ],
                'complexArgField' => [
                    'type' => Type::string(),
                    'args' => [ 'complexArg' => [ 'type' => $ComplexInput ]],
                ],
                'multipleReqs' => [
                    'type' => Type::string(),
                    'args' => [
                        'req1' => [ 'type' => Type::nonNull(Type::int())],
                        'req2' => [ 'type' => Type::nonNull(Type::int())],
                    ],
                ],
                'multipleOpts' => [
                    'type' => Type::string(),
                    'args' => [
                        'opt1' => [
                            'type' => Type::int(),
                            'defaultValue' => 0,
                        ],
                        'opt2' => [
                            'type' => Type::int(),
                            'defaultValue' => 0,
                        ],
                    ],
                ],
                'multipleOptAndReq' => [
                    'type' => Type::string(),
                    'args' => [
                        'req1' => [ 'type' => Type::nonNull(Type::int())],
                        'req2' => [ 'type' => Type::nonNull(Type::int())],
                        'opt1' => [
                            'type' => Type::int(),
                            'defaultValue' => 0,
                        ],
                        'opt2' => [
                            'type' => Type::int(),
                            'defaultValue' => 0,
                        ],
                    ],
                ],
            ]
        ]);

        $queryRoot = new ObjectType([
            'name' => 'QueryRoot',
            'fields' => [
                'human' => [
                    'args' => ['id' => ['type' => Type::id()]],
                    'type' => $Human
                ],
                'alien' => ['type' => $Alien],
                'dog' => ['type' => $Dog],
                'cat' => ['type' => $Cat],
                'pet' => ['type' => $Pet],
                'catOrDog' => ['type' => $CatOrDog],
                'dogOrHuman' => ['type' => $DogOrHuman],
                'humanOrAlien' => ['type' => $HumanOrAlien],
                'complicatedArgs' => ['type' => $ComplicatedArgs],
            ]
        ]);

        $testSchema = new Schema([
            'query' => $queryRoot,
            'directives' => [
                Directive::includeDirective(),
                Directive::skipDirective(),
                new Directive([
                    'name' => 'onQuery',
                    'locations' => ['QUERY'],
                ]),
                new Directive([
                    'name' => 'onMutation',
                    'locations' => ['MUTATION'],
                ]),
                new Directive([
                    'name' => 'onSubscription',
                    'locations' => ['SUBSCRIPTION'],
                ]),
                new Directive([
                    'name' => 'onField',
                    'locations' => ['FIELD'],
                ]),
                new Directive([
                    'name' => 'onFragmentDefinition',
                    'locations' => ['FRAGMENT_DEFINITION'],
                ]),
                new Directive([
                    'name' => 'onFragmentSpread',
                    'locations' => ['FRAGMENT_SPREAD'],
                ]),
                new Directive([
                    'name' => 'onInlineFragment',
                    'locations' => ['INLINE_FRAGMENT'],
                ]),
                new Directive([
                    'name' => 'onSchema',
                    'locations' => ['SCHEMA'],
                ]),
                new Directive([
                    'name' => 'onScalar',
                    'locations' => ['SCALAR'],
                ]),
                new Directive([
                    'name' => 'onObject',
                    'locations' => ['OBJECT'],
                ]),
                new Directive([
                    'name' => 'onFieldDefinition',
                    'locations' => ['FIELD_DEFINITION'],
                ]),
                new Directive([
                    'name' => 'onArgumentDefinition',
                    'locations' => ['ARGUMENT_DEFINITION'],
                ]),
                new Directive([
                    'name' => 'onInterface',
                    'locations' => ['INTERFACE'],
                ]),
                new Directive([
                    'name' => 'onUnion',
                    'locations' => ['UNION'],
                ]),
                new Directive([
                    'name' => 'onEnum',
                    'locations' => ['ENUM'],
                ]),
                new Directive([
                    'name' => 'onEnumValue',
                    'locations' => ['ENUM_VALUE'],
                ]),
                new Directive([
                    'name' => 'onInputObject',
                    'locations' => ['INPUT_OBJECT'],
                ]),
                new Directive([
                    'name' => 'onInputFieldDefinition',
                    'locations' => ['INPUT_FIELD_DEFINITION'],
                ]),
            ],
        ]);
        return $testSchema;
    }

    function expectValid($schema, $rules, $queryString)
    {
        $this->assertEquals(
            [],
            DocumentValidator::validate($schema, Parser::parse($queryString), $rules),
            'Should validate'
        );
    }

    function expectInvalid($schema, $rules, $queryString, $expectedErrors)
    {
        $errors = DocumentValidator::validate($schema, Parser::parse($queryString), $rules);

        $this->assertNotEmpty($errors, 'GraphQL should not validate');
        $this->assertEquals($expectedErrors, array_map(['GraphQL\Error\Error', 'formatError'], $errors));

        return $errors;
    }

    function expectPassesRule($rule, $queryString)
    {
        $this->expectValid($this->getTestSchema(), [$rule], $queryString);
    }

    function expectFailsRule($rule, $queryString, $errors)
    {
        return $this->expectInvalid($this->getTestSchema(), [$rule], $queryString, $errors);
    }

    function expectPassesRuleWithSchema($schema, $rule, $queryString)
    {
        $this->expectValid($schema, [$rule], $queryString);
    }

    function expectFailsRuleWithSchema($schema, $rule, $queryString, $errors)
    {
        $this->expectInvalid($schema, [$rule], $queryString, $errors);
    }

    function expectPassesCompleteValidation($queryString)
    {
        $this->expectValid($this->getTestSchema(), DocumentValidator::allRules(), $queryString);
    }

    function expectFailsCompleteValidation($queryString, $errors)
    {
        $this->expectInvalid($this->getTestSchema(), DocumentValidator::allRules(), $queryString, $errors);
    }
}
