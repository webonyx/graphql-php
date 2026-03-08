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

    /**
     * The type isolation case — no scalar overrides, just fresh BuiltInDefinitions instances
     * for each schema — already breaks at runtime when setAssumeValid(true) is used to skip
     * validation. A field typed via Type::string() holds the global singleton StringType,
     * but the schema's BuiltInDefinitions owns a different StringType instance. The executor
     * detects the mismatch and rejects the field with a "Found duplicate type" error.
     *
     * This is the scenario described in https://github.com/webonyx/graphql-php/issues/1424:
     * schemas that should be independent still collide through built-in type instances.
     *
     * This test documents that failure. It should pass once the issue is resolved.
     */
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

        // Expects no errors and hello => 'world'.
        // Actual: field error "Found duplicate type in schema at Query.hello: String"
        // because Type::string() (singleton) != new BuiltInDefinitions()->string() (fresh instance).
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

    /**
     * In production PHP (zend.assertions = -1), the executor's runtime type-identity
     * assert() at ReferenceExecutor.php is compiled out. setAssumeValid(true) already
     * skips schema validation. With both disabled, execution completes without error —
     * but silently uses the singleton StringType's serialize(), not the custom one.
     * The result is wrong and there is no indication that the override was ignored.
     *
     * This test simulates that scenario by disabling assert.active at runtime.
     * It documents that failure. It should pass once the issue is resolved.
     */
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
            @ini_set('assert.active', $prevAssertActive);
        }

        // No errors — setAssumeValid skips schema validation and the disabled assert
        // suppresses the executor's runtime identity check. Execution "succeeds".
        self::assertSame([], $result->errors);
        assert(is_array($result->data));
        // Expects 'HELLO WORLD' from the custom String serializer.
        // Actual: 'hello world' — the field holds a reference to the singleton StringType,
        // which is what the executor calls serialize() on. The override is silently lost.
        self::assertSame('HELLO WORLD', $result->data['greeting']);
    }

    /**
     * A user naturally reaches for Type::string() when defining fields — the same way every
     * example and doc in this repository does. When they pair that with a BuiltInDefinitions
     * override they expect the custom scalar to take effect. Instead, schema validation blows
     * up because two different StringType instances with the same name end up in the type map.
     *
     * This test documents that failure. It should pass once the issue is resolved.
     */
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

        // Fails: "Schema must contain unique named types but contains multiple types named "String""
        $schema->assertValid();
    }

    /**
     * Even when validation is skipped, the custom String scalar is never invoked at runtime.
     * Fields defined via Type::string() hold a direct reference to the singleton StringType.
     * The executor detects that the field's type instance differs from the schema's String
     * (the two instances have the same name but are not identical), and rejects the field
     * with: "Found duplicate type in schema at Query.greeting: String.".
     *
     * The field resolves to null with a GraphQL error — not even falling back to the
     * singleton's standard serialization, let alone invoking the custom one.
     *
     * This test documents that failure. It should pass once the issue is resolved.
     */
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

        // Expects no errors and 'HELLO WORLD' from the custom String serializer.
        // Actual: field error "Found duplicate type in schema at Query.greeting: String"
        // and greeting => null, because the executor sees two different StringType instances.
        self::assertSame([], $result->errors);
        assert(is_array($result->data));
        self::assertSame('HELLO WORLD', $result->data['greeting']);
    }
}
