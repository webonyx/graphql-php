<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Executor;
use GraphQL\GraphQL;
use GraphQL\Language\Parser;
use GraphQL\Tests\Executor\TestClasses\Cat;
use GraphQL\Tests\Executor\TestClasses\Dog;
use GraphQL\Tests\Executor\TestClasses\Human;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use PHPUnit\Framework\TestCase;

/**
 * Execute: Handles execution of abstract types.
 */
class AbstractTest extends TestCase
{
    use ArraySubsetAsserts;

    /**
     * @see it('isTypeOf used to resolve runtime type for Interface')
     */
    public function testIsTypeOfUsedToResolveRuntimeTypeForInterface(): void
    {
        // isTypeOf used to resolve runtime type for Interface
        $petType = new InterfaceType([
            'name' => 'Pet',
            'fields' => [
                'name' => ['type' => Type::string()],
            ],
        ]);

        // Added to interface type when defined
        $dogType = new ObjectType([
            'name' => 'Dog',
            'interfaces' => [$petType],
            'isTypeOf' => static fn ($obj): bool => $obj instanceof Dog,
            'fields' => [
                'name' => ['type' => Type::string()],
                'woofs' => ['type' => Type::boolean()],
            ],
        ]);

        $catType = new ObjectType([
            'name' => 'Cat',
            'interfaces' => [$petType],
            'isTypeOf' => static fn ($obj): bool => $obj instanceof Cat,
            'fields' => [
                'name' => ['type' => Type::string()],
                'meows' => ['type' => Type::boolean()],
            ],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => Type::listOf($petType),
                        'resolve' => static fn (): array => [
                            new Dog('Odie', true),
                            new Cat('Garfield', false),
                        ],
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
    public function testIsTypeOfUsedToResolveRuntimeTypeForUnion(): void
    {
        $dogType = new ObjectType([
            'name' => 'Dog',
            'isTypeOf' => static fn ($obj): bool => $obj instanceof Dog,
            'fields' => [
                'name' => ['type' => Type::string()],
                'woofs' => ['type' => Type::boolean()],
            ],
        ]);

        $catType = new ObjectType([
            'name' => 'Cat',
            'isTypeOf' => static fn ($obj): bool => $obj instanceof Cat,
            'fields' => [
                'name' => ['type' => Type::string()],
                'meows' => ['type' => Type::boolean()],
            ],
        ]);

        $petType = new UnionType([
            'name' => 'Pet',
            'types' => [$dogType, $catType],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => Type::listOf($petType),
                        'resolve' => static fn (): array => [
                            new Dog('Odie', true),
                            new Cat('Garfield', false),
                        ],
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
    public function testResolveTypeOnInterfaceYieldsUsefulError(): void
    {
        $DogType = null;
        $CatType = null;
        $HumanType = null;

        $PetType = new InterfaceType([
            'name' => 'Pet',
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
            'fields' => [
                'name' => ['type' => Type::string()],
            ],
        ]);

        $HumanType = new ObjectType([
            'name' => 'Human',
            'fields' => [
                'name' => ['type' => Type::string()],
            ],
        ]);

        $DogType = new ObjectType([
            'name' => 'Dog',
            'interfaces' => [$PetType],
            'fields' => [
                'name' => ['type' => Type::string()],
                'woofs' => ['type' => Type::boolean()],
            ],
        ]);

        $CatType = new ObjectType([
            'name' => 'Cat',
            'interfaces' => [$PetType],
            'fields' => [
                'name' => ['type' => Type::string()],
                'meows' => ['type' => Type::boolean()],
            ],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => Type::listOf($PetType),
                        'resolve' => static fn (): array => [
                            new Dog('Odie', true),
                            new Cat('Garfield', false),
                            new Human('Jon'),
                        ],
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
            'data' => [
                'pets' => [
                    ['name' => 'Odie', 'woofs' => true],
                    ['name' => 'Garfield', 'meows' => false],
                    null,
                ],
            ],
            'errors' => [
                [
                    'locations' => [['line' => 2, 'column' => 11]],
                    'path' => ['pets', 2],
                    'extensions' => ['debugMessage' => 'Runtime Object type "Human" is not a possible type for "Pet".'],
                ],
            ],
        ];
        $actual = GraphQL::executeQuery($schema, $query)->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE);

        self::assertArraySubset($expected, $actual);
    }

    /**
     * @see it('resolveType on Union yields useful error')
     */
    public function testResolveTypeOnUnionYieldsUsefulError(): void
    {
        $HumanType = new ObjectType([
            'name' => 'Human',
            'fields' => [
                'name' => ['type' => Type::string()],
            ],
        ]);

        $DogType = new ObjectType([
            'name' => 'Dog',
            'fields' => [
                'name' => ['type' => Type::string()],
                'woofs' => ['type' => Type::boolean()],
            ],
        ]);

        $CatType = new ObjectType([
            'name' => 'Cat',
            'fields' => [
                'name' => ['type' => Type::string()],
                'meows' => ['type' => Type::boolean()],
            ],
        ]);

        $PetType = new UnionType([
            'name' => 'Pet',
            'resolveType' => static function ($obj) use ($DogType, $CatType, $HumanType): Type {
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
            'types' => [$DogType, $CatType],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => Type::listOf($PetType),
                        'resolve' => static fn (): array => [
                            new Dog('Odie', true),
                            new Cat('Garfield', false),
                            new Human('Jon'),
                        ],
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
            'data' => [
                'pets' => [
                    [
                        'name' => 'Odie',
                        'woofs' => true,
                    ],
                    [
                        'name' => 'Garfield',
                        'meows' => false,
                    ],
                    null,
                ],
            ],
            'errors' => [
                [
                    'locations' => [['line' => 2, 'column' => 11]],
                    'path' => ['pets', 2],
                    'extensions' => ['debugMessage' => 'Runtime Object type "Human" is not a possible type for "Pet".'],
                ],
            ],
        ];
        self::assertArraySubset($expected, $result);
    }

    /**
     * @dataProvider dogCatRootValues
     *
     * @param mixed $dog
     * @param mixed $cat
     *
     * @see it('resolve Union type using __typename on source object')
     */
    public function testResolveUnionTypeUsingTypenameOnSourceObject($dog, $cat): void
    {
        $schema = BuildSchema::build('
          type Query {
            pets: [Pet]
          }
    
          union Pet = Cat | Dog
    
          type Cat {
            name: String
            meows: Boolean
          }
    
          type Dog {
            name: String
            woofs: Boolean
          }
        ');

        $query = '
          {
            pets {
              name
              ... on Dog {
                woofs
              }
              ... on Cat {
                meows
              }
            }
          }
        ';

        $rootValue = [
            'pets' => [
                $dog,
                $cat,
            ],
        ];

        $expected = new ExecutionResult([
            'pets' => [
                ['name' => 'Odie', 'woofs' => true],
                ['name' => 'Garfield', 'meows' => false],
            ],
        ]);

        $result = Executor::execute($schema, Parser::parse($query), $rootValue);
        self::assertEquals($expected, $result);
    }

    /**
     * Return possible representations of root values for a dog and a cat with valid __typename.
     *
     * @return iterable<array{mixed, mixed}>
     */
    public function dogCatRootValues(): iterable
    {
        yield [
            [
                '__typename' => 'Dog',
                'name' => 'Odie',
                'woofs' => true,
            ],
            [
                '__typename' => 'Cat',
                'name' => 'Garfield',
                'meows' => false,
            ],
        ];
        yield [
            (object) [
                '__typename' => 'Dog',
                'name' => 'Odie',
                'woofs' => true,
            ],
            (object) [
                '__typename' => 'Cat',
                'name' => 'Garfield',
                'meows' => false,
            ],
        ];
        yield [
            new class() {
                public string $__typename = 'Dog';

                public string $name = 'Odie';

                public bool $woofs = true;
            },
            new class() {
                public string $__typename = 'Cat';

                public string $name = 'Garfield';

                public bool $meows = false;
            },
        ];
    }

    /**
     * @dataProvider dogCatRootValues
     *
     * @param mixed $dog
     * @param mixed $cat
     *
     * @see it('resolve Interface type using __typename on source object')
     */
    public function testResolveInterfaceTypeUsingTypenameOnSourceObject($dog, $cat): void
    {
        $schema = BuildSchema::build('
          type Query {
            pets: [Pet]
          }
    
          interface Pet {
            name: String
          }
    
          type Cat implements Pet {
            name: String
            meows: Boolean
          }
    
          type Dog implements Pet {
            name: String
            woofs: Boolean
          }
        ');

        $query = '
          {
            pets {
              name
              ... on Dog {
                woofs
              }
              ... on Cat {
                meows
              }
            }
          }
        ';

        $rootValue = [
            'pets' => [
                $dog,
                $cat,
            ],
        ];

        $expected = new ExecutionResult([
            'pets' => [
                ['name' => 'Odie', 'woofs' => true],
                ['name' => 'Garfield', 'meows' => false],
            ],
        ]);

        $result = Executor::execute($schema, Parser::parse($query), $rootValue);
        self::assertEquals($expected, $result);
    }

    /**
     * @see it('returning invalid value from resolveType yields useful error')
     */
    public function testReturningInvalidValueFromResolveTypeYieldsUsefulError(): void
    {
        // @phpstan-ignore-next-line intentionally wrong
        $fooInterface = new InterfaceType([
            'name' => 'FooInterface',
            'fields' => ['bar' => ['type' => Type::string()]],
            'resolveType' => static fn (): array => [],
        ]);

        $fooObject = new ObjectType([
            'name' => 'FooObject',
            'fields' => ['bar' => ['type' => Type::string()]],
            'interfaces' => [$fooInterface],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'foo' => [
                        'type' => $fooInterface,
                        'resolve' => static fn (): string => 'dummy',
                    ],
                ],
            ]),
            'types' => [$fooObject],
        ]);

        $result = GraphQL::executeQuery($schema, '{ foo { bar } }');

        $expected = [
            'data' => ['foo' => null],
            'errors' => [
                [
                    'message' => 'Internal server error',
                    'locations' => [['line' => 1, 'column' => 3]],
                    'path' => ['foo'],
                    'extensions' => [
                        'debugMessage' => 'Abstract type FooInterface must resolve to an Object type at '
                        . 'runtime for field Query.foo with value "dummy", received "[]". '
                        . 'Either the FooInterface type should provide a "resolveType" '
                        . 'function or each possible type should provide an "isTypeOf" function.',
                    ],
                ],
            ],
        ];
        self::assertEquals($expected, $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE));
    }

    public function testWarnsAboutOrphanedTypesWhenMissingType(): void
    {
        $fooObject = null;
        $fooInterface = new InterfaceType([
            'name' => 'FooInterface',
            'fields' => [
                'bar' => [
                    'type' => Type::string(),
                ],
            ],
            'resolveType' => static function () use (&$fooObject): ?ObjectType {
                return $fooObject;
            },
        ]);

        $fooObject = new ObjectType([
            'name' => 'FooObject',
            'fields' => [
                'bar' => [
                    'type' => Type::string(),
                ],
            ],
            'interfaces' => [$fooInterface],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'foo' => [
                        'type' => $fooInterface,
                        'resolve' => static fn (): array => ['bar' => 'baz'],
                    ],
                ],
            ]),
        ]);

        $result = GraphQL::executeQuery($schema, '{ foo { bar } }');

        $expected = [
            'data' => ['foo' => null],
            'errors' => [
                [
                    'message' => 'Internal server error',
                    'locations' => [['line' => 1, 'column' => 3]],
                    'path' => ['foo'],
                    'extensions' => [
                        'debugMessage' => 'Schema does not contain type "FooObject". '
                            . 'This can happen when an object type is only referenced indirectly through '
                            . 'abstract types and never directly through fields.'
                            . 'List the type in the option "types" during schema construction, '
                            . 'see https://webonyx.github.io/graphql-php/schema-definition/#configuration-options.',
                    ],
                ],
            ],
        ];
        self::assertEquals($expected, $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE));
    }

    /**
     * @see it('resolveType allows resolving with type name')
     */
    public function testResolveTypeAllowsResolvingWithTypeName(): void
    {
        $PetType = new InterfaceType([
            'name' => 'Pet',
            'resolveType' => static function ($obj) {
                if ($obj instanceof Dog) {
                    return 'Dog';
                }

                if ($obj instanceof Cat) {
                    return 'Cat';
                }

                return null;
            },
            'fields' => [
                'name' => ['type' => Type::string()],
            ],
        ]);

        $DogType = new ObjectType([
            'name' => 'Dog',
            'interfaces' => [$PetType],
            'fields' => [
                'name' => ['type' => Type::string()],
                'woofs' => ['type' => Type::boolean()],
            ],
        ]);

        $CatType = new ObjectType([
            'name' => 'Cat',
            'interfaces' => [$PetType],
            'fields' => [
                'name' => ['type' => Type::string()],
                'meows' => ['type' => Type::boolean()],
            ],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => Type::listOf($PetType),
                        'resolve' => static fn (): array => [
                            new Dog('Odie', true),
                            new Cat('Garfield', false),
                        ],
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

    public function testHintsOnConflictingTypeInstancesInResolveType(): void
    {
        /** @var InterfaceType $iface */
        $iface = null;

        $createTest = static function () use (&$iface): ObjectType {
            return new ObjectType([
                'name' => 'Test',
                'fields' => [
                    'a' => Type::string(),
                ],
                'interfaces' => static fn (): array => [$iface],
            ]);
        };

        $iface = new InterfaceType([
            'name' => 'Node',
            'fields' => [
                'a' => Type::string(),
            ],
            'resolveType' => $createTest,
        ]);

        $query = new ObjectType([
            'name' => 'Query',
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
            'Schema must contain unique named types but contains multiple types named "Test". '
            . 'Make sure that `resolveType` function of abstract type "Node" returns the same type instance '
            . 'as referenced anywhere else within the schema '
            . '(see https://webonyx.github.io/graphql-php/type-definitions/#type-registry).',
            $result->errors[0]->getMessage()
        );
    }
}
