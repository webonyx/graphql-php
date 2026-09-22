<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

final class TrustingExecutorTest extends TestCase
{
    /** @see https://github.com/webonyx/graphql-php/issues/1493 */
    public function testTrustResultReturnsLeafValuesWithoutSerialization(): void
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'scalar' => [
                        'type' => Type::int(),
                        // returns a string where the declared type is Int
                        'resolve' => static fn (): string => '123',
                    ],
                ],
            ]),
        ]);

        $query = '{ scalar }';

        // by default the value is serialized through the scalar type
        $result = Executor::execute($schema, Parser::parse($query));
        self::assertSame(['scalar' => 123], $result->data);

        // when the result is trusted, it is put into the response as it is
        $result = Executor::execute($schema, Parser::parse($query), null, null, null, null, null, true);
        self::assertSame(['scalar' => '123'], $result->data);
    }

    public function testTrustResultSkipsFailingSerialization(): void
    {
        $throwingScalar = new CustomScalarType([
            'name' => 'Throwing',
            'serialize' => static function (): void {
                throw new \Exception('serialize should not be called');
            },
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'value' => [
                        'type' => $throwingScalar,
                        'resolve' => static fn (): string => 'raw',
                    ],
                ],
            ]),
        ]);

        $query = '{ value }';

        $result = Executor::execute($schema, Parser::parse($query));
        self::assertCount(1, $result->errors);
        self::assertStringContainsString('serialize should not be called', $result->errors[0]->getMessage());

        $result = Executor::execute($schema, Parser::parse($query), null, null, null, null, null, true);
        self::assertCount(0, $result->errors);
        self::assertSame(['value' => 'raw'], $result->data);
    }

    public function testTrustResultSkipsIsTypeOf(): void
    {
        $someType = new ObjectType([
            'name' => 'SomeType',
            'fields' => [
                'foo' => ['type' => Type::string()],
            ],
            'isTypeOf' => static fn (): bool => false,
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'obj' => [
                        'type' => $someType,
                        'resolve' => static fn (): array => ['foo' => 'bar'],
                    ],
                ],
            ]),
        ]);

        $query = '{ obj { foo } }';

        $result = Executor::execute($schema, Parser::parse($query));
        self::assertCount(1, $result->errors);
        self::assertStringContainsString('Expected value of type "SomeType" but got:', $result->errors[0]->getMessage());

        $result = Executor::execute($schema, Parser::parse($query), null, null, null, null, null, true);
        self::assertCount(0, $result->errors);
        self::assertSame(['obj' => ['foo' => 'bar']], $result->data);
    }

    public function testTrustResultStillResolvesAbstractTypes(): void
    {
        $catType = new ObjectType([
            'name' => 'Cat',
            'fields' => ['meows' => ['type' => Type::boolean()]],
        ]);
        $dogType = new ObjectType([
            'name' => 'Dog',
            'fields' => ['barks' => ['type' => Type::boolean()]],
        ]);

        $petType = new UnionType([
            'name' => 'Pet',
            'types' => [$catType, $dogType],
            'resolveType' => static fn (array $pet): ObjectType => $pet['kind'] === 'cat'
                ? $catType
                : $dogType,
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pet' => [
                        'type' => $petType,
                        'resolve' => static fn (): array => ['kind' => 'cat', 'meows' => true],
                    ],
                ],
            ]),
        ]);

        $query = '{ pet { ... on Cat { meows } } }';

        $result = Executor::execute($schema, Parser::parse($query), null, null, null, null, null, true);
        self::assertCount(0, $result->errors);
        self::assertSame(['pet' => ['meows' => true]], $result->data);
    }

    public function testTrustResultSkipsInterfaceRuntimeTypeValidation(): void
    {
        $implType = new ObjectType([
            'name' => 'Impl',
            'fields' => ['foo' => ['type' => Type::string()]],
            'interfaces' => [],
        ]);

        $iface = new InterfaceType([
            'name' => 'Iface',
            'fields' => ['foo' => ['type' => Type::string()]],
            'resolveType' => static fn (): ObjectType => $implType,
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'iface' => [
                        'type' => $iface,
                        'resolve' => static fn (): array => ['foo' => 'bar'],
                    ],
                ],
            ]),
            'types' => [$implType],
        ]);

        $query = '{ iface { foo } }';

        // Impl is not a possible type of Iface, so the runtime type is rejected
        $result = Executor::execute($schema, Parser::parse($query));
        self::assertCount(1, $result->errors);
        self::assertStringContainsString('is not a possible type for "Iface"', $result->errors[0]->getMessage());

        // when the result is trusted, the resolved type is used as it is
        $result = Executor::execute($schema, Parser::parse($query), null, null, null, null, null, true);
        self::assertCount(0, $result->errors);
        self::assertSame(['iface' => ['foo' => 'bar']], $result->data);
    }

    public function testTrustResultStillEnforcesNonNull(): void
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'nonNull' => [
                        'type' => Type::nonNull(Type::string()),
                        'resolve' => static fn (): ?string => null,
                    ],
                ],
            ]),
        ]);

        $query = '{ nonNull }';

        foreach ([false, true] as $trustResult) {
            $result = Executor::execute($schema, Parser::parse($query), null, null, null, null, null, $trustResult);
            self::assertCount(1, $result->errors);
            self::assertStringContainsString('Cannot return null for non-nullable field "Query.nonNull".', $result->errors[0]->getMessage());
        }
    }

    public function testTrustResultStillRequiresIterableForList(): void
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'list' => [
                        'type' => Type::listOf(Type::string()),
                        'resolve' => static fn (): string => 'not an iterable',
                    ],
                ],
            ]),
        ]);

        $query = '{ list }';

        foreach ([false, true] as $trustResult) {
            $result = Executor::execute($schema, Parser::parse($query), null, null, null, null, null, $trustResult);
            self::assertCount(1, $result->errors);
            self::assertStringContainsString('Expected field Query.list to return iterable, but got: string.', $result->errors[0]->getMessage());
        }
    }

    public function testTrustResultInvalidRuntimeTypeNameProducesFieldError(): void
    {
        $petType = new UnionType([
            'name' => 'Pet',
            'types' => [
                new ObjectType([
                    'name' => 'Cat',
                    'fields' => ['meows' => ['type' => Type::boolean()]],
                ]),
            ],
            'resolveType' => static fn (): string => 'DoesNotExist',
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'pet' => [
                        'type' => $petType,
                        'resolve' => static fn (): array => ['kind' => 'cat'],
                    ],
                ],
            ]),
        ]);

        $query = '{ pet { ... on Cat { meows } } }';

        $result = Executor::execute($schema, Parser::parse($query), null, null, null, null, null, true);
        self::assertCount(1, $result->errors);
    }
}
