<?php
namespace GraphQL\Tests\Executor;

require_once __DIR__ . '/TestClasses.php';

use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Executor;
use GraphQL\GraphQL;
use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;

class AbstractTest extends \PHPUnit_Framework_TestCase
{
    // Execute: Handles execution of abstract types

    /**
     * @it isTypeOf used to resolve runtime type for Interface
     */
    public function testIsTypeOfUsedToResolveRuntimeTypeForInterface()
    {
        // isTypeOf used to resolve runtime type for Interface
        $petType = new InterfaceType([
            'name' => 'Pet',
            'fields' => [
                'name' => ['type' => Type::string()]
            ]
        ]);

        // Added to interface type when defined
        $dogType = new ObjectType([
            'name' => 'Dog',
            'interfaces' => [$petType],
            'isTypeOf' => function($obj) { return $obj instanceof Dog; },
            'fields' => [
                'name' => ['type' => Type::string()],
                'woofs' => ['type' => Type::boolean()]
            ]
        ]);

        $catType = new ObjectType([
            'name' => 'Cat',
            'interfaces' => [$petType],
            'isTypeOf' => function ($obj) {
                return $obj instanceof Cat;
            },
            'fields' => [
                'name' => ['type' => Type::string()],
                'meows' => ['type' => Type::boolean()],
            ]
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => Type::listOf($petType),
                        'resolve' => function () {
                            return [new Dog('Odie', true), new Cat('Garfield', false)];
                        }
                    ]
                ]
            ]),
            'types' => [$catType, $dogType]
        ]);

        $query = '{
          pets {
            name
            ... on Dog {
              woofs
            }
            ... on Cat {
              meows
            }
          }
        }';

        $expected = new ExecutionResult([
            'pets' => [
                ['name' => 'Odie', 'woofs' => true],
                ['name' => 'Garfield', 'meows' => false]
            ]
        ]);

        $result = Executor::execute($schema, Parser::parse($query));
        $this->assertEquals($expected, $result);
    }

    /**
     * @it isTypeOf used to resolve runtime type for Union
     */
    public function testIsTypeOfUsedToResolveRuntimeTypeForUnion()
    {
        $dogType = new ObjectType([
            'name' => 'Dog',
            'isTypeOf' => function($obj) { return $obj instanceof Dog; },
            'fields' => [
                'name' => ['type' => Type::string()],
                'woofs' => ['type' => Type::boolean()]
            ]
        ]);

        $catType = new ObjectType([
            'name' => 'Cat',
            'isTypeOf' => function ($obj) {
                return $obj instanceof Cat;
            },
            'fields' => [
                'name' => ['type' => Type::string()],
                'meows' => ['type' => Type::boolean()],
            ]
        ]);

        $petType = new UnionType([
            'name' => 'Pet',
            'types' => [$dogType, $catType]
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => Type::listOf($petType),
                        'resolve' => function() {
                            return [ new Dog('Odie', true), new Cat('Garfield', false) ];
                        }
                    ]
                ]
            ])
        ]);

        $query = '{
          pets {
            name
            ... on Dog {
              woofs
            }
            ... on Cat {
              meows
            }
          }
        }';

        $expected = new ExecutionResult([
            'pets' => [
                ['name' => 'Odie', 'woofs' => true],
                ['name' => 'Garfield', 'meows' => false]
            ]
        ]);

        $this->assertEquals($expected, Executor::execute($schema, Parser::parse($query)));
    }

    /**
     * @it resolveType on Interface yields useful error
     */
    function testResolveTypeOnInterfaceYieldsUsefulError()
    {
        $DogType = null;
        $CatType = null;
        $HumanType = null;

        $PetType = new InterfaceType([
            'name' => 'Pet',
            'resolveType' => function ($obj) use (&$DogType, &$CatType, &$HumanType) {
                if ($obj instanceof Dog) {
                    return $DogType;
                }
                if ($obj instanceof Cat) {
                    return $CatType;
                }
                if ($obj instanceof Human) {
                    return $HumanType;
                }
                return null;
            },
            'fields' => [
                'name' => ['type' => Type::string()]
            ]
        ]);

        $HumanType = new ObjectType([
            'name' => 'Human',
            'fields' => [
                'name' => ['type' => Type::string()],
            ]
        ]);

        $DogType = new ObjectType([
            'name' => 'Dog',
            'interfaces' => [$PetType],
            'fields' => [
                'name' => ['type' => Type::string()],
                'woofs' => ['type' => Type::boolean()],
            ]
        ]);

        $CatType = new ObjectType([
            'name' => 'Cat',
            'interfaces' => [$PetType],
            'fields' => [
                'name' => ['type' => Type::string()],
                'meows' => ['type' => Type::boolean()],
            ]
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => Type::listOf($PetType),
                        'resolve' => function () {
                            return [
                                new Dog('Odie', true),
                                new Cat('Garfield', false),
                                new Human('Jon')
                            ];
                        }
                    ]
                ],
            ]),
            'types' => [$DogType, $CatType]
        ]);


        $query = '{
          pets {
            name
            ... on Dog {
              woofs
            }
            ... on Cat {
              meows
            }
          }
        }';

        $expected = [
            'data' => [
                'pets' => [
                    ['name' => 'Odie', 'woofs' => true],
                    ['name' => 'Garfield', 'meows' => false],
                    null
                ]
            ],
            'errors' => [[
                'debugMessage' => 'Runtime Object type "Human" is not a possible type for "Pet".',
                'locations' => [['line' => 2, 'column' => 11]],
                'path' => ['pets', 2]
            ]]
        ];
        $actual = GraphQL::executeAndReturnResult($schema, $query)->toArray(true);

        $this->assertArraySubset($expected, $actual);
    }

    /**
     * @it resolveType on Union yields useful error
     */
    public function testResolveTypeOnUnionYieldsUsefulError()
    {
        $HumanType = new ObjectType([
            'name' => 'Human',
            'fields' => [
                'name' => ['type' => Type::string()],
            ]
        ]);

        $DogType = new ObjectType([
            'name' => 'Dog',
            'fields' => [
                'name' => ['type' => Type::string()],
                'woofs' => ['type' => Type::boolean()],
            ]
        ]);

        $CatType = new ObjectType([
            'name' => 'Cat',
            'fields' => [
                'name' => ['type' => Type::string()],
                'meows' => ['type' => Type::boolean()],
            ]
        ]);

        $PetType = new UnionType([
            'name' => 'Pet',
            'resolveType' => function ($obj) use ($DogType, $CatType, $HumanType) {
                if ($obj instanceof Dog) {
                    return $DogType;
                }
                if ($obj instanceof Cat) {
                    return $CatType;
                }
                if ($obj instanceof Human) {
                    return $HumanType;
                }
            },
            'types' => [$DogType, $CatType]
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => Type::listOf($PetType),
                        'resolve' => function () {
                            return [
                                new Dog('Odie', true),
                                new Cat('Garfield', false),
                                new Human('Jon')
                            ];
                        }
                    ]
                ]
            ])
        ]);

        $query = '{
          pets {
            ... on Dog {
              name
              woofs
            }
            ... on Cat {
              name
              meows
            }
          }
        }';

        $result = GraphQL::executeAndReturnResult($schema, $query)->toArray(true);
        $expected = [
            'data' => [
                'pets' => [
                    ['name' => 'Odie',
                        'woofs' => true],
                    ['name' => 'Garfield',
                        'meows' => false],
                    null
                ]
            ],
            'errors' => [[
                'debugMessage' => 'Runtime Object type "Human" is not a possible type for "Pet".',
                'locations' => [['line' => 2, 'column' => 11]],
                'path' => ['pets', 2]
            ]]
        ];
        $this->assertArraySubset($expected, $result);
    }

    /**
     * @it resolveType allows resolving with type name
     */
    public function testResolveTypeAllowsResolvingWithTypeName()
    {
        $PetType = new InterfaceType([
            'name' => 'Pet',
            'resolveType' => function($obj) {
                if ($obj instanceof Dog) return 'Dog';
                if ($obj instanceof Cat) return 'Cat';
                return null;
            },
            'fields' => [
                'name' => [ 'type' => Type::string() ]
            ]
        ]);

        $DogType = new ObjectType([
            'name' => 'Dog',
            'interfaces' => [ $PetType ],
            'fields' => [
                'name' => [ 'type' => Type::string() ],
                'woofs' => [ 'type' => Type::boolean() ],
            ]
        ]);

        $CatType = new ObjectType([
            'name' => 'Cat',
            'interfaces' => [ $PetType ],
            'fields' => [
                'name' => [ 'type' => Type::string() ],
                'meows' => [ 'type' => Type::boolean() ],
            ]
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => Type::listOf($PetType),
                        'resolve' => function() {
                            return [
                                new Dog('Odie', true),
                                new Cat('Garfield', false)
                            ];
                        }
                    ]
                ]
            ]),
            'types' => [ $CatType, $DogType ]
        ]);

        $query = '{
          pets {
            name
            ... on Dog {
              woofs
            }
            ... on Cat {
              meows
            }
          }
        }';

        $result = GraphQL::execute($schema, $query);

        $this->assertEquals([
            'data' => [
                'pets' => [
                    ['name' => 'Odie', 'woofs' => true],
                    ['name' => 'Garfield', 'meows' => false]
                ]
            ]
        ], $result);
    }

    public function testHintsOnConflictingTypeInstancesInResolveType()
    {
        $createTest = function() use (&$iface) {
            return new ObjectType([
                'name' => 'Test',
                'fields' => [
                    'a' => Type::string()
                ],
                'interfaces' => function() use ($iface) {
                    return [$iface];
                }
            ]);
        };

        $iface = new InterfaceType([
            'name' => 'Node',
            'fields' => [
                'a' => Type::string()
            ],
            'resolveType' => function() use (&$createTest) {
                return $createTest();
            }
        ]);

        $query = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'node' => $iface,
                'test' => $createTest()
            ]
        ]);

        $schema = new Schema([
            'query' => $query,
        ]);
        $schema->assertValid();

        $query = '
            {
                node {
                    a
                }
            }
        ';

        $result = Executor::execute($schema, Parser::parse($query), ['node' => ['a' => 'value']]);

        $this->assertEquals(
            'Schema must contain unique named types but contains multiple types named "Test". '.
            'Make sure that `resolveType` function of abstract type "Node" returns the same type instance '.
            'as referenced anywhere else within the schema '.
            '(see http://webonyx.github.io/graphql-php/type-system/#type-registry).',
            $result->errors[0]->getMessage()
        );
    }
}
