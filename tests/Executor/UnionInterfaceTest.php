<?php
namespace GraphQL\Tests\Executor;

require_once __DIR__ . '/TestClasses.php';

use GraphQL\Error\Warning;
use GraphQL\Executor\Executor;
use GraphQL\GraphQL;
use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;

class UnionInterfaceTest extends \PHPUnit_Framework_TestCase
{
    public $schema;
    public $garfield;
    public $odie;
    public $liz;
    public $john;

    public function setUp()
    {
        $NamedType = new InterfaceType([
            'name' => 'Named',
            'fields' => [
                'name' => ['type' => Type::string()]
            ]
        ]);

        $DogType = new ObjectType([
            'name' => 'Dog',
            'interfaces' => [$NamedType],
            'fields' => [
                'name' => ['type' => Type::string()],
                'woofs' => ['type' => Type::boolean()]
            ],
            'isTypeOf' => function ($value) {
                return $value instanceof Dog;
            }
        ]);

        $CatType = new ObjectType([
            'name' => 'Cat',
            'interfaces' => [$NamedType],
            'fields' => [
                'name' => ['type' => Type::string()],
                'meows' => ['type' => Type::boolean()]
            ],
            'isTypeOf' => function ($value) {
                return $value instanceof Cat;
            }
        ]);

        $PetType = new UnionType([
            'name' => 'Pet',
            'types' => [$DogType, $CatType],
            'resolveType' => function ($value) use ($DogType, $CatType) {
                if ($value instanceof Dog) {
                    return $DogType;
                }
                if ($value instanceof Cat) {
                    return $CatType;
                }
            }
        ]);

        $PersonType = new ObjectType([
            'name' => 'Person',
            'interfaces' => [$NamedType],
            'fields' => [
                'name' => ['type' => Type::string()],
                'pets' => ['type' => Type::listOf($PetType)],
                'friends' => ['type' => Type::listOf($NamedType)]
            ],
            'isTypeOf' => function ($value) {
                return $value instanceof Person;
            }
        ]);

        $this->schema = new Schema([
            'query' => $PersonType,
            'types' => [ $PetType ]
        ]);

        $this->garfield = new Cat('Garfield', false);
        $this->odie = new Dog('Odie', true);
        $this->liz = new Person('Liz');
        $this->john = new Person('John', [$this->garfield, $this->odie], [$this->liz, $this->odie]);

    }

    // Execute: Union and intersection types

