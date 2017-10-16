<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class QuerySecuritySchema
{
    private static $schema;

    private static $dogType;

    private static $humanType;

    private static $queryRootType;

    /**
     * @return Schema
     */
    public static function buildSchema()
    {
        if (null !== self::$schema) {
            return self::$schema;
        }

        self::$schema = new Schema([
            'query' => static::buildQueryRootType()
        ]);

        return self::$schema;
    }

    public static function buildQueryRootType()
    {
        if (null !== self::$queryRootType) {
            return self::$queryRootType;
        }

        self::$queryRootType = new ObjectType([
            'name' => 'QueryRoot',
            'fields' => [
                'human' => [
                    'type' => self::buildHumanType(),
                    'args' => ['name' => ['type' => Type::string()]],
                ],
            ],
        ]);

        return self::$queryRootType;
    }

    public static function buildHumanType()
    {
        if (null !== self::$humanType) {
            return self::$humanType;
        }

        self::$humanType = new ObjectType(
            [
                'name' => 'Human',
                'fields' => function() {
                    return [
                        'firstName' => ['type' => Type::nonNull(Type::string())],
                        'dogs' => [
                            'type' => Type::nonNull(
                                Type::listOf(
                                    Type::nonNull(self::buildDogType())
                                )
                            ),
                            'complexity' => function ($childrenComplexity, $args) {
                                $complexity = isset($args['name']) ? 1 : 10;

                                return $childrenComplexity + $complexity;
                            },
                            'args' => ['name' => ['type' => Type::string()]],
                        ],
                    ];
                },
            ]
        );

        return self::$humanType;
    }

    public static function buildDogType()
    {
        if (null !== self::$dogType) {
            return self::$dogType;
        }

        self::$dogType = new ObjectType(
            [
                'name' => 'Dog',
                'fields' => [
                    'name' => ['type' => Type::nonNull(Type::string())],
                    'master' => [
                        'type' => self::buildHumanType(),
                    ],
                ],
            ]
        );

        return self::$dogType;
    }
}
