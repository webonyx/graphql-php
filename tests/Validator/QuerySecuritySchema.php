<?php declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Error\InvariantViolation;
use GraphQL\GraphQL;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\InputObjectType;
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

    private static InputObjectType $dogFilterType;

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
                    'args' => [
                        'name' => ['type' => Type::string()],
                        'names' => ['type' => Type::listOf(Type::nonNull(Type::string()))],
                        'filter' => ['type' => self::buildDogFilterType()],
                    ],
                ],
                'pets' => [
                    'type' => Type::listOf(Type::nonNull(self::buildDogType())),
                    // Falls back to a distinct worst case per limit it is not passed
                    'complexity' => static fn (int $childrenComplexity, array $args): int => $childrenComplexity + ($args['first'] ?? 100) + ($args['last'] ?? 1000),
                    'args' => [
                        'first' => ['type' => Type::nonNull(Type::int())],
                        'last' => ['type' => Type::int(), 'defaultValue' => 5],
                    ],
                ],
            ],
        ]);
    }

    /** @throws InvariantViolation */
    public static function buildDogFilterType(): InputObjectType
    {
        return self::$dogFilterType ??= new InputObjectType([
            'name' => 'DogFilter',
            'fields' => [
                'name' => ['type' => Type::nonNull(Type::string())],
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
