<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Executor;
use GraphQL\GraphQL;
use GraphQL\Language\Parser;
use GraphQL\Tests\Executor\TestClasses\Cat;
use GraphQL\Tests\Executor\TestClasses\Dog;
use GraphQL\Tests\Executor\TestClasses\Human;
use GraphQL\Tests\PHPUnit\ArraySubsetAsserts;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

/**
 * Execute: Handles execution of abstract types
 */
class AbstractTest extends TestCase
{
    use ArraySubsetAsserts;

    /**
     * @see it('isTypeOf used to resolve runtime type for Interface')
     */
    public function testIsTypeOfUsedToResolveRuntimeTypeForInterface() : void
    {
        // isTypeOf used to resolve runtime type for Interface
        $petType = new InterfaceType([
            'name'   => 'Pet',
            'fields' => [
                'name' => ['type' => Type::string()],
            ],
        ]);

        // Added to interface type when defined
        $dogType = new ObjectType([
            'name'       => 'Dog',
            'interfaces' => [$petType],
            'isTypeOf'   => static function ($obj) : bool {
                return $obj instanceof Dog;
            },
            'fields'     => [
                'name'  => ['type' => Type::string()],
                'woofs' => ['type' => Type::boolean()],
            ],
        ]);

        $catType = new ObjectType([
            'name'       => 'Cat',
            'interfaces' => [$petType],
            'isTypeOf'   => static function ($obj) : bool {
                return $obj instanceof Cat;
            },
            'fields'     => [
                'name'  => ['type' => Type::string()],
                'meows' => ['type' => Type::boolean()],
            ],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'pets' => [
                        'type'    => Type::listOf($petType),
                        'resolve' => static function () : array {
                            return [new Dog('Odie', true), new Cat('Garfield', false)];
                        },
                    ],
                ],
            ]),
            'types' => [$catType, $dogType],
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
                ['name' => 'Garfield', 'meows' => false],
            ],
        ]);

        $result = Executor::execute($schema, Parser::parse($query));
        self::assertEquals($expected, $result);
    }

    /**
     * @see it('isTypeOf used to resolve runtime type for Union')
     */
    public function testIsTypeOfUsedToResolveRuntimeTypeForUnion() : void
    {
        $dogType = new ObjectType([
            'name'     => 'Dog',
            'isTypeOf' => static function ($obj) : bool {
                return $obj instanceof Dog;
            },
            'fields'   => [
                'name'  => ['type' => Type::string()],
                'woofs' => ['type' => Type::boolean()],
            ],
        ]);

        $catType = new ObjectType([
            'name'     => 'Cat',
            'isTypeOf' => static function ($obj) : bool {
                return $obj instanceof Cat;
            },
            'fields'   => [
                'name'  => ['type' => Type::string()],
                'meows' => ['type' => Type::boolean()],
            ],
        ]);

        $petType = new UnionType([
            'name'  => 'Pet',
            'types' => [$dogType, $catType],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'pets' => [
                        'type'    => Type::listOf($petType),
                        'resolve' => static function () : array {
                            return [new Dog('Odie', true), new Cat('Garfield', false)];
                        },
                    ],
                ],
            ]),
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
                ['name' => 'Garfield', 'meows' => false],
            ],
        ]);

        self::assertEquals($expected, Executor::execute($schema, Parser::parse($query)));
    }

    /**
     * @see it('resolveType on Interface yields useful error')
     */
    public function testResolveTypeOnInterfaceYieldsUsefulError() : void
    {
        $DogType   = null;
        $CatType   = null;
        $HumanType = null;

        $PetType = new InterfaceType([
            'name'        => 'Pet',
            'resolveType' => static function ($obj) use (&$DogType, &$CatType, &$HumanType) {
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
            'fields'      => [
                'name' => ['type' => Type::string()],
            ],
        ]);

        $HumanType = new ObjectType([
            'name'   => 'Human',
            'fields' => [
                'name' => ['type' => Type::string()],
            ],
        ]);

        $DogType = new ObjectType([
            'name'       => 'Dog',
            'interfaces' => [$PetType],
            'fields'     => [
                'name'  => ['type' => Type::string()],
                'woofs' => ['type' => Type::boolean()],
            ],
        ]);

        $CatType = new ObjectType([
            'name'       => 'Cat',
            'interfaces' => [$PetType],
            'fields'     => [
                'name'  => ['type' => Type::string()],
                'meows' => ['type' => Type::boolean()],
            ],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'pets' => [
                        'type'    => Type::listOf($PetType),
                        'resolve' => static function () : array {
                            return [
                                new Dog('Odie', true),
                                new Cat('Garfield', false),
                                new Human('Jon'),
                            ];
                        },
                    ],
                ],
            ]),
            'types' => [$DogType, $CatType],
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
            'data'   => [
                'pets' => [
                    ['name' => 'Odie', 'woofs' => true],
                    ['name' => 'Garfield', 'meows' => false],
                    null,
                ],
            ],
            'errors' => [[
                'debugMessage' => 'Runtime Object type "Human" is not a possible type for "Pet".',
                'locations'    => [['line' => 2, 'column' => 11]],
                'path'         => ['pets', 2],
            ],
            ],
        ];
        $actual   = GraphQL::executeQuery($schema, $query)->toArray(true);

        self::assertArraySubset($expected, $actual);
    }

    /**
     * @see it('resolveType on Union yields useful error')
     */
    public function testResolveTypeOnUnionYieldsUsefulError() : void
    {
        $HumanType = new ObjectType([
            'name'   => 'Human',
            'fields' => [
                'name' => ['type' => Type::string()],
            ],
        ]);

        $DogType = new ObjectType([
            'name'   => 'Dog',
            'fields' => [
                'name'  => ['type' => Type::string()],
                'woofs' => ['type' => Type::boolean()],
            ],
        ]);

        $CatType = new ObjectType([
            'name'   => 'Cat',
            'fields' => [
                'name'  => ['type' => Type::string()],
                'meows' => ['type' => Type::boolean()],
            ],
        ]);

        $PetType = new UnionType([
            'name'        => 'Pet',
            'resolveType' => static function ($obj) use ($DogType, $CatType, $HumanType) : Type {
                if ($obj instanceof Dog) {
                    return $DogType;
                }
                if ($obj instanceof Cat) {
                    return $CatType;
                }
                if ($obj instanceof Human) {
                    return $HumanType;
                }

                throw new InvariantViolation('Invalid type');
            },
            'types'       => [$DogType, $CatType],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'pets' => [
                        'type'    => Type::listOf($PetType),
                        'resolve' => static function () : array {
                            return [
                                new Dog('Odie', true),
                                new Cat('Garfield', false),
                                new Human('Jon'),
                            ];
                        },
                    ],
                ],
            ]),
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

        $result   = GraphQL::executeQuery($schema, $query)->toArray(true);
        $expected = [
            'data'   => [
                'pets' => [
                    [
                        'name'  => 'Odie',
                        'woofs' => true,
                    ],
                    [
                        'name'  => 'Garfield',
                        'meows' => false,
                    ],
                    null,
                ],
            ],
            'errors' => [[
                'debugMessage' => 'Runtime Object type "Human" is not a possible type for "Pet".',
                'locations'    => [['line' => 2, 'column' => 11]],
                'path'         => ['pets', 2],
            ],
            ],
        ];
        self::assertArraySubset($expected, $result);
    }

    /**
     * @see it('returning invalid value from resolveType yields useful error')
     */
    public function testReturningInvalidValueFromResolveTypeYieldsUsefulError() : void
    {
        $fooInterface = new InterfaceType([
            'name'        => 'FooInterface',
            'fields'      => ['bar' => ['type' => Type::string()]],
            'resolveType' => static function () : array {
                return [];
            },
        ]);

        $fooObject = new ObjectType([
            'name'       => 'FooObject',
            'fields'     => ['bar' => ['type' => Type::string()]],
            'interfaces' => [$fooInterface],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'foo' => [
                        'type'    => $fooInterface,
                        'resolve' => static function () : string {
                            return 'dummy';
                        },
                    ],
                ],
            ]),
            'types' => [$fooObject],
        ]);

        $result = GraphQL::executeQuery($schema, '{ foo { bar } }');

        $expected = [
            'data'   => ['foo' => null],
            'errors' => [
                [
                    'message'      => 'Internal server error',
                    'debugMessage' =>
                        'Abstract type FooInterface must resolve to an Object type at ' .
                        'runtime for field Query.foo with value "dummy", received "[]". ' .
                        'Either the FooInterface type should provide a "resolveType" ' .
                        'function or each possible type should provide an "isTypeOf" function.',
                    'locations'    => [['line' => 1, 'column' => 3]],
                    'path'         => ['foo'],
                    'extensions'   => ['category' => 'internal'],
                ],
            ],
        ];
        self::assertEquals($expected, $result->toArray(true));
    }

    /**
     * @see it('resolveType allows resolving with type name')
     */
    public function testResolveTypeAllowsResolvingWithTypeName() : void
    {
        $PetType = new InterfaceType([
            'name'        => 'Pet',
            'resolveType' => static function ($obj) {
                if ($obj instanceof Dog) {
                    return 'Dog';
                }
                if ($obj instanceof Cat) {
                    return 'Cat';
                }

                return null;
            },
            'fields'      => [
                'name' => ['type' => Type::string()],
            ],
        ]);

        $DogType = new ObjectType([
            'name'       => 'Dog',
            'interfaces' => [$PetType],
            'fields'     => [
                'name'  => ['type' => Type::string()],
                'woofs' => ['type' => Type::boolean()],
            ],
        ]);

        $CatType = new ObjectType([
            'name'       => 'Cat',
            'interfaces' => [$PetType],
            'fields'     => [
                'name'  => ['type' => Type::string()],
                'meows' => ['type' => Type::boolean()],
            ],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'pets' => [
                        'type'    => Type::listOf($PetType),
                        'resolve' => static function () : array {
                            return [
                                new Dog('Odie', true),
                                new Cat('Garfield', false),
                            ];
                        },
                    ],
                ],
            ]),
            'types' => [$CatType, $DogType],
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

        $result = GraphQL::executeQuery($schema, $query)->toArray();

        self::assertEquals(
            [
                'data' => [
                    'pets' => [
                        ['name' => 'Odie', 'woofs' => true],
                        ['name' => 'Garfield', 'meows' => false],
                    ],
                ],
            ],
            $result
        );
    }

    public function testHintsOnConflictingTypeInstancesInResolveType() : void
    {
        $createTest = static function () use (&$iface) {
            return new ObjectType([
                'name'       => 'Test',
                'fields'     => [
                    'a' => Type::string(),
                ],
                'interfaces' => static function () use ($iface) : array {
                    return [$iface];
                },
            ]);
        };

        $iface = new InterfaceType([
            'name'        => 'Node',
            'fields'      => [
                'a' => Type::string(),
            ],
            'resolveType' => static function () use (&$createTest) {
                return $createTest();
            },
        ]);

        $query = new ObjectType([
            'name'   => 'Query',
            'fields' => [
                'node' => $iface,
                'test' => $createTest(),
            ],
        ]);

        $schema = new Schema(['query' => $query]);
        $schema->assertValid();

        $query = '
            {
                node {
                    a
                }
            }
        ';

        $result = Executor::execute($schema, Parser::parse($query), ['node' => ['a' => 'value']]);

        self::assertEquals(
            'Schema must contain unique named types but contains multiple types named "Test". ' .
            'Make sure that `resolveType` function of abstract type "Node" returns the same type instance ' .
            'as referenced anywhere else within the schema ' .
            '(see http://webonyx.github.io/graphql-php/type-system/#type-registry).',
            $result->errors[0]->getMessage()
        );
    }
}
