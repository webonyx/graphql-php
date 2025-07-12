<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GraphQL\Deferred;
use GraphQL\GraphQL;
use GraphQL\Tests\Executor\TestClasses\Cat;
use GraphQL\Tests\Executor\TestClasses\Dog;
use GraphQL\Tests\PsrValidationCacheAdapter;
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

    /** @see it('isTypeOf used to resolve runtime type for Interface') */
    public function testIsValidationCachedWithAdapter(): void
    {
        $cache = new PsrValidationCacheAdapter(new Psr16Cache(new ArrayAdapter()));



        $petType = new InterfaceType([
            'name' => 'Pet',
            'fields' => [
                'name' => ['type' => Type::string()],
            ],
        ]);

        $DogType = new ObjectType([
            'name' => 'Dog',
            'interfaces' => [$petType],
            'isTypeOf' => static fn ($obj): Deferred => new Deferred(static fn (): bool => $obj instanceof Dog),
            'fields' => [
                'name' => ['type' => Type::string()],
                'woofs' => ['type' => Type::boolean()],
            ],
        ]);

        $CatType = new ObjectType([
            'name' => 'Cat',
            'interfaces' => [$petType],
            'isTypeOf' => static fn ($obj): Deferred => new Deferred(static fn (): bool => $obj instanceof Cat),
            'fields' => [
                'name' => ['type' => Type::string()],
                'meows' => ['type' => Type::boolean()],
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

        GraphQL::executeQuery( $schema, $query, null, null, null, null, null, null, $cache)->toArray();

        // TODO: use a spy or something to prove that the validation only happens once
        $result = GraphQL::executeQuery( $schema, $query, null, null, null, null, null, null, $cache)->toArray();

        $expected = [
            'data' => [
                'pets' => [
                    ['name' => 'Odie', 'woofs' => true],
                    ['name' => 'Garfield', 'meows' => false],
                ],
            ],
        ];

        self::assertEquals($expected, $result);
    }
}
