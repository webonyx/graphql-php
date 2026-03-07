<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Type\BuiltInTypes;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use PHPUnit\Framework\TestCase;

final class BuiltInTypesTest extends TestCase
{
    /** @var array<string, \GraphQL\Type\Definition\ScalarType> */
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

    public function testStandardReturnsSingleton(): void
    {
        $a = BuiltInTypes::standard();
        $b = BuiltInTypes::standard();
        self::assertSame($a, $b);
    }

    public function testResetStandardClearsSingleton(): void
    {
        $before = BuiltInTypes::standard();
        BuiltInTypes::resetStandard();
        $after = BuiltInTypes::standard();
        self::assertNotSame($before, $after);
    }

    public function testDefaultInstanceMatchesStaticTypes(): void
    {
        $builtIn = BuiltInTypes::standard();

        self::assertSame(Type::int(), $builtIn->int());
        self::assertSame(Type::float(), $builtIn->float());
        self::assertSame(Type::string(), $builtIn->string());
        self::assertSame(Type::boolean(), $builtIn->boolean());
        self::assertSame(Type::id(), $builtIn->id());
    }

    public function testTwoInstancesProduceDistinctTypes(): void
    {
        $a = new BuiltInTypes();
        $b = new BuiltInTypes();

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

        $builtIn = new BuiltInTypes([Type::STRING => $customString]);

        self::assertSame($customString, $builtIn->string());
        self::assertSame($customString, $builtIn->standardTypes()[Type::STRING]);
        self::assertSame($customString, $builtIn->allTypes()[Type::STRING]);

        $typeNameMeta = $builtIn->typeNameMetaFieldDef();
        $innerType = Type::getNullableType($typeNameMeta->getType());
        self::assertSame($customString, $innerType);

        $deprecatedDirective = $builtIn->deprecatedDirective();
        self::assertSame($customString, $deprecatedDirective->args[0]->getType());
    }

    public function testTwoSchemasWithDifferentBuiltInTypes(): void
    {
        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => ['hello' => Type::string()],
        ]);

        $builtInA = new BuiltInTypes();
        $builtInB = new BuiltInTypes();

        $schemaA = new Schema(
            (new SchemaConfig())
                ->setQuery($queryType)
                ->setBuiltInTypes($builtInA)
                ->setAssumeValid(true)
        );

        $schemaB = new Schema(
            (new SchemaConfig())
                ->setQuery($queryType)
                ->setBuiltInTypes($builtInB)
                ->setAssumeValid(true)
        );

        self::assertSame($builtInA, $schemaA->getBuiltInTypes());
        self::assertSame($builtInB, $schemaB->getBuiltInTypes());
        self::assertNotSame($schemaA->getBuiltInTypes(), $schemaB->getBuiltInTypes());

        self::assertNotSame(
            $schemaA->getBuiltInTypes()->schemaType(),
            $schemaB->getBuiltInTypes()->schemaType()
        );
    }

    public function testAllTypesContainsScalarsAndIntrospection(): void
    {
        $builtIn = new BuiltInTypes();
        $allTypes = $builtIn->allTypes();

        self::assertArrayHasKey('Int', $allTypes);
        self::assertArrayHasKey('String', $allTypes);
        self::assertArrayHasKey('__Schema', $allTypes);
        self::assertArrayHasKey('__Type', $allTypes);
        self::assertArrayHasKey('__TypeKind', $allTypes);
        self::assertCount(13, $allTypes);
    }

    public function testDirectivesReturnsAllStandard(): void
    {
        $builtIn = new BuiltInTypes();
        $directives = $builtIn->directives();

        self::assertArrayHasKey('include', $directives);
        self::assertArrayHasKey('skip', $directives);
        self::assertArrayHasKey('deprecated', $directives);
        self::assertArrayHasKey('oneOf', $directives);
        self::assertCount(4, $directives);
    }
}