    /**
     * @it can introspect on union and intersection types
     */
    public function testCanIntrospectOnUnionAndIntersectionTypes()
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
                    'kind' => 'INTERFACE',
                    'name' => 'Named',
                    'fields' => [
                        ['name' => 'name']
                    ],
                    'interfaces' => null,
                    'possibleTypes' => [
                        ['name' => 'Person'],
                        ['name' => 'Dog'],
                        ['name' => 'Cat']
                    ],
                    'enumValues' => null,
                    'inputFields' => null
                ],
                'Pet' => [
                    'kind' => 'UNION',
                    'name' => 'Pet',
                    'fields' => null,
                    'interfaces' => null,
                    'possibleTypes' => [
                        ['name' => 'Dog'],
                        ['name' => 'Cat']
                    ],
                    'enumValues' => null,
                    'inputFields' => null
                ]
            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema, $ast)->toArray());
    }

    /**
     * @it executes using union types
     */
    public function testExecutesUsingUnionTypes()
    {
        // NOTE: This is an *invalid* query, but it should be an *executable* query.
        $ast = Parser::parse('
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
                'name' => 'John',
                'pets' => [
                    ['__typename' => 'Cat', 'name' => 'Garfield', 'meows' => false],
                    ['__typename' => 'Dog', 'name' => 'Odie', 'woofs' => true]
                ]
            ]
        ];

        $this->assertEquals($expected, Executor::execute($this->schema, $ast, $this->john)->toArray());
    }

    /**
     * @it executes union types with inline fragments
     */
    public function testExecutesUnionTypesWithInlineFragments()
    {
        // This is the valid version of the query in the above test.
        $ast = Parser::parse('
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
                'name' => 'John',
                'pets' => [
                    ['__typename' => 'Cat', 'name' => 'Garfield', 'meows' => false],
                    ['__typename' => 'Dog', 'name' => 'Odie', 'woofs' => true]
                ]

            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema, $ast, $this->john)->toArray());
    }

    /**
     * @it executes using interface types
     */
    public function testExecutesUsingInterfaceTypes()
    {
        // NOTE: This is an *invalid* query, but it should be an *executable* query.
        $ast = Parser::parse('
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
                'name' => 'John',
                'friends' => [
                    ['__typename' => 'Person', 'name' => 'Liz'],
                    ['__typename' => 'Dog', 'name' => 'Odie', 'woofs' => true]
                ]
            ]
        ];

        Warning::suppress(Warning::WARNING_FULL_SCHEMA_SCAN);
        $this->assertEquals($expected, Executor::execute($this->schema, $ast, $this->john)->toArray());
        Warning::enable(Warning::WARNING_FULL_SCHEMA_SCAN);
    }

    /**
     * @it executes interface types with inline fragments
     */
    public function testExecutesInterfaceTypesWithInlineFragments()
    {
        // This is the valid version of the query in the above test.
        $ast = Parser::parse('
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
                'name' => 'John',
                'friends' => [
                    ['__typename' => 'Person', 'name' => 'Liz'],
                    ['__typename' => 'Dog', 'name' => 'Odie', 'woofs' => true]
                ]
            ]
        ];

        Warning::suppress(Warning::WARNING_FULL_SCHEMA_SCAN);
        $this->assertEquals($expected, Executor::execute($this->schema, $ast, $this->john)->toArray());
        Warning::enable(Warning::WARNING_FULL_SCHEMA_SCAN);
    }

    /**
     * @it allows fragment conditions to be abstract types
     */
    public function testAllowsFragmentConditionsToBeAbstractTypes()
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
                'name' => 'John',
                'pets' => [
                    ['__typename' => 'Cat', 'name' => 'Garfield', 'meows' => false],
                    ['__typename' => 'Dog', 'name' => 'Odie', 'woofs' => true]
                ],
                'friends' => [
                    ['__typename' => 'Person', 'name' => 'Liz'],
                    ['__typename' => 'Dog', 'name' => 'Odie', 'woofs' => true]
                ]
            ]
        ];

        Warning::suppress(Warning::WARNING_FULL_SCHEMA_SCAN);
        $this->assertEquals($expected, Executor::execute($this->schema, $ast, $this->john)->toArray());
        Warning::enable(Warning::WARNING_FULL_SCHEMA_SCAN);
    }

    /**
     * @it gets execution info in resolver
     */
    public function testGetsExecutionInfoInResolver()
    {
        $encounteredContext = null;
        $encounteredSchema = null;
        $encounteredRootValue = null;
        $PersonType2 = null;

        $NamedType2 = new InterfaceType([
            'name' => 'Named',
            'fields' => [
                'name' => ['type' => Type::string()]
            ],
            'resolveType' => function ($obj, $context, ResolveInfo $info) use (&$encounteredContext, &$encounteredSchema, &$encounteredRootValue, &$PersonType2) {
                $encounteredContext = $context;
                $encounteredSchema = $info->schema;
                $encounteredRootValue = $info->rootValue;
                return $PersonType2;
            }
        ]);

        $PersonType2 = new ObjectType([
            'name' => 'Person',
            'interfaces' => [$NamedType2],
            'fields' => [
                'name' => ['type' => Type::string()],
                'friends' => ['type' => Type::listOf($NamedType2)],
            ],
        ]);

        $schema2 = new Schema([
            'query' => $PersonType2
        ]);

        $john2 = new Person('John', [], [$this->liz]);

        $context = ['authToken' => '123abc'];

        $ast = Parser::parse('{ name, friends { name } }');

        $this->assertEquals(
            ['data' => ['name' => 'John', 'friends' => [['name' => 'Liz']]]],
            GraphQL::execute($schema2, $ast, $john2, $context)
        );
        $this->assertSame($context, $encounteredContext);
        $this->assertSame($schema2, $encounteredSchema);
        $this->assertSame($john2, $encounteredRootValue);
    }
}
