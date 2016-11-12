<?php
namespace GraphQL\Tests\Executor;

require_once __DIR__ . '/TestClasses.php';

use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Executor;
use GraphQL\Error\FormattedError;
use GraphQL\GraphQL;
use GraphQL\Language\Lexer;
use GraphQL\Language\Parser;
use GraphQL\Language\SourceLocation;
use GraphQL\Schema;
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

        $parser = new Parser(new Lexer());
        $this->assertEquals($expected, Executor::execute($schema, $parser->parse($query)));
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

        $parser = new Parser(new Lexer());
        $this->assertEquals($expected, Executor::execute($schema, $parser->parse($query)));
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
            'errors' => [
                FormattedError::create(
                    'Runtime Object type "Human" is not a possible type for "Pet".',
                    [new SourceLocation(2, 11)]
                )
            ]
        ];
        $actual = GraphQL::execute($schema, $query);

        $this->assertEquals($expected, $actual);
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

        $result = GraphQL::execute($schema, $query);
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
            'errors' => [
                FormattedError::create(
                    'Runtime Object type "Human" is not a possible type for "Pet".',
                    [new SourceLocation(2, 11)]
                )
            ]
        ];
        $this->assertEquals($expected, $result);
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
}
