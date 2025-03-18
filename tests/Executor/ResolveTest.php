<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use GraphQL\Error\InvariantViolation;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

use function Safe\json_encode;

/**
 * @phpstan-import-type UnnamedFieldDefinitionConfig from FieldDefinition
 */
final class ResolveTest extends TestCase
{
    // Execute: resolve function

    /** @see it('default function accesses properties') */
    public function testDefaultFunctionAccessesProperties(): void
    {
        $schema = $this->buildSchema(['type' => Type::string()]);

        $source = ['test' => 'testValue'];

        self::assertSame(
            ['data' => ['test' => 'testValue']],
            GraphQL::executeQuery($schema, '{ test }', $source)->toArray()
        );
    }

    /**
     * @phpstan-param UnnamedFieldDefinitionConfig $testField
     *
     * @throws InvariantViolation
     */
    private function buildSchema(array $testField): Schema
    {
        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => ['test' => $testField],
            ]),
        ]);
    }

    /** @see it('default function calls methods') */
    public function testDefaultFunctionCallsClosures(): void
    {
        $schema = $this->buildSchema(['type' => Type::string()]);
        $_secret = 'secretValue' . uniqid();

        $source = [
            'test' => static fn (): string => $_secret,
        ];
        self::assertEquals(
            ['data' => ['test' => $_secret]],
            GraphQL::executeQuery($schema, '{ test }', $source)->toArray()
        );
    }

    /** @see it('default function passes args and context') */
    public function testDefaultFunctionPassesArgsAndContext(): void
    {
        $schema = $this->buildSchema([
            'type' => Type::int(),
            'args' => [
                'addend1' => [
                    'type' => Type::int(),
                ],
            ],
        ]);

        $result = GraphQL::executeQuery(
            $schema,
            '{ test(addend1: 80) }',
            [
                'test' => fn ($objectValue, array $args, array $context): float => 700
                    + $args['addend1']
                    + $context['addend2'],
            ],
            ['addend2' => 9],
        )->toArray();

        self::assertSame([
            'data' => [
                'test' => 789,
            ],
        ], $result);
    }

    /** @see it('uses provided resolve function') */
    public function testUsesProvidedResolveFunction(): void
    {
        $schema = $this->buildSchema([
            'type' => Type::string(),
            'args' => [
                'aStr' => ['type' => Type::string()],
                'aInt' => ['type' => Type::int()],
            ],
            'resolve' => static fn (?string $source, array $args) => json_encode([$source, $args], JSON_THROW_ON_ERROR),
        ]);

        self::assertSame(
            ['data' => ['test' => '[null,[]]']],
            GraphQL::executeQuery($schema, '{ test }')->toArray()
        );

        self::assertSame(
            ['data' => ['test' => '["Source!",[]]']],
            GraphQL::executeQuery($schema, '{ test }', 'Source!')->toArray()
        );

        self::assertSame(
            ['data' => ['test' => '["Source!",{"aStr":"String!"}]']],
            GraphQL::executeQuery($schema, '{ test(aStr: "String!") }', 'Source!')->toArray()
        );

        self::assertSame(
            ['data' => ['test' => '["Source!",{"aStr":"String!","aInt":-123}]']],
            GraphQL::executeQuery($schema, '{ test(aInt: -123, aStr: "String!") }', 'Source!')->toArray()
        );
    }
}
