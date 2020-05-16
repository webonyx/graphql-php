<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\Executor;
use GraphQL\GraphQL;
use GraphQL\Language\Parser;
use GraphQL\Tests\Executor\TestClasses\Cat;
use GraphQL\Tests\Executor\TestClasses\Dog;
use GraphQL\Tests\Executor\TestClasses\Person;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

class UnionInterfaceTest extends TestCase
{
    /** @var Schema */
    public $schema;

    /** @var Cat */
    public $garfield;

    /** @var Dog */
    public $odie;

    /** @var Person */
    public $liz;

    /** @var Person */
    public $john;

    public function setUp() : void
    {
        $NamedType = new InterfaceType([
            'name'   => 'Named',
            'fields' => [
                'name' => ['type' => Type::string()],
            ],
        ]);

        $DogType = new ObjectType([
            'name'       => 'Dog',
            'interfaces' => [$NamedType],
            'fields'     => [
                'name'  => ['type' => Type::string()],
                'woofs' => ['type' => Type::boolean()],
            ],
            'isTypeOf'   => static function ($value) : bool {
                return $value instanceof Dog;
            },
        ]);

        $CatType = new ObjectType([
            'name'       => 'Cat',
            'interfaces' => [$NamedType],
            'fields'     => [
                'name'  => ['type' => Type::string()],
                'meows' => ['type' => Type::boolean()],
            ],
            'isTypeOf'   => static function ($value) : bool {
                return $value instanceof Cat;
            },
        ]);

        $PetType = new UnionType([
            'name'        => 'Pet',
            'types'       => [$DogType, $CatType],
            'resolveType' => static function ($value) use ($DogType, $CatType) : ObjectType {
                if ($value instanceof Dog) {
                    return $DogType;
                }
                if ($value instanceof Cat) {
                    return $CatType;
                }

                throw new InvariantViolation('Unknown type');
            },
        ]);

        $PersonType = new ObjectType([
            'name'       => 'Person',
            'interfaces' => [$NamedType],
            'fields'     => [
                'name'    => ['type' => Type::string()],
                'pets'    => ['type' => Type::listOf($PetType)],
                'friends' => ['type' => Type::listOf($NamedType)],
            ],
            'isTypeOf'   => static function ($value) : bool {
                return $value instanceof Person;
            },
        ]);

        $this->schema = new Schema([
            'query' => $PersonType,
            'types' => [$PetType],
        ]);

        $this->garfield = new Cat('Garfield', false);
        $this->odie     = new Dog('Odie', true);
        $this->liz      = new Person('Liz');
        $this->john     = new Person('John', [$this->garfield, $this->odie], [$this->liz, $this->odie]);
    }

    // Execute: Union and intersection types

