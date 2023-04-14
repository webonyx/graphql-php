<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase
{
    private InterfaceType $interfaceType;

    private ObjectType $implementingType;

    private InputObjectType $directiveInputType;

    private InputObjectType $wrappedDirectiveInputType;

    private Directive $directive;

    private Schema $schema;

    public function setUp(): void
    {
        $this->interfaceType = new InterfaceType([
            'name' => 'Interface',
            'fields' => ['fieldName' => ['type' => Type::string()]],
        ]);

        $this->implementingType = new ObjectType([
            'name' => 'Object',
            'interfaces' => [$this->interfaceType],
            'fields' => [
                'fieldName' => [
                    'type' => Type::string(),
                    'resolve' => static fn (): string => '',
                ],
            ],
        ]);

        $this->directiveInputType = new InputObjectType([
            'name' => 'DirInput',
            'fields' => [
                'field' => [
                    'type' => Type::string(),
                ],
            ],
        ]);

        $this->wrappedDirectiveInputType = new InputObjectType([
            'name' => 'WrappedDirInput',
            'fields' => [
                'field' => [
                    'type' => Type::string(),
                ],
            ],
        ]);

        $this->directive = new Directive([
            'name' => 'dir',
            'locations' => ['OBJECT'],
            'args' => [
                'arg' => [
                    'type' => $this->directiveInputType,
                ],
                'argList' => [
                    'type' => Type::listOf($this->wrappedDirectiveInputType),
                ],
            ],
        ]);

        $this->schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'getObject' => [
                        'type' => $this->interfaceType,
                        'resolve' => static fn (): array => [],
                    ],
                ],
            ]),
            'directives' => [$this->directive],
        ]);
    }

    // Type System: Schema
    // Getting possible types

    /**
     * @see it('throws human-reable error if schema.types is not defined')
     */
    public function testThrowsHumanReableErrorIfSchemaTypesIsNotDefined(): void
    {
        self::markTestSkipped("Can't check interface implementations without full schema scan");

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'Could not find possible implementing types for Interface in schema. '
            . 'Check that schema.types is defined and is an array of all possible '
            . 'types in the schema.'
        );
        $this->schema->isSubType($this->interfaceType, $this->implementingType);
    }

    // Type Map

    /**
     * @see it('includes input types only used in directives')
     */
    public function testIncludesInputTypesOnlyUsedInDirectives(): void
    {
        $typeMap = $this->schema->getTypeMap();
        self::assertArrayHasKey('DirInput', $typeMap);
        self::assertArrayHasKey('WrappedDirInput', $typeMap);
    }

    /**
     * @see https://github.com/webonyx/graphql-php/issues/997
     */
    public function testSchemaReturnsNullForNonexistentType(): void
    {
        self::assertNull($this->schema->getType('UnknownType'));
    }
}
