<?php

declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

class SchemaTest extends TestCase
{
    /** @var InterfaceType */
    private $interfaceType;

    /** @var ObjectType */
    private $implementingType;

    /** @var InputObjectType */
    private $directiveInputType;

    /** @var InputObjectType */
    private $wrappedDirectiveInputType;

    /** @var Directive */
    private $directive;

    /** @var Schema */
    private $schema;

    public function setUp() : void
    {
        $this->interfaceType = new InterfaceType([
            'name'   => 'Interface',
            'fields' => ['fieldName' => ['type' => Type::string()]],
        ]);

        $this->implementingType = new ObjectType([
            'name'       => 'Object',
            'interfaces' => [$this->interfaceType],
            'fields'     => [
                'fieldName' => [
                    'type'    => Type::string(),
                    'resolve' => static function () : string {
                        return '';
                    },
                ],
            ],
        ]);

        $this->directiveInputType = new InputObjectType([
            'name'   => 'DirInput',
            'fields' => [
                'field' => [
                    'type' => Type::string(),
                ],
            ],
        ]);

        $this->wrappedDirectiveInputType = new InputObjectType([
            'name'   => 'WrappedDirInput',
            'fields' => [
                'field' => [
                    'type' => Type::string(),
                ],
            ],
        ]);

        $this->directive = new Directive([
            'name'      => 'dir',
            'locations' => ['OBJECT'],
            'args'      => [
                'arg'     => [
                    'type' => $this->directiveInputType,
                ],
                'argList' => [
                    'type' => Type::listOf($this->wrappedDirectiveInputType),
                ],
            ],
        ]);

        $this->schema = new Schema([
            'query'      => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'getObject' => [
                        'type'    => $this->interfaceType,
                        'resolve' => static function () : array {
                            return [];
                        },
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
    public function testThrowsHumanReableErrorIfSchemaTypesIsNotDefined() : void
    {
        self::markTestSkipped("Can't check interface implementations without full schema scan");

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'Could not find possible implementing types for Interface in schema. ' .
            'Check that schema.types is defined and is an array of all possible ' .
            'types in the schema.'
        );
        $this->schema->isPossibleType($this->interfaceType, $this->implementingType);
    }

    // Type Map

    /**
     * @see it('includes input types only used in directives')
     */
    public function testIncludesInputTypesOnlyUsedInDirectives() : void
    {
        $typeMap = $this->schema->getTypeMap();
        self::assertArrayHasKey('DirInput', $typeMap);
        self::assertArrayHasKey('WrappedDirInput', $typeMap);
    }
}
