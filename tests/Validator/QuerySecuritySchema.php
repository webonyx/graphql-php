<?php declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Error\InvariantViolation;
use GraphQL\GraphQL;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

final class QuerySecuritySchema
{
    private static Schema $schema;

    private static Directive $fooDirective;

    private static ObjectType $dogType;

    private static ObjectType $humanType;

    private static ObjectType $queryRootType;

    /** @throws InvariantViolation */
    public static function buildSchema(): Schema
    {
        return self::$schema ??= new Schema([
            'query' => self::buildQueryRootType(),
            'directives' => array_merge(GraphQL::getStandardDirectives(), [self::buildFooDirective()]),
        ]);
    }

    /** @throws InvariantViolation */
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

    /** @throws InvariantViolation */
    public static function buildHumanType(): ObjectType
    {
        return self::$humanType ??= new ObjectType([
            'name' => 'Human',
            'fields' => static fn (): array => [
                'firstName' => ['type' => Type::nonNull(Type::string())],
                'dogs' => [
                    'type' => Type::nonNull(
                        Type::listOf(
                            Type::nonNull(self::buildDogType())
                        )
                    ),
                    'complexity' => static function (int $childrenComplexity, array $args): int {
                        $ownComplexity = isset($args['name'])
                            ? 1
                            : 10;

                        return $childrenComplexity + $ownComplexity;
                    },
                    'args' => ['name' => ['type' => Type::string()]],
                ],
            ],
        ]);
    }

    /** @throws InvariantViolation */
    public static function buildDogType(): ObjectType
    {
        return self::$dogType ??= new ObjectType([
            'name' => 'Dog',
            'fields' => [
                'name' => ['type' => Type::nonNull(Type::string())],
                'master' => [
                    'type' => self::buildHumanType(),
                ],
            ],
        ]);
    }

    /** @throws InvariantViolation */
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
