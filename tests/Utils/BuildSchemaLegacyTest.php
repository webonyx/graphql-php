<?php declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Error\DebugFlag;
use GraphQL\GraphQL;
use GraphQL\Language\Parser;
use GraphQL\Utils\BuildSchema;
use PHPUnit\Framework\TestCase;

/**
 * Contains tests originating from `graphql-js` that previously were in BuildSchemaTest.
 * Their counterparts have been removed from `buildASTSchema-test.js` and moved elsewhere,
 * but these changes to `graphql-js` haven't been reflected in `graphql-php` yet.
 * TODO align with:
 *   - https://github.com/graphql/graphql-js/commit/64a5c3448a201737f9218856786c51d66f2deabd.
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
}
