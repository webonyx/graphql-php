<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\GraphQL;
use GraphQL\Type\BuiltInDefinitions;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use PHPUnit\Framework\TestCase;

final class BuiltInDefinitionsTest extends TestCase
{
    public function testStandardReturnsSingleton(): void
    {
        $a = BuiltInDefinitions::standard();
        $b = BuiltInDefinitions::standard();
        self::assertSame($a, $b);
    }

    public function testDefaultInstanceMatchesStaticTypes(): void
    {
        $builtInDefinitions = BuiltInDefinitions::standard();

        self::assertSame(Type::int(), $builtInDefinitions->int());
        self::assertSame(Type::float(), $builtInDefinitions->float());
        self::assertSame(Type::string(), $builtInDefinitions->string());
        self::assertSame(Type::boolean(), $builtInDefinitions->boolean());
        self::assertSame(Type::id(), $builtInDefinitions->id());
    }

    public function testTwoInstancesProduceDistinctTypes(): void
    {
        $a = new BuiltInDefinitions();
        $b = new BuiltInDefinitions();

        self::assertNotSame($a->schemaType(), $b->schemaType());
        self::assertNotSame($a->typeType(), $b->typeType());
        self::assertNotSame($a->schemaMetaFieldDef(), $b->schemaMetaFieldDef());
        self::assertNotSame($a->includeDirective(), $b->includeDirective());
    }

    public function testCustomScalarOverridePropagates(): void
    {
        $customString = new CustomScalarType([
            'name' => Type::STRING,
            'serialize' => static fn ($value) => $value,
        ]);

        $builtInDefinitions = new BuiltInDefinitions([Type::STRING => $customString]);

        self::assertSame($customString, $builtInDefinitions->string());
        self::assertSame($customString, $builtInDefinitions->scalarTypes()[Type::STRING]);
        self::assertSame($customString, $builtInDefinitions->types()[Type::STRING]);

        $typeNameMeta = $builtInDefinitions->typeNameMetaFieldDef();
        $innerType = Type::getNullableType($typeNameMeta->getType());
        self::assertSame($customString, $innerType);

        $deprecatedDirective = $builtInDefinitions->deprecatedDirective();
        self::assertSame($customString, $deprecatedDirective->args[0]->getType());
    }