    /**
     * @see it('can introspect on union and intersection types')
     */
    public function testCanIntrospectOnUnionAndIntersectionTypes() : void
    {
        $ast = Parser::parse('
      {
        Named: __type(name: "Named") {
          kind
          name
          fields { name }
          interfaces { name }
          possibleTypes { name }
          enumValues { name }
          inputFields { name }
        }
        Pet: __type(name: "Pet") {
          kind
          name
          fields { name }
          interfaces { name }
          possibleTypes { name }
          enumValues { name }
          inputFields { name }
        }
      }
    ');

        $expected = [
            'data' => [
                'Named' => [
                    'kind'          => 'INTERFACE',
                    'name'          => 'Named',
                    'fields'        => [
                        ['name' => 'name'],
                    ],
                    'interfaces'    => null,
                    'possibleTypes' => [
                        ['name' => 'Person'],
                        ['name' => 'Dog'],
                        ['name' => 'Cat'],
                    ],
                    'enumValues'    => null,
                    'inputFields'   => null,
                ],
                'Pet'   => [
                    'kind'          => 'UNION',
                    'name'          => 'Pet',
                    'fields'        => null,
                    'interfaces'    => null,
                    'possibleTypes' => [
                        ['name' => 'Dog'],
                        ['name' => 'Cat'],
                    ],
                    'enumValues'    => null,
                    'inputFields'   => null,
                ],
            ],
        ];
        self::assertEquals($expected, Executor::execute($this->schema, $ast)->toArray());
    }

    /**
     * @see it('executes using union types')
     */
    public function testExecutesUsingUnionTypes() : void
    {
        // NOTE: This is an *invalid* query, but it should be an *executable* query.
        $ast      = Parser::parse('
      {
        __typename
        name
        pets {
          __typename
          name
          woofs
          meows
        }
      }
        ');
        $expected = [
            'data' => [
                '__typename' => 'Person',
                'name'       => 'John',
                'pets'       => [
                    ['__typename' => 'Cat', 'name' => 'Garfield', 'meows' => false],
                    ['__typename' => 'Dog', 'name' => 'Odie', 'woofs' => true],
                ],
            ],
        ];

        self::assertEquals($expected, Executor::execute($this->schema, $ast, $this->john)->toArray());
    }

    /**
     * @see it('executes union types with inline fragments')
     */
    public function testExecutesUnionTypesWithInlineFragments() : void
    {
        // This is the valid version of the query in the above test.
        $ast      = Parser::parse('
      {
        __typename
        name
        pets {
          __typename
          ... on Dog {
            name
            woofs
          }
          ... on Cat {
            name
            meows
          }
        }
      }
        ');
        $expected = [
            'data' => [
                '__typename' => 'Person',
                'name'       => 'John',
                'pets'       => [
                    ['__typename' => 'Cat', 'name' => 'Garfield', 'meows' => false],
                    ['__typename' => 'Dog', 'name' => 'Odie', 'woofs' => true],
                ],

            ],
        ];
        self::assertEquals($expected, Executor::execute($this->schema, $ast, $this->john)->toArray());
    }

    /**
     * @see it('executes using interface types')
     */
    public function testExecutesUsingInterfaceTypes() : void
    {
        // NOTE: This is an *invalid* query, but it should be an *executable* query.
        $ast      = Parser::parse('
      {
        __typename
        name
        friends {
          __typename
          name
          woofs
          meows
        }
      }
        ');
        $expected = [
            'data' => [
                '__typename' => 'Person',
                'name'       => 'John',
                'friends'    => [
                    ['__typename' => 'Person', 'name' => 'Liz'],
                    ['__typename' => 'Dog', 'name' => 'Odie', 'woofs' => true],
                ],
            ],
        ];

        self::assertEquals($expected, Executor::execute($this->schema, $ast, $this->john)->toArray());
    }

    /**
     * @see it('executes interface types with inline fragments')
     */
    public function testExecutesInterfaceTypesWithInlineFragments() : void
    {
        // This is the valid version of the query in the above test.
        $ast      = Parser::parse('
      {
        __typename
        name
        friends {
          __typename
          name
          ... on Dog {
            woofs
          }
          ... on Cat {
            meows
          }
        }
      }
        ');
        $expected = [
            'data' => [
                '__typename' => 'Person',
                'name'       => 'John',
                'friends'    => [
                    ['__typename' => 'Person', 'name' => 'Liz'],
                    ['__typename' => 'Dog', 'name' => 'Odie', 'woofs' => true],
                ],
            ],
        ];

        self::assertEquals($expected, Executor::execute($this->schema, $ast, $this->john)->toArray(true));
    }

    /**
     * @see it('allows fragment conditions to be abstract types')
     */
    public function testAllowsFragmentConditionsToBeAbstractTypes() : void
    {
        $ast = Parser::parse('
      {
        __typename
        name
        pets { ...PetFields }
        friends { ...FriendFields }
      }

      fragment PetFields on Pet {
        __typename
        ... on Dog {
          name
          woofs
        }
        ... on Cat {
          name
          meows
        }
      }

      fragment FriendFields on Named {
        __typename
        name
        ... on Dog {
          woofs
        }
        ... on Cat {
          meows
        }
      }
    ');

        $expected = [
            'data' => [
                '__typename' => 'Person',
                'name'       => 'John',
                'pets'       => [
                    ['__typename' => 'Cat', 'name' => 'Garfield', 'meows' => false],
                    ['__typename' => 'Dog', 'name' => 'Odie', 'woofs' => true],
                ],
                'friends'    => [
                    ['__typename' => 'Person', 'name' => 'Liz'],
                    ['__typename' => 'Dog', 'name' => 'Odie', 'woofs' => true],
                ],
            ],
        ];

        self::assertEquals($expected, Executor::execute($this->schema, $ast, $this->john)->toArray());
    }

    /**
     * @see it('gets execution info in resolver')
     */
    public function testGetsExecutionInfoInResolver() : void
    {
        $encounteredContext   = null;
        $encounteredSchema    = null;
        $encounteredRootValue = null;
        $PersonType2          = null;

        $NamedType2 = new InterfaceType([
            'name'        => 'Named',
            'fields'      => [
                'name' => ['type' => Type::string()],
            ],
            'resolveType' => static function (
                $obj,
                $context,
                ResolveInfo $info
            ) use (
                &$encounteredContext,
                &
                $encounteredSchema,
                &$encounteredRootValue,
                &$PersonType2
            ) {
                $encounteredContext   = $context;
                $encounteredSchema    = $info->schema;
                $encounteredRootValue = $info->rootValue;

                return $PersonType2;
            },
        ]);

        $PersonType2 = new ObjectType([
            'name'       => 'Person',
            'interfaces' => [$NamedType2],
            'fields'     => [
                'name'    => ['type' => Type::string()],
                'friends' => ['type' => Type::listOf($NamedType2)],
            ],
        ]);

        $schema2 = new Schema(['query' => $PersonType2]);

        $john2 = new Person('John', [], [$this->liz]);

        $context = ['authToken' => '123abc'];

        $ast = Parser::parse('{ name, friends { name } }');

        self::assertEquals(
            ['data' => ['name' => 'John', 'friends' => [['name' => 'Liz']]]],
            GraphQL::executeQuery($schema2, $ast, $john2, $context)->toArray()
        );
        self::assertSame($context, $encounteredContext);
        self::assertSame($schema2, $encounteredSchema);
        self::assertSame($john2, $encounteredRootValue);
    }
}
