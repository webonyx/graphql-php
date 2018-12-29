<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use GraphQL\Deferred;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\UserError;
use GraphQL\GraphQL;
use GraphQL\Tests\Executor\TestClasses\Cat;
use GraphQL\Tests\Executor\TestClasses\Dog;
use GraphQL\Tests\Executor\TestClasses\Human;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

/**
 * DESCRIBE: Execute: Handles execution of abstract types with promises
 */
class AbstractPromiseTest extends TestCase
{
    /**
     * @see it('isTypeOf used to resolve runtime type for Interface')
     */
    public function testIsTypeOfUsedToResolveRuntimeTypeForInterface() : void
    {
        $petType = new InterfaceType([
            'name'   => 'Pet',
            'fields' => [
                'name' => ['type' => Type::string()],
            ],
        ]);

        $DogType = new ObjectType([
            'name'       => 'Dog',
            'interfaces' => [$petType],
            'isTypeOf'   => static function ($obj) {
                return new Deferred(static function () use ($obj) {
                    return $obj instanceof Dog;
                });
            },
            'fields'     => [
                'name'  => ['type' => Type::string()],
                'woofs' => ['type' => Type::boolean()],
            ],
        ]);

        $CatType = new ObjectType([
            'name'       => 'Cat',
            'interfaces' => [$petType],
            'isTypeOf'   => static function ($obj) {
                return new Deferred(static function () use ($obj) {
                    return $obj instanceof Cat;
                });
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
                        'resolve' => static function () {
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

        $expected = [
            'data' => [
                'pets' => [
                    ['name' => 'Odie', 'woofs' => true],
                    ['name' => 'Garfield', 'meows' => false],
                ],
            ],
        ];

        self::assertEquals($expected, $result);
    }

    /**
     * @see it('isTypeOf can be rejected')
     */
    public function testIsTypeOfCanBeRejected() : void
    {
        $PetType = new InterfaceType([
            'name'   => 'Pet',
            'fields' => [
                'name' => ['type' => Type::string()],
            ],
        ]);

        $DogType = new ObjectType([
            'name'       => 'Dog',
            'interfaces' => [$PetType],
            'isTypeOf'   => static function () {
                return new Deferred(static function () {
                    throw new UserError('We are testing this error');
                });
            },
            'fields'     => [
                'name'  => ['type' => Type::string()],
                'woofs' => ['type' => Type::boolean()],
            ],
        ]);

        $CatType = new ObjectType([
            'name'       => 'Cat',
            'interfaces' => [$PetType],
            'isTypeOf'   => static function ($obj) {
                return new Deferred(static function () use ($obj) {
                    return $obj instanceof Cat;
                });
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
                        'type'    => Type::listOf($PetType),
                        'resolve' => static function () {
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

        $expected = [
            'data'   => [
                'pets' => [null, null],
            ],
            'errors' => [
                [
                    'message'   => 'We are testing this error',
                    'locations' => [['line' => 2, 'column' => 7]],
                    'path'      => ['pets', 0],
                ],
                [
                    'message'   => 'We are testing this error',
                    'locations' => [['line' => 2, 'column' => 7]],
                    'path'      => ['pets', 1],
                ],
            ],
        ];

        self::assertArraySubset($expected, $result);
    }

    /**
     * @see it('isTypeOf used to resolve runtime type for Union')
     */
    public function testIsTypeOfUsedToResolveRuntimeTypeForUnion() : void
    {
        $dogType = new ObjectType([
            'name'     => 'Dog',
            'isTypeOf' => static function ($obj) {
                return new Deferred(static function () use ($obj) {
                    return $obj instanceof Dog;
                });
            },
            'fields'   => [
                'name'  => ['type' => Type::string()],
                'woofs' => ['type' => Type::boolean()],
            ],
        ]);

        $catType = new ObjectType([
            'name'     => 'Cat',
            'isTypeOf' => static function ($obj) {
                return new Deferred(static function () use ($obj) {
                    return $obj instanceof Cat;
                });
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
                        'resolve' => static function () {
                            return [new Dog('Odie', true), new Cat('Garfield', false)];
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

        $result = GraphQL::executeQuery($schema, $query)->toArray();

        $expected = [
            'data' => [
                'pets' => [
                    ['name' => 'Odie', 'woofs' => true],
                    ['name' => 'Garfield', 'meows' => false],
                ],
            ],
        ];

        self::assertEquals($expected, $result);
    }

    /**
     * @see it('resolveType on Interface yields useful error')
     */
    public function testResolveTypeOnInterfaceYieldsUsefulError() : void
    {
        $PetType = new InterfaceType([
            'name'        => 'Pet',
            'resolveType' => static function ($obj) use (&$DogType, &$CatType, &$HumanType) {
                return new Deferred(static function () use ($obj, $DogType, $CatType, $HumanType) {
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
                });
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
                        'resolve' => static function () {
                            return new Deferred(static function () {
                                return [
                                    new Dog('Odie', true),
                                    new Cat('Garfield', false),
                                    new Human('Jon'),
                                ];
                            });
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

        $result = GraphQL::executeQuery($schema, $query)->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE);

        $expected = [
            'data'   => [
                'pets' => [
                    ['name' => 'Odie', 'woofs' => true],
                    ['name' => 'Garfield', 'meows' => false],
                    null,
                ],
            ],
            'errors' => [
                [
                    'debugMessage' => 'Runtime Object type "Human" is not a possible type for "Pet".',
                    'locations'    => [['line' => 2, 'column' => 7]],
                    'path'         => ['pets', 2],
                ],
            ],
        ];

        self::assertArraySubset($expected, $result);
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
            'resolveType' => static function ($obj) use ($DogType, $CatType, $HumanType) {
                return new Deferred(static function () use ($obj, $DogType, $CatType, $HumanType) {
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
                });
            },
            'types'       => [$DogType, $CatType],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'pets' => [
                        'type'    => Type::listOf($PetType),
                        'resolve' => static function () {
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

        $result = GraphQL::executeQuery($schema, $query)->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE);

        $expected = [
            'data'   => [
                'pets' => [
                    ['name' => 'Odie', 'woofs' => true],
                    ['name' => 'Garfield', 'meows' => false],
                    null,
                ],
            ],
            'errors' => [
                [
                    'debugMessage' => 'Runtime Object type "Human" is not a possible type for "Pet".',
                    'locations'    => [['line' => 2, 'column' => 7]],
                    'path'         => ['pets', 2],
                ],
            ],
        ];

        self::assertArraySubset($expected, $result);
    }

    /**
     * @see it('resolveType allows resolving with type name')
     */
    public function testResolveTypeAllowsResolvingWithTypeName() : void
    {
        $PetType = new InterfaceType([
            'name'        => 'Pet',
            'resolveType' => static function ($obj) {
                return new Deferred(static function () use ($obj) {
                    if ($obj instanceof Dog) {
                        return 'Dog';
                    }
                    if ($obj instanceof Cat) {
                        return 'Cat';
                    }

                    return null;
                });
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
                        'resolve' => static function () {
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

        $expected = [
            'data' => [
                'pets' => [
                    ['name' => 'Odie', 'woofs' => true],
                    ['name' => 'Garfield', 'meows' => false],
                ],
            ],
        ];
        self::assertEquals($expected, $result);
    }

    /**
     * @see it('resolveType can be caught')
     */
    public function testResolveTypeCanBeCaught() : void
    {
        $PetType = new InterfaceType([
            'name'        => 'Pet',
            'resolveType' => static function () {
                return new Deferred(static function () {
                    throw new UserError('We are testing this error');
                });
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
                        'resolve' => static function () {
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

        $expected = [
            'data'   => [
                'pets' => [null, null],
            ],
            'errors' => [
                [
                    'message'   => 'We are testing this error',
                    'locations' => [['line' => 2, 'column' => 7]],
                    'path'      => ['pets', 0],
                ],
                [
                    'message'   => 'We are testing this error',
                    'locations' => [['line' => 2, 'column' => 7]],
                    'path'      => ['pets', 1],
                ],
            ],
        ];

        self::assertArraySubset($expected, $result);
    }
}
