<?php

declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Language\Parser;
use GraphQL\Utils\BuildSchema;
use PHPUnit\Framework\TestCase;

/**
 * Contains tests originating from `graphql-js` that previously were in BuildSchemaTest.
 * Their counterparts have been removed from `buildASTSchema-test.js` and moved elsewhere,
 * but these changes to `graphql-js` haven't been reflected in `graphql-php` yet.
 * TODO align with:
 *   - https://github.com/graphql/graphql-js/commit/257797a0ebdddd3da6e75b7c237fdc12a1a7c75a
 *   - https://github.com/graphql/graphql-js/commit/9b7a8af43fc0865a01df5b5a084f37bbb8680ef8
 *   - https://github.com/graphql/graphql-js/commit/3b9ea61f2348215dee755f779caef83df749d2bb
 *   - https://github.com/graphql/graphql-js/commit/64a5c3448a201737f9218856786c51d66f2deabd
 */
class BuildSchemaLegacyTest extends TestCase
{
    /**
     * @see it('Specifying Union type using __typename')
     */
    public function testSpecifyingUnionTypeUsingTypename(): void
    {
        $schema = BuildSchema::buildAST(Parser::parse('
            type Query {
              fruits: [Fruit]
            }
            
            union Fruit = Apple | Banana
            
            type Apple {
              color: String
            }
            
            type Banana {
              length: Int
            }
        '));

        $source = '
            {
              fruits {
                ... on Apple {
                  color
                }
                ... on Banana {
                  length
                }
              }
            }
        ';

        $rootValue = [
            'fruits' => [
                [
                    'color' => 'green',
                    '__typename' => 'Apple',
                ],
                [
                    'length' => 5,
                    '__typename' => 'Banana',
                ],
            ],
        ];

        $expected = [
            'data' => [
                'fruits' => [
                    ['color' => 'green'],
                    ['length' => 5],
                ],
            ],
        ];

        $result = GraphQL::executeQuery($schema, $source, $rootValue);
        self::assertEquals($expected, $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE));
    }

    /**
     * @see it('Specifying Interface type using __typename')
     */
    public function testSpecifyingInterfaceUsingTypename(): void
    {
        $schema = BuildSchema::buildAST(Parser::parse('
            type Query {
              characters: [Character]
            }
            
            interface Character {
              name: String!
            }
            
            type Human implements Character {
              name: String!
              totalCredits: Int
            }
            
            type Droid implements Character {
              name: String!
              primaryFunction: String
            }
        '));

        $source = '
            {
              characters {
                name
                ... on Human {
                  totalCredits
                }
                ... on Droid {
                  primaryFunction
                }
              }
            }
        ';

        $rootValue = [
            'characters' => [
                [
                    'name' => 'Han Solo',
                    'totalCredits' => 10,
                    '__typename' => 'Human',
                ],
                [
                    'name' => 'R2-D2',
                    'primaryFunction' => 'Astromech',
                    '__typename' => 'Droid',
                ],
            ],
        ];

        $expected = [
            'data' => [
                'characters' => [
                    ['name' => 'Han Solo', 'totalCredits' => 10],
                    ['name' => 'R2-D2', 'primaryFunction' => 'Astromech'],
                ],
            ],
        ];

        $result = GraphQL::executeQuery($schema, $source, $rootValue);
        self::assertEquals($expected, $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE));
    }

    // Describe: Failures

    /**
     * @see it('Allows only a single query type')
     */
    public function testAllowsOnlySingleQueryType(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Must provide only one query type in schema.');
        $sdl = '
            schema {
              query: Hello
              query: Yellow
            }
            
            type Hello {
              bar: String
            }
            
            type Yellow {
              isColor: Boolean
            }
        ';
        $doc = Parser::parse($sdl);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Allows only a single mutation type')
     */
    public function testAllowsOnlySingleMutationType(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Must provide only one mutation type in schema.');
        $sdl = '
            schema {
              query: Hello
              mutation: Hello
              mutation: Yellow
            }
            
            type Hello {
              bar: String
            }
            
            type Yellow {
              isColor: Boolean
            }
        ';
        $doc = Parser::parse($sdl);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Allows only a single subscription type')
     */
    public function testAllowsOnlySingleSubscriptionType(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Must provide only one subscription type in schema.');
        $sdl = '
            schema {
              query: Hello
              subscription: Hello
              subscription: Yellow
            }
            
            type Hello {
              bar: String
            }
            
            type Yellow {
              isColor: Boolean
            }
        ';
        $doc = Parser::parse($sdl);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Unknown type referenced')
     */
    public function testUnknownTypeReferenced(): void
    {
        $this->expectExceptionObject(BuildSchema::unknownType('Bar'));
        $sdl    = '
            schema {
              query: Hello
            }
            
            type Hello {
              bar: Bar
            }
        ';
        $doc    = Parser::parse($sdl);
        $schema = BuildSchema::buildAST($doc);
        $schema->getTypeMap();
    }

    /**
     * @see it('Unknown type in interface list')
     */
    public function testUnknownTypeInInterfaceList(): void
    {
        $this->expectExceptionObject(BuildSchema::unknownType('Bar'));
        $sdl    = '
            type Query implements Bar {
              field: String
            }
        ';
        $doc    = Parser::parse($sdl);
        $schema = BuildSchema::buildAST($doc);
        $schema->getTypeMap();
    }

    /**
     * @see it('Unknown type in union list')
     */
    public function testUnknownTypeInUnionList(): void
    {
        $this->expectExceptionObject(BuildSchema::unknownType('Bar'));
        $sdl    = '
            union TestUnion = Bar
            type Query { testUnion: TestUnion }
        ';
        $doc    = Parser::parse($sdl);
        $schema = BuildSchema::buildAST($doc);
        $schema->getTypeMap();
    }

    /**
     * @see it('Unknown query type')
     */
    public function testUnknownQueryType(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Specified query type "Wat" not found in document.');
        $sdl = '
            schema {
              query: Wat
            }
            
            type Hello {
              str: String
            }
        ';
        $doc = Parser::parse($sdl);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Unknown mutation type')
     */
    public function testUnknownMutationType(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Specified mutation type "Wat" not found in document.');
        $sdl = '
            schema {
              query: Hello
              mutation: Wat
            }
            
            type Hello {
              str: String
            }
        ';
        $doc = Parser::parse($sdl);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Unknown subscription type')
     */
    public function testUnknownSubscriptionType(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Specified subscription type "Awesome" not found in document.');
        $sdl = '
            schema {
              query: Hello
              mutation: Wat
              subscription: Awesome
            }
            
            type Hello {
              str: String
            }
            
            type Wat {
              str: String
            }
        ';
        $doc = Parser::parse($sdl);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Does not consider directive names')
     */
    public function testDoesNotConsiderDirectiveNames(): void
    {
        $sdl = '
          schema {
            query: Foo
          }
    
          directive @Foo on QUERY
        ';
        $doc = Parser::parse($sdl);
        $this->expectExceptionMessage('Specified query type "Foo" not found in document.');
        BuildSchema::build($doc);
    }

    /**
     * @see it('Does not consider operation names')
     */
    public function testDoesNotConsiderOperationNames(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Specified query type "Foo" not found in document.');
        $sdl = '
            schema {
              query: Foo
            }
            
            query Foo { field }
        ';
        $doc = Parser::parse($sdl);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Does not consider fragment names')
     */
    public function testDoesNotConsiderFragmentNames(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Specified query type "Foo" not found in document.');
        $sdl = '
            schema {
              query: Foo
            }
            
            fragment Foo on Type { field }
        ';
        $doc = Parser::parse($sdl);
        BuildSchema::buildAST($doc);
    }

    /**
     * @see it('Forbids duplicate type definitions')
     */
    public function testForbidsDuplicateTypeDefinitions(): void
    {
        $sdl = '
            schema {
              query: Repeated
            }
            
            type Repeated {
              id: Int
            }
            
            type Repeated {
              id: String
            }
        ';
        $doc = Parser::parse($sdl);
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Type "Repeated" was defined more than once.');
        BuildSchema::buildAST($doc);
    }
}
