<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

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
                ->setAssumeValid(true)
        );

        $schemaB = new Schema(
            (new SchemaConfig())
                ->setQuery($queryType)
                ->setBuiltInDefinitions($builtInDefinitionsB)
                ->setAssumeValid(true)
        );

        self::assertSame($builtInDefinitionsA, $schemaA->getBuiltInDefinitions());
        self::assertSame($builtInDefinitionsB, $schemaB->getBuiltInDefinitions());
        self::assertNotSame($schemaA->getBuiltInDefinitions(), $schemaB->getBuiltInDefinitions());

        self::assertNotSame(
            $schemaA->getBuiltInDefinitions()->schemaType(),
            $schemaB->getBuiltInDefinitions()->schemaType()
        );
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
}
