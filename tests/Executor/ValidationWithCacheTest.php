<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GraphQL\Deferred;
use GraphQL\GraphQL;
use GraphQL\Tests\Executor\TestClasses\Cat;
use GraphQL\Tests\Executor\TestClasses\Dog;
use GraphQL\Tests\Executor\TestClasses\SpyValidationCacheAdapter;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

final class ValidationWithCacheTest extends TestCase
{
    use ArraySubsetAsserts;

    public function testIsValidationCachedWithAdapter(): void
    {
        $cache = new SpyValidationCacheAdapter(new Psr16Cache(new ArrayAdapter()));
        $petType = new InterfaceType([
            'name' => 'Pet',
            'fields' => [
                'name' => Type::string(),
            ],
        ]);

        $DogType = new ObjectType([
            'name' => 'Dog',
            'interfaces' => [$petType],
            'isTypeOf' => static fn ($obj): Deferred => new Deferred(static fn (): bool => $obj instanceof Dog),
            'fields' => [
                'name' => Type::string(),
                'woofs' => Type::boolean(),
            ],
        ]);

        $CatType = new ObjectType([
            'name' => 'Cat',
            'interfaces' => [$petType],
            'isTypeOf' => static fn ($obj): Deferred => new Deferred(static fn (): bool => $obj instanceof Cat),
            'fields' => [
                'name' => Type::string(),
                'meows' => Type::boolean(),
            ],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pets' => [
                        'type' => Type::listOf($petType),
                        'resolve' => static fn (): array => [
                            new Dog('Odie', true),
                            new Cat('Garfield', false),
                        ],
                    ],
                ],
            ]),
            'types' => [$CatType, $DogType],
        ]);

        $query = '{
          pets {
            name
            ... on Dog {
              woofs
            }
            ... on Cat {
              meows
            }
          }
        }';

        // make the same call twice in a row. We'll then inspect the cache object to count calls
        $resultA = GraphQL::executeQuery($schema, $query, null, null, null, null, null, null, $cache)->toArray();
        $resultB = GraphQL::executeQuery($schema, $query, null, null, null, null, null, null, $cache)->toArray();

        // ✅ Assert that validation only happened once
        self::assertEquals(2, $cache->isValidatedCalls, 'Should check cache twice');
        self::assertEquals(1, $cache->markValidatedCalls, 'Should mark as validated once');

        $expected = [
            'data' => [
                'pets' => [
                    ['name' => 'Odie', 'woofs' => true],
                    ['name' => 'Garfield', 'meows' => false],
                ],
            ],
        ];

        self::assertEquals($expected, $resultA);
        self::assertEquals($expected, $resultB);
    }
}
