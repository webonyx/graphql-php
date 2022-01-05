<?php declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use function array_merge;
use GraphQL\GraphQL;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

class QuerySecuritySchema
{
    private static Schema $schema;

    private static Directive $fooDirective;

    private static ObjectType $dogType;

    private static ObjectType $humanType;

    private static ObjectType $queryRootType;

    public static function buildSchema(): Schema
    {
        return self::$schema ??= new Schema([
            'query' => static::buildQueryRootType(),
            'directives' => array_merge(GraphQL::getStandardDirectives(), [static::buildFooDirective()]),
        ]);
    }

    public static function buildQueryRootType(): ObjectType
    {
        return self::$queryRootType ??= new ObjectType([
            'name' => 'QueryRoot',
            'fields' => [
                'human' => [
                    'type' => self::buildHumanType(),
                    'args' => ['name' => ['type' => Type::string()]],
                ],
            ],
        ]);
    }

    public static function buildHumanType(): ObjectType
    {
        return self::$humanType ??= new ObjectType(
            [
                'name' => 'Human',
                'fields' => static function (): array {
                    return [
                        'firstName' => ['type' => Type::nonNull(Type::string())],
                        'dogs' => [
                            'type' => Type::nonNull(
                                Type::listOf(
                                    Type::nonNull(self::buildDogType())
                                )
                            ),
                            'complexity' => static function ($childrenComplexity, $args) {
                                $complexity = isset($args['name']) ? 1 : 10;

                                return $childrenComplexity + $complexity;
                            },
                            'args' => ['name' => ['type' => Type::string()]],
                        ],
                    ];
                },
            ]
        );
    }

    public static function buildDogType(): ObjectType
    {
        return self::$dogType ??= new ObjectType(
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
    }

    public static function buildFooDirective(): Directive
    {
        return self::$fooDirective ??= new Directive([
            'name' => 'foo',
            'locations' => [DirectiveLocation::FIELD],
            'args' => [
                'bar' => [
                    'type' => Type::nonNull(Type::boolean()),
                    'defaultValue' => ' ',
                ],
            ],
        ]);
    }
}
