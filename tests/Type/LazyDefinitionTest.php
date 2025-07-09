<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Tests\TestCaseBase;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\IntType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\StringType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

final class LazyDefinitionTest extends TestCaseBase
{
    public function testAllowsTypeWhichDefinesItFieldsAsClosureReturningFieldDefinitionAsArray(): void
    {
        $objType = new ObjectType([
            'name' => 'SomeObject',
            'fields' => [
                'f' => static fn (): array => [
                    'type' => Type::string(),
                ],
            ],
        ]);

        $objType->assertValid();

        self::assertSame(Type::string(), $objType->getField('f')->getType());
    }

    public function testAllowsTypeWhichDefinesItFieldsAsClosureReturningFieldDefinitionAsObject(): void
    {
        $objType = new ObjectType([
            'name' => 'SomeObject',
            'fields' => [
                'f' => static fn (): FieldDefinition => new FieldDefinition([
                    'name' => 'f',
                    'type' => Type::string(),
                ]),
            ],
        ]);
        $objType->assertValid();

        self::assertSame(Type::string(), $objType->getField('f')->getType());
    }

    public function testAllowsTypeWhichDefinesItFieldsAsInvokableClassReturningFieldDefinitionAsArray(): void
    {
        $objType = new ObjectType([
            'name' => 'SomeObject',
            'fields' => [
                'f' => new class() {
                    /**
                     * @throws InvariantViolation
                     *
                     * @return array{type: ScalarType}
                     */
                    public function __invoke(): array
                    {
                        return ['type' => Type::string()];
                    }
                },
            ],
        ]);
        $objType->assertValid();

        self::assertSame(Type::string(), $objType->getField('f')->getType());
    }

    public function testLazyFieldNotExecutedIfNotAccessed(): void
    {
        $resolvedCount = 0;
        $fieldCallback = static function () use (&$resolvedCount): array {
            ++$resolvedCount;

            return ['type' => Type::string()];
        };

        $objType = new ObjectType([
            'name' => 'SomeObject',
            'fields' => [
                'f' => $fieldCallback,
                'b' => static function (): void {
                    throw new \RuntimeException('Would not expect this to be called!');
                },
            ],
        ]);

        self::assertSame(Type::string(), $objType->getField('f')->getType());
        self::assertSame(1, $resolvedCount);
    }

    public function testLazyFieldNotExecutedIfNotAccessedInQuery(): void
    {
        $resolvedCount = 0;
        $lazyField = static function () use (&$resolvedCount): array {
            ++$resolvedCount;

            return ['type' => Type::string()];
        };

        $query = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'f' => $lazyField,
                'b' => static function (): void {
                    throw new \RuntimeException('Would not expect this to be called!');
                },
            ],
        ]);

        $schema = new Schema([
            'query' => $query,
        ]);
        $result = Executor::execute($schema, Parser::parse('{ f }'));

        self::assertSame(['f' => null], $result->data);
        self::assertSame(1, $resolvedCount);
    }

    public function testLazyFieldsAreResolvedWhenValidatingType(): void
    {
        $resolvedCount = 0;
        $fieldCallback = static function () use (&$resolvedCount): array {
            ++$resolvedCount;

            return ['type' => Type::string()];
        };

        $objType = new ObjectType([
            'name' => 'SomeObject',
            'fields' => [
                'f' => $fieldCallback,
                'o' => $fieldCallback,
            ],
        ]);
        $objType->assertValid();

        self::assertSame(Type::string(), $objType->getField('f')->getType());
        self::assertSame(2, $resolvedCount);
    }

    public function testThrowsWhenLazyFieldDefinitionHasNoKeysForFieldNames(): void
    {
        $objType = new ObjectType([
            'name' => 'SomeObject',
            'fields' => [
                static fn (): array => [
                    'type' => Type::string(),
                ],
            ],
        ]);

        $this->expectExceptionObject(new InvariantViolation(
            'SomeObject lazy fields must be an associative array with field names as keys.'
        ));

        $objType->assertValid();
    }

    public function testReturningFieldsUsingYield(): void
    {
        $type = new ObjectType([
            'name' => 'Query',
            'fields' => static function (): \Generator {
                yield 'url' => ['type' => Type::string()];
                yield 'width' => ['type' => Type::int()];
            },
        ]);

        $blogSchema = new Schema([
            'query' => $type,
        ]);

        self::assertSame($blogSchema->getQueryType(), $type);

        $field = $type->getField('url');
        self::assertSame('url', $field->name);
        self::assertInstanceOf(StringType::class, $field->getType());

        $field = $type->getField('width');
        self::assertSame('width', $field->name);
        self::assertInstanceOf(IntType::class, $field->getType());
    }

    public function testLazyRootTypes(): void
    {
        $query = new ObjectType([
            'name' => 'Query',
            'fields' => [],
        ]);
        $mutation = new ObjectType([
            'name' => 'Mutation',
            'fields' => [],
        ]);
        $subscription = new ObjectType([
            'name' => 'Subscription',
            'fields' => [],
        ]);

        $schema = new Schema([
            'query' => fn () => $query,
            'mutation' => fn () => $mutation,
            'subscription' => fn () => $subscription,
        ]);

        self::assertSame($schema->getQueryType(), $query);
        self::assertSame($schema->getMutationType(), $mutation);
        self::assertSame($schema->getSubscriptionType(), $subscription);
    }

    public function testLazyRootTypesNull(): void
    {
        $schema = new Schema([
            'query' => fn () => null,
            'mutation' => fn () => null,
            'subscription' => fn () => null,
        ]);

        self::assertNull($schema->getQueryType());
        self::assertNull($schema->getMutationType());
        self::assertNull($schema->getSubscriptionType());
    }
}
