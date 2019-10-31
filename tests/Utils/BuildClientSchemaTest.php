<?php

declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use Closure;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildClientSchema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaPrinter;
use PHPUnit\Framework\TestCase;
use function array_keys;
use function count;

/**
 * @see
 */
class BuildClientSchemaTest extends TestCase
{
    protected static function assertCycleIntrospection(string $sdl): void
    {
        $serverSchema = BuildSchema::build($sdl);
        $initialIntrospection = Introspection::fromSchema($serverSchema);
        $clientSchema = BuildClientSchema::build($initialIntrospection);
        $secondIntrospection = Introspection::fromSchema($clientSchema);

        self::assertSame($initialIntrospection, $secondIntrospection);
    }

    protected static function introspectionFromSDL(string $sdl): array
    {
        $schema = BuildSchema::build($sdl);

        return Introspection::fromSchema($schema);
    }

    protected static function clientSchemaFromSDL(string $sdl): Schema
    {
        $introspection = self::introspectionFromSDL($sdl);

        return BuildClientSchema::build($introspection);
    }

    // describe('Type System: build schema from introspection', () => {

    /**
     * @see it('builds a simple schema', () => {
     */
    public function testBuildsASimpleSchema() : void
    {
        self::assertCycleIntrospection('
        schema {
          query: Simple
        }
        
        """This is a simple type"""
        type Simple {
          """This is a string field"""
          string: String
        }
        ');
    }

    /**
     * it('builds a schema without the query type', () => {
     */
    public function testBuildsASchemaWithoutTheQueryType(): void
    {
        $sdl = '
        type Query {
          foo: String
        }
        ';
        $introspection = self::introspectionFromSDL($sdl);
        unset($introspection['__schema']['queryType']);

        $clientSchema = BuildClientSchema::build($introspection);
        $this->assertNull($clientSchema->getQueryType());
        $this->markTestSkipped('Why should this assertion be true?');
        $this->assertSame($sdl, SchemaPrinter::printIntrospectionSchema($clientSchema));
    }

    /**
     * it('builds a simple schema with all operation types', () => {
     */
    public function testBuildsASimpleSchemaWithAllOperationTypes(): void
    {
        self::assertCycleIntrospection('
          schema {
            query: QueryType
            mutation: MutationType
            subscription: SubscriptionType
          }
    
          """This is a simple mutation type"""
          type MutationType {
            """Set the string field"""
            string: String
          }
    
          """This is a simple query type"""
          type QueryType {
            """This is a string field"""
            string: String
          }
    
          """This is a simple subscription type"""
          type SubscriptionType {
            """This is a string field"""
            string: String
          }
        ');
    }

    /**
     * it('uses built-in scalars when possible', () => {
     */
    public function testUsesBuiltInScalarsWhenPossible(): void
    {
        $sdl = '
          scalar CustomScalar
    
          type Query {
            int: Int
            float: Float
            string: String
            boolean: Boolean
            id: ID
            custom: CustomScalar
          }
        ';

        self::assertCycleIntrospection($sdl);

        $schema = BuildSchema::build($sdl);
        $introspection = Introspection::fromSchema($schema);
        $clientSchema = BuildClientSchema::build($introspection);

        // Built-ins are used
        $this->assertSame(Type::int(), $clientSchema->getType('Int'));
        $this->assertSame(Type::float(), $clientSchema->getType('Float'));
        $this->assertSame(Type::string(), $clientSchema->getType('String'));
        $this->assertSame(Type::boolean(), $clientSchema->getType('Boolean'));
        $this->assertSame(Type::id(), $clientSchema->getType('ID'));

        // Custom are built
        $this->assertNotSame(
            $schema->getType('CustomScalar'),
            $clientSchema->getType('CustomScalar')
        );
    }

    /**
     * it('includes standard types only if they are used', () => {
     */
    public function testIncludesStandardTypesOnlyIfTheyAreUsed(): void
    {
        $clientSchema = self::clientSchemaFromSDL('
          type Query {
            foo: String
          }
        ');

        $this->assertNull($clientSchema->getType('Int'));
    }
}
