<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\ArrayAccessPropertyFallback;
use GraphQL\GraphQL;
use GraphQL\Tests\Executor\TestClasses\ArrayAccessAttributes;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

/**
 * @see https://github.com/webonyx/graphql-php/pull/1960#issuecomment-5865658411
 */
final class ArrayAccessPropertyFallbackTest extends TestCase
{
    public function testReadsPropertiesOfOptedInArrayAccess(): void
    {
        $rootValue = new class(['attribute' => 'attribute', 'shadowed' => 'attribute', 'nullAttribute' => null]) extends ArrayAccessAttributes implements ArrayAccessPropertyFallback {
            public string $shadowed = 'property';

            public string $property = 'property';

            public string $nullAttribute = 'property';
        };

        self::assertSame(
            [
                'data' => [
                    'attribute' => 'attribute',
                    'shadowed' => 'attribute',
                    'property' => 'property',
                    'nullAttribute' => 'property',
                    'missing' => null,
                ],
            ],
            self::executeFields(['attribute', 'shadowed', 'property', 'nullAttribute', 'missing'], $rootValue)
        );
    }

    public function testHidesPropertiesOfEloquentLikeModel(): void
    {
        $rootValue = new class(['name' => null]) extends ArrayAccessAttributes {
            public bool $exists = true;

            public bool $wasRecentlyCreated = false;

            public string $name = 'property';
        };

        self::assertSame(
            [
                'data' => [
                    'exists' => null,
                    'wasRecentlyCreated' => null,
                    'name' => null,
                ],
            ],
            self::executeFields(['exists', 'wasRecentlyCreated', 'name'], $rootValue)
        );
    }

    public function testDoesNotCallMagicGetOfCollectionLikeValue(): void
    {
        $rootValue = new class(['present' => 1]) extends ArrayAccessAttributes {
            /**
             * @throws \Exception
             *
             * @return never
             */
            public function __get(string $key)
            {
                throw new \Exception("Property [{$key}] does not exist on this collection instance.");
            }
        };

        self::assertSame(
            [
                'data' => [
                    'present' => '1',
                    'missing' => null,
                ],
            ],
            self::executeFields(['present', 'missing'], $rootValue)
        );
    }

    public function testDoesNotForwardToFirstItemOfFieldItemListLikeValue(): void
    {
        $rootValue = new class([]) extends ArrayAccessAttributes {
            public function __isset(string $name): bool
            {
                return true;
            }

            public function __get(string $name): string
            {
                return 'referenced entity';
            }
        };

        self::assertSame(
            ['data' => ['entity' => null]],
            self::executeFields(['entity'], $rootValue)
        );
    }

    public function testIgnoresTypenamePropertyOfArrayAccess(): void
    {
        $pet = new class([]) extends ArrayAccessAttributes {
            public string $__typename = 'Cat';
        };

        self::assertSame(
            ['data' => ['pet' => ['__typename' => 'Dog']]],
            self::executePet($pet)
        );
    }

    public function testReadsTypenamePropertyOfOptedInArrayAccess(): void
    {
        $pet = new class([]) extends ArrayAccessAttributes implements ArrayAccessPropertyFallback {
            public string $__typename = 'Cat';
        };

        self::assertSame(
            ['data' => ['pet' => ['__typename' => 'Cat']]],
            self::executePet($pet)
        );
    }

    /**
     * @param list<string> $fieldNames
     * @param \ArrayAccess<string, mixed> $rootValue
     *
     * @throws \Exception
     * @throws InvariantViolation
     *
     * @return array<string, mixed>
     */
    private static function executeFields(array $fieldNames, \ArrayAccess $rootValue): array
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => array_fill_keys($fieldNames, Type::string()),
            ]),
        ]);

        $query = '{ ' . implode(' ', $fieldNames) . ' }';

        return GraphQL::executeQuery($schema, $query, $rootValue)->toArray();
    }

    /**
     * @param \ArrayAccess<string, mixed> $pet
     *
     * @throws \Exception
     * @throws InvariantViolation
     *
     * @return array<string, mixed>
     */
    private static function executePet(\ArrayAccess $pet): array
    {
        $dog = new ObjectType([
            'name' => 'Dog',
            'fields' => ['name' => Type::string()],
            'isTypeOf' => static fn (): bool => true,
        ]);
        $cat = new ObjectType([
            'name' => 'Cat',
            'fields' => ['name' => Type::string()],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pet' => [
                        'type' => new UnionType([
                            'name' => 'Pet',
                            'types' => [$dog, $cat],
                        ]),
                        'resolve' => static fn (): \ArrayAccess => $pet,
                    ],
                ],
            ]),
        ]);

        return GraphQL::executeQuery($schema, '{ pet { __typename } }')->toArray();
    }
}
