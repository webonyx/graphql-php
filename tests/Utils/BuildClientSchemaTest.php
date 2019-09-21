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
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Introspection;
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
        
        """This is simple type"""
        type Simple {
          """This is a string field"""
          string: String
        }
        ');
    }
}
