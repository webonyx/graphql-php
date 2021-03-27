<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\GraphQL;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use function array_merge;

class QuerySecuritySchema
{
    /** @var Schema */
    private static $schema;

    /** @var Directive */
    private static $fooDirective;

    /** @var ObjectType */
    private static $dogType;

    /** @var ObjectType */
    private static $humanType;

    /** @var ObjectType */
    private static $queryRootType;

    /**
     * @return Schema
     */
    public static function buildSchema()
    {
        if (self::$schema !== null) {
            return self::$schema;
        }

        self::$schema = new Schema([
            'query'      => static::buildQueryRootType(),
            'directives' => array_merge(GraphQL::getStandardDirectives(), [static::buildFooDirective()]),
        ]);

        return self::$schema;
    }

    public static function buildQueryRootType()
    {
        if (self::$queryRootType !== null) {
            return self::$queryRootType;
        }

        self::$queryRootType = new ObjectType([
            'name'   => 'QueryRoot',
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
        if (self::$humanType !== null) {
            return self::$humanType;
        }

        self::$humanType = new ObjectType(
            [
                'name'   => 'Human',
                'fields' => static function () : array {
                    return [
                        'firstName' => ['type' => Type::nonNull(Type::string())],
                        'dogs'      => [
                            'type'       => Type::nonNull(
                                Type::listOf(
                                    Type::nonNull(self::buildDogType())
                                )
                            ),
                            'complexity' => static function ($childrenComplexity, $args) {
                                $complexity = isset($args['name']) ? 1 : 10;

                                return $childrenComplexity + $complexity;
                            },
                            'args'       => ['name' => ['type' => Type::string()]],
                        ],
                    ];
                },
            ]
        );

        return self::$humanType;
    }

    public static function buildDogType()
    {
        if (self::$dogType !== null) {
            return self::$dogType;
        }

        self::$dogType = new ObjectType(
            [
                'name'   => 'Dog',
                'fields' => [
                    'name'   => ['type' => Type::nonNull(Type::string())],
                    'master' => [
                        'type' => self::buildHumanType(),
                    ],
                ],
            ]
        );

        return self::$dogType;
    }

    public static function buildFooDirective() : Directive
    {
        if (self::$fooDirective !== null) {
            return self::$fooDirective;
        }

        self::$fooDirective = new Directive([
            'name'      => 'foo',
            'locations' => [DirectiveLocation::FIELD],
            'args'      => [new FieldArgument([
                'name'         => 'bar',
                'type'         => Type::nonNull(Type::boolean()),
                'defaultValue' => ' ',
            ]),
            ],
        ]);

        return self::$fooDirective;
    }
}