    public function testTwoSchemasWithDifferentBuiltInDefinitions(): void
    {
        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => ['hello' => Type::string()],
        ]);

        $builtInDefinitionsA = new BuiltInDefinitions();
        $builtInDefinitionsB = new BuiltInDefinitions();

        $schemaA = new Schema(
            (new SchemaConfig())
                ->setQuery($queryType)
                ->setBuiltInDefinitions($builtInDefinitionsA)
        );

        $schemaB = new Schema(
            (new SchemaConfig())
                ->setQuery($queryType)
                ->setBuiltInDefinitions($builtInDefinitionsB)
        );

        self::assertSame($builtInDefinitionsA, $schemaA->getBuiltInDefinitions());
        self::assertSame($builtInDefinitionsB, $schemaB->getBuiltInDefinitions());
        self::assertNotSame($schemaA->getBuiltInDefinitions(), $schemaB->getBuiltInDefinitions());

        self::assertNotSame(
            $schemaA->getBuiltInDefinitions()->schemaType(),
            $schemaB->getBuiltInDefinitions()->schemaType()
        );
    }

    public function testCorrectScalarOverrideWorksEndToEnd(): void
    {
        $upperString = new CustomScalarType([
            'name' => Type::STRING,
            'serialize' => static fn ($value) => strtoupper((string) $value),
        ]);

        $builtInDefs = new BuiltInDefinitions([Type::STRING => $upperString]);

        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'greeting' => [
                    'type' => $builtInDefs->string(), // use the instance, not the singleton
                    'resolve' => static fn (): string => 'hello world',
                ],
            ],
        ]);

        $schema = new Schema(
            (new SchemaConfig())
                ->setQuery($queryType)
                ->setBuiltInDefinitions($builtInDefs)
        );

        $schema->assertValid();

        $result = GraphQL::executeQuery($schema, '{ greeting }');
        self::assertSame([], $result->errors);
        assert(is_array($result->data));
        self::assertSame('HELLO WORLD', $result->data['greeting']);
    }

    /** @see https://github.com/webonyx/graphql-php/issues/1424 */
    public function testIsolatedSchemaFailsAtRuntime(): void
    {
        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'hello' => [
                    'type' => Type::string(), // natural, naive usage
                    'resolve' => static fn (): string => 'world',
                ],
            ],
        ]);

        $schema = new Schema(
            (new SchemaConfig())
                ->setQuery($queryType)
                ->setBuiltInDefinitions(new BuiltInDefinitions()) // no overrides, just isolation
                ->setAssumeValid(true) // skip validation; the real problem surfaces at runtime
        );

        $result = GraphQL::executeQuery($schema, '{ hello }');

        self::assertSame([], $result->errors);
        assert(is_array($result->data));
        self::assertSame('world', $result->data['hello']);
    }

    public function testAllTypesContainsScalarsAndIntrospection(): void
    {
        $builtInDefinitions = new BuiltInDefinitions();
        $allTypes = $builtInDefinitions->types();

        self::assertArrayHasKey('Int', $allTypes);
        self::assertArrayHasKey('String', $allTypes);
        self::assertArrayHasKey('__Schema', $allTypes);
        self::assertArrayHasKey('__Type', $allTypes);
        self::assertArrayHasKey('__TypeKind', $allTypes);
        self::assertCount(13, $allTypes);
    }

    public function testDirectivesReturnsAllStandard(): void
    {
        $builtInDefinitions = new BuiltInDefinitions();
        $directives = $builtInDefinitions->directives();

        self::assertArrayHasKey('include', $directives);
        self::assertArrayHasKey('skip', $directives);
        self::assertArrayHasKey('deprecated', $directives);
        self::assertArrayHasKey('oneOf', $directives);
        self::assertCount(4, $directives);
    }

    /** Scalar override must take effect even with assertions disabled and validation skipped. */
    public function testNaiveScalarOverrideIsIgnoredSilentlyInProduction(): void
    {
        $upperString = new CustomScalarType([
            'name' => Type::STRING,
            'serialize' => static fn ($value) => strtoupper((string) $value),
        ]);

        $builtInDefs = new BuiltInDefinitions([Type::STRING => $upperString]);

        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'greeting' => [
                    'type' => Type::string(), // natural, naive usage
                    'resolve' => static fn (): string => 'hello world',
                ],
            ],
        ]);

        $schema = new Schema(
            (new SchemaConfig())
                ->setQuery($queryType)
                ->setBuiltInDefinitions($builtInDefs)
                ->setAssumeValid(true)
        );

        $prevAssertActive = ini_get('assert.active');
        // ini_set('assert.active') is deprecated in PHP 8.3 but still functional;
        // we use it here only to simulate production mode (zend.assertions = -1).
        @ini_set('assert.active', '0');
        try {
            $result = GraphQL::executeQuery($schema, '{ greeting }');
        } finally {
            if (is_string($prevAssertActive)) {
                @ini_set('assert.active', $prevAssertActive);
            }
        }

        self::assertSame([], $result->errors);
        assert(is_array($result->data));
        self::assertSame('HELLO WORLD', $result->data['greeting']);
    }

    /** Scalar override via BuiltInDefinitions must not break validation when fields use Type::string(). */
    public function testNaiveScalarOverridePassesValidation(): void
    {
        $upperString = new CustomScalarType([
            'name' => Type::STRING,
            'serialize' => static fn ($value) => strtoupper((string) $value),
        ]);

        $builtInDefs = new BuiltInDefinitions([Type::STRING => $upperString]);

        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => ['greeting' => Type::string()], // natural, naive usage
        ]);

        $schema = new Schema(
            (new SchemaConfig())
                ->setQuery($queryType)
                ->setBuiltInDefinitions($builtInDefs)
        );

        $schema->assertValid();
        self::assertSame($upperString, $schema->getType(Type::STRING));
    }

    /** Scalar override must work at runtime even when fields use Type::string(). */
    public function testNaiveScalarOverrideIsUsedAtRuntime(): void
    {
        $upperString = new CustomScalarType([
            'name' => Type::STRING,
            'serialize' => static fn ($value) => strtoupper((string) $value),
        ]);

        $builtInDefs = new BuiltInDefinitions([Type::STRING => $upperString]);

        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'greeting' => [
                    'type' => Type::string(), // natural, naive usage
                    'resolve' => static fn (): string => 'hello world',
                ],
            ],
        ]);

        $schema = new Schema(
            (new SchemaConfig())
                ->setQuery($queryType)
                ->setBuiltInDefinitions($builtInDefs)
                ->setAssumeValid(true) // skip schema validation to reach execution
        );

        $result = GraphQL::executeQuery($schema, '{ greeting }');

        self::assertSame([], $result->errors);
        assert(is_array($result->data));
        self::assertSame('HELLO WORLD', $result->data['greeting']);
    }
}
