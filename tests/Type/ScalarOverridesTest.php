<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use PHPUnit\Framework\TestCase;

/**
 * @see https://github.com/webonyx/graphql-php/issues/1424
 * @see StandardTypesTest for global static override tests
 */
final class ScalarOverridesTest extends TestCase
{
    /** @var array<string, ScalarType> */
    private static array $originalStandardTypes;

    public static function setUpBeforeClass(): void
    {
        self::$originalStandardTypes = Type::getStandardTypes();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        Type::overrideStandardTypes(self::$originalStandardTypes);
    }

    public function testTypeLoaderOverrideWorksEndToEnd(): void
    {
        $uppercaseString = self::createUppercaseString();
        $queryType = self::createQueryType();
        $types = ['Query' => $queryType, 'String' => $uppercaseString];

        $schema = new Schema([
            'query' => $queryType,
            'typeLoader' => static fn (string $name): ?Type => $types[$name] ?? null,
        ]);

        $schema->assertValid();

        $result = GraphQL::executeQuery($schema, '{ greeting }');

        self::assertSame(['data' => ['greeting' => 'HELLO WORLD']], $result->toArray());
    }

    public function testTypeLoaderOverrideWorksInProductionMode(): void
    {
        $assertActive = (int) ini_get('assert.active');
        @ini_set('assert.active', '0');

        try {
            $uppercaseString = self::createUppercaseString();
            $queryType = self::createQueryType();
            $types = ['Query' => $queryType, 'String' => $uppercaseString];

            $schema = new Schema([
                'query' => $queryType,
                'typeLoader' => static fn (string $name): ?Type => $types[$name] ?? null,
            ]);

            $result = GraphQL::executeQuery($schema, '{ greeting }');

            self::assertSame(['data' => ['greeting' => 'HELLO WORLD']], $result->toArray());
        } finally {
            @ini_set('assert.active', (string) $assertActive);
        }
    }

    public function testTypesConfigOverrideWorksEndToEnd(): void
    {
        $uppercaseString = self::createUppercaseString();

        $schema = new Schema([
            'query' => self::createQueryType(),
            'types' => [$uppercaseString],
        ]);

        $schema->assertValid();

        $result = GraphQL::executeQuery($schema, '{ greeting }');

        self::assertSame(['data' => ['greeting' => 'HELLO WORLD']], $result->toArray());
    }

    public function testTypesConfigOverrideWorksWithAssumeValid(): void
    {
        $uppercaseString = self::createUppercaseString();

        $config = SchemaConfig::create([
            'query' => self::createQueryType(),
            'types' => [$uppercaseString],
        ]);
        $config->setAssumeValid(true);

        $schema = new Schema($config);

        $result = GraphQL::executeQuery($schema, '{ greeting }');

        self::assertSame(['data' => ['greeting' => 'HELLO WORLD']], $result->toArray());
    }

    public function testIntrospectionUsesOverriddenScalar(): void
    {
        $uppercaseString = self::createUppercaseString();
        $queryType = self::createQueryType();
        $types = ['Query' => $queryType, 'String' => $uppercaseString];

        $schema = new Schema([
            'query' => $queryType,
            'typeLoader' => static fn (string $name): ?Type => $types[$name] ?? null,
        ]);

        $result = GraphQL::executeQuery($schema, '{ __type(name: "Query") { fields { name } } }');

        $data = $result->toArray()['data'] ?? [];
        $fields = $data['__type']['fields'];
        $fieldNames = array_column($fields, 'name');

        self::assertContains('GREETING', $fieldNames);
    }

    public function testTwoSchemasWithDifferentOverridesAreIndependent(): void
    {
        $uppercaseString = new CustomScalarType([
            'name' => Type::STRING,
            'serialize' => static fn ($value): string => strtoupper((string) $value),
        ]);
        $reversedString = new CustomScalarType([
            'name' => Type::STRING,
            'serialize' => static fn ($value): string => strrev((string) $value),
        ]);

        $queryTypeA = self::createQueryType();
        $typesA = ['Query' => $queryTypeA, 'String' => $uppercaseString];
        $schemaA = new Schema([
            'query' => $queryTypeA,
            'typeLoader' => static fn (string $name): ?Type => $typesA[$name] ?? null,
        ]);

        $queryTypeB = self::createQueryType();
        $typesB = ['Query' => $queryTypeB, 'String' => $reversedString];
        $schemaB = new Schema([
            'query' => $queryTypeB,
            'typeLoader' => static fn (string $name): ?Type => $typesB[$name] ?? null,
        ]);

        $resultA = GraphQL::executeQuery($schemaA, '{ greeting }');
        $resultB = GraphQL::executeQuery($schemaB, '{ greeting }');

        self::assertSame(['data' => ['greeting' => 'HELLO WORLD']], $resultA->toArray());
        self::assertSame(['data' => ['greeting' => 'dlrow olleh']], $resultB->toArray());
    }

    public function testNonOverriddenScalarsAreUnaffected(): void
    {
        $uppercaseString = self::createUppercaseString();
        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'greeting' => [
                    'type' => Type::string(),
                    'resolve' => static fn (): string => 'hello world',
                ],
                'count' => [
                    'type' => Type::int(),
                    'resolve' => static fn (): int => 42,
                ],
                'ratio' => [
                    'type' => Type::float(),
                    'resolve' => static fn (): float => 3.14,
                ],
                'active' => [
                    'type' => Type::boolean(),
                    'resolve' => static fn (): bool => true,
                ],
                'identifier' => [
                    'type' => Type::id(),
                    'resolve' => static fn (): string => 'abc-123',
                ],
            ],
        ]);

        $types = ['Query' => $queryType, 'String' => $uppercaseString];

        $schema = new Schema([
            'query' => $queryType,
            'typeLoader' => static fn (string $name): ?Type => $types[$name] ?? null,
        ]);

        $result = GraphQL::executeQuery($schema, '{ greeting count ratio active identifier }');
        $data = $result->toArray()['data'] ?? [];

        self::assertSame('HELLO WORLD', $data['greeting']);
        self::assertSame(42, $data['count']);
        self::assertSame(3.14, $data['ratio']);
        self::assertTrue($data['active']);
        self::assertSame('abc-123', $data['identifier']);
    }

    /** @throws InvariantViolation */
    private static function createUppercaseString(): CustomScalarType
    {
        return new CustomScalarType([
            'name' => Type::STRING,
            'serialize' => static fn ($value): string => strtoupper((string) $value),
        ]);
    }

    /** @throws InvariantViolation */
    private static function createQueryType(): ObjectType
    {
        return new ObjectType([
            'name' => 'Query',
            'fields' => [
                'greeting' => [
                    'type' => Type::string(),
                    'resolve' => static fn (): string => 'hello world',
                ],
            ],
        ]);
    }
}
