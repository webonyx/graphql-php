<?php
namespace GraphQL\Validator;

use GraphQL\Language\Parser;
use GraphQL\Schema;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;

abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    public $humanType;

    /**
     * @return Schema
     */
    protected function getDefaultSchema()
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

        $DogCommand = new EnumType([
            'name' => 'DogCommand',
            'values' => [
                'SIT' => ['value' => 0],
                'HEEL' => ['value' => 1],
                'DOWN' => ['value' => 3]
            ]
        ]);

        $Dog = new ObjectType([
            'name' => 'Dog',
            'isTypeOf' => function() {return true;},
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
            'interfaces' => [$Being, $Pet]
        ]);

        $Cat = new ObjectType([
            'name' => 'Cat',
            'isTypeOf' => function() {return true;},
            'fields' => [
                'name' => [
                    'type' => Type::string(),
                    'args' => [ 'surname' => [ 'type' => Type::boolean() ] ]
                ],
                'nickname' => ['type' => Type::string()],
                'meows' => ['type' => Type::boolean()],
                'meowVolume' => ['type' => Type::int()],
                'furColor' => ['type' => function() use (&$FurColor) {return $FurColor;}]
            ],
            'interfaces' => [$Being, $Pet]
        ]);

        $CatOrDog = new UnionType([
            'name' => 'CatOrDog',
            'types' => [$Dog, $Cat],
            'resolveType' => function($value) {
                // not used for validation
                return null;
            }
        ]);

        $Intelligent = new InterfaceType([
            'name' => 'Intelligent',
            'fields' => [
                'iq' => ['type' => Type::int()]
            ]
        ]);

        $Human = $this->humanType = new ObjectType([
            'name' => 'Human',
            'isTypeOf' => function() {return true;},
            'interfaces' => [$Being, $Intelligent],
            'fields' => [
                'name' => [
                    'type' => Type::string(),
                    'args' => ['surname' => ['type' => Type::boolean()]]
                ],
                'pets' => ['type' => Type::listOf($Pet)],
                'relatives' => ['type' => function() {return Type::listOf($this->humanType); }],
                'iq' => ['type' => Type::int()]
            ]
        ]);

        $Alien = new ObjectType([
            'name' => 'Alien',
            'isTypeOf' => function() {return true;},
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
            'resolveType' => function() {
                // not used for validation
                return null;
            }
        ]);

        $HumanOrAlien = new UnionType([
            'name' => 'HumanOrAlien',
            'types' => [$Human, $Alien],
            'resolveType' => function() {
                // not used for validation
                return null;
            }
        ]);

        $FurColor = new EnumType([
            'name' => 'FurColor',
            'values' => [
                'BROWN' => [ 'value' => 0 ],
                'BLACK' => [ 'value' => 1 ],
                'TAN' => [ 'value' => 2 ],
                'SPOTTED' => [ 'value' => 3 ],
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
                'complicatedArgs' => ['type' => $ComplicatedArgs]
            ]
        ]);

        $defaultSchema = new Schema($queryRoot);
        return $defaultSchema;
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
        $this->assertEquals($expectedErrors, array_map(['GraphQL\Error', 'formatError'], $errors));

        return $errors;
    }

    function expectPassesRule($rule, $queryString)
    {
        $this->expectValid($this->getDefaultSchema(), [$rule], $queryString);
    }

    function expectFailsRule($rule, $queryString, $errors)
    {
        return $this->expectInvalid($this->getDefaultSchema(), [$rule], $queryString, $errors);
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
        $this->expectValid($this->getDefaultSchema(), $this->getAllRules(), $queryString);
    }

    function expectFailsCompleteValidation($queryString, $errors)
    {
        $this->expectInvalid($this->getDefaultSchema(), $this->getAllRules(), $queryString, $errors);
    }
}
