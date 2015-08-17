<?php
namespace GraphQL\Executor;

require_once __DIR__ . '/TestClasses.php';

use GraphQL\Language\Parser;
use GraphQL\Schema;
use GraphQL\Type\Definition\Config;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
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

        $this->schema = new Schema($PersonType);

        $this->garfield = new Cat('Garfield', false);
        $this->odie = new Dog('Odie', true);
        $this->liz = new Person('Liz');
        $this->john = new Person('John', [$this->garfield, $this->odie], [$this->liz, $this->odie]);

    }

    // Execute: Union and intersection types
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
                        ['name' => 'Dog'],
                        ['name' => 'Cat'],
                        ['name' => 'Person']
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

        $this->assertEquals($expected, Executor::execute($this->schema, $ast, $this->john)->toArray());
    }

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

        $this->assertEquals($expected, Executor::execute($this->schema, $ast, $this->john)->toArray());
    }

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

        $this->assertEquals($expected, Executor::execute($this->schema, $ast, $this->john)->toArray());
    }
}
