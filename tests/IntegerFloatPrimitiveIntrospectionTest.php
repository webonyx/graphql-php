<?php declare(strict_types=1);

namespace GraphQL\Tests;

use GraphQL\Error\InvariantViolation;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

final class IntegerFloatPrimitiveIntrospectionTest extends TestCase
{
    /**
     * @throws InvariantViolation
     */
    public static function build(): Schema
    {
        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'test' => [
                    'type' => Type::string(),
                    'args' => [
                        'index' => [
                            'description' => 'This fails',
                            'type' => Type::int(),
                            'defaultValue' => 3,
                        ],
                        'another' => [
                            'description' => 'This fails',
                            'type' => Type::float(),
                            'defaultValue' => 3.14,
                        ],
                    ],
                ],
            ],
        ]);

        return new Schema(['query' => $queryType]);
    }

    public function testDefaultValues(): void
    {
        $query = '{
          __schema {
            queryType {
              fields {
                name
                args {
                  name
                  defaultValue
                }
              }
            }
          }
        }';

        $expected = [
            '__schema' => [
                'queryType' => [
                    'fields' => [
                        [
                            'name' => 'test',
                            'args' => [
                                [
                                    'name' => 'index',
                                    'defaultValue' => '3',
                                ],
                                [
                                    'name' => 'another',
                                    'defaultValue' => '3.14',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        self::assertSame(['data' => $expected], GraphQL::executeQuery(self::build(), $query)->toArray());
    }
}
