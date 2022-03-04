<?php declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Language\DirectiveLocation;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use GraphQL\Utils\BreakingChangesFinder;
use PHPUnit\Framework\TestCase;

class BreakingChangesFinderTest extends TestCase
{
    private ObjectType $queryType;

    public function setUp(): void
    {
        $this->queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                ],
            ],
        ]);
    }

    //DESCRIBE: findBreakingChanges

    /**
     * @see it('should detect if a type was removed or not')
     */
    public function testShouldDetectIfTypeWasRemovedOrNot(): void
    {
        $type1 = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => ['type' => Type::string()],
            ],
        ]);
        $type2 = new ObjectType([
            'name' => 'Type2',
            'fields' => [
                'field1' => ['type' => Type::string()],
            ],
        ]);
        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$type1, $type2],
        ]);
        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$type2],
        ]);

        $expected = [
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_TYPE_REMOVED,
                'description' => 'Type1 was removed.',
            ],
        ];

        self::assertEquals(
            $expected,
            BreakingChangesFinder::findRemovedTypes($oldSchema, $newSchema)
        );

        self::assertEquals([], BreakingChangesFinder::findRemovedTypes($oldSchema, $oldSchema));
    }

    /**
     * @see it('should detect if a type changed its type')
     */
    public function testShouldDetectIfATypeChangedItsType(): void
    {
        $objectType = new ObjectType([
            'name' => 'ObjectType',
            'fields' => [
                'field1' => ['type' => Type::string()],
            ],
        ]);

        $interfaceType = new InterfaceType([
            'name' => 'Type1',
            'fields' => [
                'field1' => ['type' => Type::string()],
            ],
        ]);

        $unionType = new UnionType([
            'name' => 'Type1',
            'types' => [$objectType],
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$interfaceType],
        ]);

        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$unionType],
        ]);

        $expected = [
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_TYPE_CHANGED_KIND,
                'description' => 'Type1 changed from an Interface type to a Union type.',
            ],
        ];

        self::assertEquals(
            $expected,
            BreakingChangesFinder::findTypesThatChangedKind($oldSchema, $newSchema)
        );
    }

    /**
     * We need to compare type of class A (old type) and type of class B (new type)
     * Class B extends A but are evaluated as same types (if all properties match).
     * The reason is that when constructing schema from remote schema,
     * we have no certain way to get information about our classes.
     * Thus object types from remote schema are constructed as Object Type
     * while their local counterparts are usually a subclass of Object Type.
     *
     * @see https://github.com/webonyx/graphql-php/pull/431
     */
    public function testShouldNotMarkTypesWithInheritedClassesAsChanged(): void
    {
        $objectTypeConstructedFromRemoteSchema = new ObjectType([
            'name' => 'ObjectType',
            'fields' => [
                'field1' => ['type' => Type::string()],
            ],
        ]);

        $localObjectType = new class(['name' => 'ObjectType', 'fields' => ['field1' => ['type' => Type::string()]]]) extends ObjectType {
        };

        $schemaA = new Schema([
            'query' => $this->queryType,
            'types' => [$objectTypeConstructedFromRemoteSchema],
        ]);

        $schemaB = new Schema([
            'query' => $this->queryType,
            'types' => [$localObjectType],
        ]);

        self::assertEmpty(BreakingChangesFinder::findTypesThatChangedKind($schemaA, $schemaB));
        self::assertEmpty(BreakingChangesFinder::findTypesThatChangedKind($schemaB, $schemaA));
    }

    /**
     * @see it('should detect if a field on a type was deleted or changed type')
     */
    public function testShouldDetectIfAFieldOnATypeWasDeletedOrChangedType(): void
    {
        $typeA = new ObjectType([
            'name' => 'TypeA',
            'fields' => [
                'field1' => ['type' => Type::string()],
            ],
        ]);
        // logically equivalent to TypeA; findBreakingFieldChanges shouldn't
        // treat this as different than TypeA
        $typeA2 = new ObjectType([
            'name' => 'TypeA',
            'fields' => [
                'field1' => ['type' => Type::string()],
            ],
        ]);
        $typeB = new ObjectType([
            'name' => 'TypeB',
            'fields' => [
                'field1' => ['type' => Type::string()],
            ],
        ]);
        $oldType1 = new InterfaceType([
            'name' => 'Type1',
            'fields' => [
                'field1' => ['type' => $typeA],
                'field2' => ['type' => Type::string()],
                'field3' => ['type' => Type::string()],
                'field4' => ['type' => $typeA],
                'field6' => ['type' => Type::string()],
                'field7' => ['type' => Type::listOf(Type::string())],
                'field8' => ['type' => Type::int()],
                'field9' => ['type' => Type::nonNull(Type::int())],
                'field10' => ['type' => Type::nonNull(Type::listOf(Type::int()))],
                'field11' => ['type' => Type::int()],
                'field12' => ['type' => Type::listOf(Type::int())],
                'field13' => ['type' => Type::listOf(Type::nonNull(Type::int()))],
                'field14' => ['type' => Type::listOf(Type::int())],
                'field15' => ['type' => Type::listOf(Type::listOf(Type::int()))],
                'field16' => ['type' => Type::nonNull(Type::int())],
                'field17' => ['type' => Type::listOf(Type::int())],
                'field18' => [
                    'type' => Type::listOf(Type::nonNull(
                        Type::listOf(Type::nonNull(Type::int()))
                    )),
                ],
            ],
        ]);
        $newType1 = new InterfaceType([
            'name' => 'Type1',
            'fields' => [
                'field1' => ['type' => $typeA2],
                'field3' => ['type' => Type::boolean()],
                'field4' => ['type' => $typeB],
                'field5' => ['type' => Type::string()],
                'field6' => ['type' => Type::listOf(Type::string())],
                'field7' => ['type' => Type::string()],
                'field8' => ['type' => Type::nonNull(Type::int())],
                'field9' => ['type' => Type::int()],
                'field10' => ['type' => Type::listOf(Type::int())],
                'field11' => ['type' => Type::nonNull(Type::listOf(Type::int()))],
                'field12' => ['type' => Type::listOf(Type::nonNull(Type::int()))],
                'field13' => ['type' => Type::listOf(Type::int())],
                'field14' => ['type' => Type::listOf(Type::listOf(Type::int()))],
                'field15' => ['type' => Type::listOf(Type::int())],
                'field16' => ['type' => Type::nonNull(Type::listOf(Type::int()))],
                'field17' => ['type' => Type::nonNull(Type::listOf(Type::int()))],
                'field18' => [
                    'type' => Type::listOf(
                        Type::listOf(Type::nonNull(Type::int()))
                    ),
                ],
            ],
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$oldType1],
        ]);

        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$newType1],
        ]);

        $expectedFieldChanges = [
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_REMOVED,
                'description' => 'Type1.field2 was removed.',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                'description' => 'Type1.field3 changed type from String to Boolean.',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                'description' => 'Type1.field4 changed type from TypeA to TypeB.',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                'description' => 'Type1.field6 changed type from String to [String].',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                'description' => 'Type1.field7 changed type from [String] to String.',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                'description' => 'Type1.field9 changed type from Int! to Int.',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                'description' => 'Type1.field10 changed type from [Int]! to [Int].',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                'description' => 'Type1.field11 changed type from Int to [Int]!.',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                'description' => 'Type1.field13 changed type from [Int!] to [Int].',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                'description' => 'Type1.field14 changed type from [Int] to [[Int]].',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                'description' => 'Type1.field15 changed type from [[Int]] to [Int].',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                'description' => 'Type1.field16 changed type from Int! to [Int]!.',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                'description' => 'Type1.field18 changed type from [[Int!]!] to [[Int!]].',
            ],
        ];

        self::assertEquals(
            $expectedFieldChanges,
            BreakingChangesFinder::findFieldsThatChangedTypeOnObjectOrInterfaceTypes($oldSchema, $newSchema)
        );
    }

    /**
     * @see it('should detect if fields on input types changed kind or were removed')
     */
    public function testShouldDetectIfFieldsOnInputTypesChangedKindOrWereRemoved(): void
    {
        $oldInputType = new InputObjectType([
            'name' => 'InputType1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                ],
                'field2' => [
                    'type' => Type::boolean(),
                ],
                'field3' => [
                    'type' => Type::listOf(Type::string()),
                ],
                'field4' => [
                    'type' => Type::nonNull(Type::string()),
                ],
                'field5' => [
                    'type' => Type::string(),
                ],
                'field6' => [
                    'type' => Type::listOf(Type::int()),
                ],
                'field7' => [
                    'type' => Type::nonNull(Type::listOf(Type::int())),
                ],
                'field8' => [
                    'type' => Type::int(),
                ],
                'field9' => [
                    'type' => Type::listOf(Type::int()),
                ],
                'field10' => [
                    'type' => Type::listOf(Type::nonNull(Type::int())),
                ],
                'field11' => [
                    'type' => Type::listOf(Type::int()),
                ],
                'field12' => [
                    'type' => Type::listOf(Type::listOf(Type::int())),
                ],
                'field13' => [
                    'type' => Type::nonNull(Type::int()),
                ],
                'field14' => [
                    'type' => Type::listOf(Type::nonNull(Type::listOf(Type::int()))),
                ],
                'field15' => [
                    'type' => Type::listOf(Type::nonNull(Type::listOf(Type::int()))),
                ],
            ],
        ]);

        $newInputType = new InputObjectType([
            'name' => 'InputType1',
            'fields' => [
                'field1' => [
                    'type' => Type::int(),
                ],
                'field3' => [
                    'type' => Type::string(),
                ],
                'field4' => [
                    'type' => Type::string(),
                ],
                'field5' => [
                    'type' => Type::nonNull(Type::string()),
                ],
                'field6' => [
                    'type' => Type::nonNull(Type::listOf(Type::int())),
                ],
                'field7' => [
                    'type' => Type::listOf(Type::int()),
                ],
                'field8' => [
                    'type' => Type::nonNull(Type::listOf(Type::int())),
                ],
                'field9' => [
                    'type' => Type::listOf(Type::nonNull(Type::int())),
                ],
                'field10' => [
                    'type' => Type::listOf(Type::int()),
                ],
                'field11' => [
                    'type' => Type::listOf(Type::listOf(Type::int())),
                ],
                'field12' => [
                    'type' => Type::listOf(Type::int()),
                ],
                'field13' => [
                    'type' => Type::nonNull(Type::listOf(Type::int())),
                ],
                'field14' => [
                    'type' => Type::listOf(Type::listOf(Type::int())),
                ],
                'field15' => [
                    'type' => Type::listOf(Type::nonNull(Type::listOf(Type::nonNull(Type::int())))),
                ],
            ],
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$oldInputType],
        ]);

        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$newInputType],
        ]);

        $expectedFieldChanges = [
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                'description' => 'InputType1.field1 changed type from String to Int.',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_REMOVED,
                'description' => 'InputType1.field2 was removed.',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                'description' => 'InputType1.field3 changed type from [String] to String.',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                'description' => 'InputType1.field5 changed type from String to String!.',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                'description' => 'InputType1.field6 changed type from [Int] to [Int]!.',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                'description' => 'InputType1.field8 changed type from Int to [Int]!.',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                'description' => 'InputType1.field9 changed type from [Int] to [Int!].',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                'description' => 'InputType1.field11 changed type from [Int] to [[Int]].',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                'description' => 'InputType1.field12 changed type from [[Int]] to [Int].',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                'description' => 'InputType1.field13 changed type from Int! to [Int]!.',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                'description' => 'InputType1.field15 changed type from [[Int]!] to [[Int!]!].',
            ],
        ];

        self::assertEquals(
            $expectedFieldChanges,
            BreakingChangesFinder::findFieldsThatChangedTypeOnInputObjectTypes(
                $oldSchema,
                $newSchema
            )['breakingChanges']
        );
    }

    /**
     * @see it('should detect if a required field is added to an input type')
     */
    public function testShouldDetectIfANonNullFieldIsAddedToAnInputType(): void
    {
        $oldInputType = new InputObjectType([
            'name' => 'InputType1',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);

        $newInputType = new InputObjectType([
            'name' => 'InputType1',
            'fields' => [
                'field1' => Type::string(),
                'requiredField' => Type::nonNull(Type::int()),
                'optionalField1' => Type::boolean(),
                'optionalField2' => [
                    'type' => Type::nonNull(Type::boolean()),
                    'defaultValue' => false,
                ],
            ],
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$oldInputType],
        ]);

        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$newInputType],
        ]);

        $expected = [
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_REQUIRED_INPUT_FIELD_ADDED,
                'description' => 'A required field requiredField on input type InputType1 was added.',
            ],
        ];

        self::assertEquals(
            $expected,
            BreakingChangesFinder::findFieldsThatChangedTypeOnInputObjectTypes(
                $oldSchema,
                $newSchema
            )['breakingChanges']
        );
    }

    /**
     * @see it('should detect if a type was removed from a union type')
     */
    public function testShouldRetectIfATypeWasRemovedFromAUnionType(): void
    {
        $type1 = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);
        // logially equivalent to type1; findTypesRemovedFromUnions should not
        // treat this as different than type1
        $type1a = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);
        $type2 = new ObjectType([
            'name' => 'Type2',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);
        $type3 = new ObjectType([
            'name' => 'Type3',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);

        $oldUnionType = new UnionType([
            'name' => 'UnionType1',
            'types' => [$type1, $type2],
        ]);
        $newUnionType = new UnionType([
            'name' => 'UnionType1',
            'types' => [$type1a, $type3],
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$oldUnionType],
        ]);
        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$newUnionType],
        ]);

        $expected = [
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_TYPE_REMOVED_FROM_UNION,
                'description' => 'Type2 was removed from union type UnionType1.',
            ],
        ];

        self::assertEquals(
            $expected,
            BreakingChangesFinder::findTypesRemovedFromUnions($oldSchema, $newSchema)
        );
    }

    /**
     * @see it('should detect if a value was removed from an enum type')
     */
    public function testShouldDetectIfAValueWasRemovedFromAnEnumType(): void
    {
        $oldEnumType = new EnumType([
            'name' => 'EnumType1',
            'values' => [
                'VALUE0' => 0,
                'VALUE1' => 1,
                'VALUE2' => 2,
            ],
        ]);
        $newEnumType = new EnumType([
            'name' => 'EnumType1',
            'values' => [
                'VALUE0' => 0,
                'VALUE2' => 1,
                'VALUE3' => 2,
            ],
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$oldEnumType],
        ]);

        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$newEnumType],
        ]);

        $expected = [
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_VALUE_REMOVED_FROM_ENUM,
                'description' => 'VALUE1 was removed from enum type EnumType1.',
            ],
        ];

        self::assertEquals(
            $expected,
            BreakingChangesFinder::findValuesRemovedFromEnums($oldSchema, $newSchema)
        );
    }

    /**
     * @see it('should detect if a field argument was removed')
     */
    public function testShouldDetectIfAFieldArgumentWasRemoved(): void
    {
        $oldType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'name' => Type::string(),
                    ],
                ],
            ],
        ]);

        $inputType = new InputObjectType([
            'name' => 'InputType1',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);

        $oldInterfaceType = new InterfaceType([
            'name' => 'Interface1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'arg1' => Type::boolean(),
                        'objectArg' => $inputType,
                    ],
                ],
            ],
        ]);

        $newType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [],
                ],
            ],
        ]);

        $newInterfaceType = new InterfaceType([
            'name' => 'Interface1',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$oldType, $oldInterfaceType],
        ]);

        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$newType, $newInterfaceType],
        ]);

        $expectedChanges = [
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_ARG_REMOVED,
                'description' => 'Type1.field1 arg name was removed',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_ARG_REMOVED,
                'description' => 'Interface1.field1 arg arg1 was removed',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_ARG_REMOVED,
                'description' => 'Interface1.field1 arg objectArg was removed',
            ],
        ];

        self::assertEquals(
            $expectedChanges,
            BreakingChangesFinder::findArgChanges($oldSchema, $newSchema)['breakingChanges']
        );
    }

    /**
     * @see it('should detect if a field argument has changed type')
     */
    public function testShouldDetectIfAFieldArgumentHasChangedType(): void
    {
        $oldType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'arg1' => Type::string(),
                        'arg2' => Type::string(),
                        'arg3' => Type::listOf(Type::string()),
                        'arg4' => Type::string(),
                        'arg5' => Type::nonNull(Type::string()),
                        'arg6' => Type::nonNull(Type::string()),
                        'arg7' => Type::nonNull(Type::listOf(Type::int())),
                        'arg8' => Type::int(),
                        'arg9' => Type::listOf(Type::int()),
                        'arg10' => Type::listOf(Type::nonNull(Type::int())),
                        'arg11' => Type::listOf(Type::int()),
                        'arg12' => Type::listOf(Type::listOf(Type::int())),
                        'arg13' => Type::nonNull(Type::int()),
                        'arg14' => Type::listOf(Type::nonNull(Type::listOf(Type::int()))),
                        'arg15' => Type::listOf(Type::nonNull(Type::listOf(Type::int()))),
                    ],
                ],
            ],
        ]);

        $newType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'arg1' => Type::int(),
                        'arg2' => Type::listOf(Type::string()),
                        'arg3' => Type::string(),
                        'arg4' => Type::nonNull(Type::string()),
                        'arg5' => Type::int(),
                        'arg6' => Type::nonNull(Type::int()),
                        'arg7' => Type::listOf(Type::int()),
                        'arg8' => Type::nonNull(Type::listOf(Type::int())),
                        'arg9' => Type::listOf(Type::nonNull(Type::int())),
                        'arg10' => Type::listOf(Type::int()),
                        'arg11' => Type::listOf(Type::listOf(Type::int())),
                        'arg12' => Type::listOf(Type::int()),
                        'arg13' => Type::nonNull(Type::listOf(Type::int())),
                        'arg14' => Type::listOf(Type::listOf(Type::int())),
                        'arg15' => Type::listOf(Type::nonNull(Type::listOf(Type::nonNull(Type::int())))),
                    ],
                ],
            ],
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$oldType],
        ]);

        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$newType],
        ]);

        $expectedChanges = [
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_ARG_CHANGED_KIND,
                'description' => 'Type1.field1 arg arg1 has changed type from String to Int',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_ARG_CHANGED_KIND,
                'description' => 'Type1.field1 arg arg2 has changed type from String to [String]',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_ARG_CHANGED_KIND,
                'description' => 'Type1.field1 arg arg3 has changed type from [String] to String',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_ARG_CHANGED_KIND,
                'description' => 'Type1.field1 arg arg4 has changed type from String to String!',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_ARG_CHANGED_KIND,
                'description' => 'Type1.field1 arg arg5 has changed type from String! to Int',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_ARG_CHANGED_KIND,
                'description' => 'Type1.field1 arg arg6 has changed type from String! to Int!',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_ARG_CHANGED_KIND,
                'description' => 'Type1.field1 arg arg8 has changed type from Int to [Int]!',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_ARG_CHANGED_KIND,
                'description' => 'Type1.field1 arg arg9 has changed type from [Int] to [Int!]',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_ARG_CHANGED_KIND,
                'description' => 'Type1.field1 arg arg11 has changed type from [Int] to [[Int]]',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_ARG_CHANGED_KIND,
                'description' => 'Type1.field1 arg arg12 has changed type from [[Int]] to [Int]',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_ARG_CHANGED_KIND,
                'description' => 'Type1.field1 arg arg13 has changed type from Int! to [Int]!',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_ARG_CHANGED_KIND,
                'description' => 'Type1.field1 arg arg15 has changed type from [[Int]!] to [[Int!]!]',
            ],
        ];

        self::assertEquals(
            $expectedChanges,
            BreakingChangesFinder::findArgChanges($oldSchema, $newSchema)['breakingChanges']
        );
    }

    /**
     * @see it('should detect if a non-null field argument was added')
     */
    public function testShouldDetectIfANonNullFieldArgumentWasAdded(): void
    {
        $oldType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'arg1' => Type::string(),
                    ],
                ],
            ],
        ]);
        $newType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'arg1' => Type::string(),
                        'newRequiredArg' => Type::nonNull(Type::string()),
                        'newOptionalArg' => Type::int(),
                    ],
                ],
            ],
        ]);
        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$oldType],
        ]);
        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$newType],
        ]);

        $expected = [
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_REQUIRED_ARG_ADDED,
                'description' => 'A required arg newRequiredArg on Type1.field1 was added',
            ],
        ];

        self::assertEquals(
            $expected,
            BreakingChangesFinder::findArgChanges($oldSchema, $newSchema)['breakingChanges']
        );
    }

    /**
     * @see it('should not flag args with the same type signature as breaking')
     */
    public function testShouldNotFlagArgsWithTheSameTypeSignatureAsBreaking(): void
    {
        $inputType1a = new InputObjectType([
            'name' => 'InputType1',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);

        $inputType1b = new InputObjectType([
            'name' => 'InputType1',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);

        $oldType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::int(),
                    'args' => [
                        'arg1' => Type::nonNull(Type::int()),
                        'arg2' => $inputType1a,
                    ],
                ],
            ],
        ]);

        $newType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::int(),
                    'args' => [
                        'arg1' => Type::nonNull(Type::int()),
                        'arg2' => $inputType1b,
                    ],
                ],
            ],
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$oldType],
        ]);
        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$newType],
        ]);

        self::assertEquals([], BreakingChangesFinder::findArgChanges($oldSchema, $newSchema)['breakingChanges']);
    }

    /**
     * @see it('should consider args that move away from NonNull as non-breaking')
     */
    public function testShouldConsiderArgsThatMoveAwayFromNonNullAsNonBreaking(): void
    {
        $oldType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'arg1' => Type::nonNull(Type::string()),
                    ],
                ],
            ],
        ]);
        $newType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'arg1' => Type::string(),
                    ],
                ],
            ],
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$oldType],
        ]);
        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$newType],
        ]);

        self::assertEquals([], BreakingChangesFinder::findArgChanges($oldSchema, $newSchema)['breakingChanges']);
    }

    /**
     * @see it('should detect interfaces removed from types')
     */
    public function testShouldDetectInterfacesRemovedFromTypes(): void
    {
        $interface1 = new InterfaceType([
            'name' => 'Interface1',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);
        $oldType = new ObjectType([
            'name' => 'Type1',
            'interfaces' => [$interface1],
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);
        $newType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$oldType],
        ]);
        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$newType],
        ]);

        $expected = [
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_IMPLEMENTED_INTERFACE_REMOVED,
                'description' => 'Type1 no longer implements interface Interface1.',
            ],
        ];

        self::assertEquals(
            $expected,
            BreakingChangesFinder::findInterfacesRemovedFromObjectTypes($oldSchema, $newSchema)
        );
    }

    /**
     * @see it('should detect interfaces removed from interfaces')
     */
    public function testShouldDetectInterfacesRemovedFromInterfaces(): void
    {
        $interface1 = new InterfaceType([
            'name' => 'Interface1',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);

        $oldInterface2 = new InterfaceType([
            'name' => 'Interface2',
            'fields' => [
                'field1' => Type::string(),
            ],
            'interfaces' => [$interface1],
        ]);
        $newInterface2 = new InterfaceType([
            'name' => 'Interface2',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$interface1, $oldInterface2],
        ]);
        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$interface1, $newInterface2],
        ]);

        $expected = [
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_IMPLEMENTED_INTERFACE_REMOVED,
                'description' => 'Interface2 no longer implements interface Interface1.',
            ],
        ];

        self::assertEquals(
            $expected,
            BreakingChangesFinder::findInterfacesRemovedFromObjectTypes($oldSchema, $newSchema)
        );
    }

    /**
     * @see it('should detect all breaking changes')
     */
    public function testShouldDetectAllBreakingChanges(): void
    {
        $typeThatGetsRemoved = new ObjectType([
            'name' => 'TypeThatGetsRemoved',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);

        $argThatChanges = new ObjectType([
            'name' => 'ArgThatChanges',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'id' => Type::int(),
                    ],
                ],
            ],
        ]);

        $argChanged = new ObjectType([
            'name' => 'ArgThatChanges',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'id' => Type::string(),
                    ],
                ],
            ],
        ]);

        $typeThatChangesTypeOld = new ObjectType([
            'name' => 'TypeThatChangesType',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);

        $typeThatChangesTypeNew = new InterfaceType([
            'name' => 'TypeThatChangesType',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);

        $typeThatHasBreakingFieldChangesOld = new InterfaceType([
            'name' => 'TypeThatHasBreakingFieldChanges',
            'fields' => [
                'field1' => Type::string(),
                'field2' => Type::string(),
            ],
        ]);

        $typeThatHasBreakingFieldChangesNew = new InterfaceType([
            'name' => 'TypeThatHasBreakingFieldChanges',
            'fields' => [
                'field2' => Type::boolean(),
            ],
        ]);

        $typeInUnion1 = new ObjectType([
            'name' => 'TypeInUnion1',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);

        $typeInUnion2 = new ObjectType([
            'name' => 'TypeInUnion2',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);

        $unionTypeThatLosesATypeOld = new UnionType([
            'name' => 'UnionTypeThatLosesAType',
            'types' => [$typeInUnion1, $typeInUnion2],
        ]);

        $unionTypeThatLosesATypeNew = new UnionType([
            'name' => 'UnionTypeThatLosesAType',
            'types' => [$typeInUnion1],
        ]);

        $enumTypeThatLosesAValueOld = new EnumType([
            'name' => 'EnumTypeThatLosesAValue',
            'values' => [
                'VALUE0' => 0,
                'VALUE1' => 1,
                'VALUE2' => 2,
            ],
        ]);

        $enumTypeThatLosesAValueNew = new EnumType([
            'name' => 'EnumTypeThatLosesAValue',
            'values' => [
                'VALUE1' => 1,
                'VALUE2' => 2,
            ],
        ]);

        $interface1 = new InterfaceType([
            'name' => 'Interface1',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);

        $typeThatLosesInterfaceOld = new ObjectType([
            'name' => 'TypeThatLosesInterface1',
            'interfaces' => [$interface1],
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);

        $typeThatLosesInterfaceNew = new ObjectType([
            'name' => 'TypeThatLosesInterface1',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);

        $directiveThatIsRemoved = Directive::skipDirective();
        $directiveThatRemovesArgOld = new Directive([
            'name' => 'DirectiveThatRemovesArg',
            'locations' => [DirectiveLocation::FIELD_DEFINITION],
            'args' => [
                'arg1' => [
                    'name' => 'arg1',
                    'type' => Type::boolean(),
                ],
            ],
        ]);
        $directiveThatRemovesArgNew = new Directive([
            'name' => 'DirectiveThatRemovesArg',
            'locations' => [DirectiveLocation::FIELD_DEFINITION],
        ]);
        $nonNullDirectiveAddedOld = new Directive([
            'name' => 'NonNullDirectiveAdded',
            'locations' => [DirectiveLocation::FIELD_DEFINITION],
        ]);
        $nonNullDirectiveAddedNew = new Directive([
            'name' => 'NonNullDirectiveAdded',
            'locations' => [DirectiveLocation::FIELD_DEFINITION],
            'args' => [
                'arg1' => [
                    'name' => 'arg1',
                    'type' => Type::nonNull(Type::boolean()),
                ],
            ],
        ]);
        $directiveRemovedLocationOld = new Directive([
            'name' => 'Directive Name',
            'locations' => [DirectiveLocation::FIELD_DEFINITION, DirectiveLocation::QUERY],
        ]);
        $directiveRemovedLocationNew = new Directive([
            'name' => 'Directive Name',
            'locations' => [DirectiveLocation::FIELD_DEFINITION],
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [
                $typeThatGetsRemoved,
                $typeThatChangesTypeOld,
                $typeThatHasBreakingFieldChangesOld,
                $unionTypeThatLosesATypeOld,
                $enumTypeThatLosesAValueOld,
                $argThatChanges,
                $typeThatLosesInterfaceOld,
            ],
            'directives' => [
                $directiveThatIsRemoved,
                $directiveThatRemovesArgOld,
                $nonNullDirectiveAddedOld,
                $directiveRemovedLocationOld,
            ],
        ]);

        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [
                $typeThatChangesTypeNew,
                $typeThatHasBreakingFieldChangesNew,
                $unionTypeThatLosesATypeNew,
                $enumTypeThatLosesAValueNew,
                $argChanged,
                $typeThatLosesInterfaceNew,
                $interface1,
            ],
            'directives' => [
                $directiveThatRemovesArgNew,
                $nonNullDirectiveAddedNew,
                $directiveRemovedLocationNew,
            ],
        ]);

        $expectedBreakingChanges = [
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_TYPE_REMOVED,
                'description' => 'TypeThatGetsRemoved was removed.',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_TYPE_REMOVED,
                'description' => 'TypeInUnion2 was removed.',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_TYPE_REMOVED,
                'description' => 'Int was removed.',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_TYPE_CHANGED_KIND,
                'description' => 'TypeThatChangesType changed from an Object type to an Interface type.',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_REMOVED,
                'description' => 'TypeThatHasBreakingFieldChanges.field1 was removed.',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_FIELD_CHANGED_KIND,
                'description' => 'TypeThatHasBreakingFieldChanges.field2 changed type from String to Boolean.',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_TYPE_REMOVED_FROM_UNION,
                'description' => 'TypeInUnion2 was removed from union type UnionTypeThatLosesAType.',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_VALUE_REMOVED_FROM_ENUM,
                'description' => 'VALUE0 was removed from enum type EnumTypeThatLosesAValue.',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_ARG_CHANGED_KIND,
                'description' => 'ArgThatChanges.field1 arg id has changed type from Int to String',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_IMPLEMENTED_INTERFACE_REMOVED,
                'description' => 'TypeThatLosesInterface1 no longer implements interface Interface1.',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_DIRECTIVE_REMOVED,
                'description' => 'skip was removed',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_DIRECTIVE_ARG_REMOVED,
                'description' => 'arg1 was removed from DirectiveThatRemovesArg',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_REQUIRED_DIRECTIVE_ARG_ADDED,
                'description' => 'A required arg arg1 on directive NonNullDirectiveAdded was added',
            ],
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_DIRECTIVE_LOCATION_REMOVED,
                'description' => 'QUERY was removed from Directive Name',
            ],
        ];

        self::assertEquals(
            $expectedBreakingChanges,
            BreakingChangesFinder::findBreakingChanges($oldSchema, $newSchema)
        );
    }

    /**
     * @see it('should detect if a directive was explicitly removed')
     */
    public function testShouldDetectIfADirectiveWasExplicitlyRemoved(): void
    {
        $oldSchema = new Schema([
            'directives' => [Directive::skipDirective(), Directive::includeDirective()],
        ]);

        $newSchema = new Schema([
            'directives' => [Directive::skipDirective()],
        ]);

        $includeDirective = Directive::includeDirective();

        $expectedBreakingChanges = [
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_DIRECTIVE_REMOVED,
                'description' => "{$includeDirective->name} was removed",
            ],
        ];

        self::assertEquals(
            $expectedBreakingChanges,
            BreakingChangesFinder::findRemovedDirectives($oldSchema, $newSchema)
        );
    }

    /**
     * @see it('should detect if a directive was implicitly removed')
     */
    public function testShouldDetectIfADirectiveWasImplicitlyRemoved(): void
    {
        $oldSchema = new Schema([]);

        $newSchema = new Schema([
            'directives' => [Directive::skipDirective(), Directive::includeDirective()],
        ]);

        $deprecatedDirective = Directive::deprecatedDirective();

        $expectedBreakingChanges = [
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_DIRECTIVE_REMOVED,
                'description' => "{$deprecatedDirective->name} was removed",
            ],
        ];

        self::assertEquals(
            $expectedBreakingChanges,
            BreakingChangesFinder::findRemovedDirectives($oldSchema, $newSchema)
        );
    }

    /**
     * @see it('should detect if a directive argument was removed')
     */
    public function testShouldDetectIfADirectiveArgumentWasRemoved(): void
    {
        $oldSchema = new Schema([
            'directives' => [
                new Directive([
                    'name' => 'DirectiveWithArg',
                    'locations' => [DirectiveLocation::FIELD_DEFINITION],
                    'args' => [
                        'arg1' => [
                            'name' => 'arg1',
                            'type' => Type::string(),
                        ],
                    ],
                ]),
            ],
        ]);

        $newSchema = new Schema([
            'directives' => [
                new Directive([
                    'name' => 'DirectiveWithArg',
                    'locations' => [DirectiveLocation::FIELD_DEFINITION],
                ]),
            ],
        ]);

        $expectedBreakingChanges = [
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_DIRECTIVE_ARG_REMOVED,
                'description' => 'arg1 was removed from DirectiveWithArg',
            ],
        ];

        self::assertEquals(
            $expectedBreakingChanges,
            BreakingChangesFinder::findRemovedDirectiveArgs($oldSchema, $newSchema)
        );
    }

    /**
     * @see it('should detect if a non-nullable directive argument was added')
     */
    public function testShouldDetectIfANonNullableDirectiveArgumentWasAdded(): void
    {
        $oldSchema = new Schema([
            'directives' => [
                new Directive([
                    'name' => 'DirectiveName',
                    'locations' => [DirectiveLocation::FIELD_DEFINITION],
                ]),
            ],
        ]);

        $newSchema = new Schema([
            'directives' => [
                new Directive([
                    'name' => 'DirectiveName',
                    'locations' => [DirectiveLocation::FIELD_DEFINITION],
                    'args' => [
                        'arg1' => [
                            'name' => 'arg1',
                            'type' => Type::nonNull(Type::boolean()),
                        ],
                    ],
                ]),
            ],
        ]);

        $expectedBreakingChanges = [
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_REQUIRED_DIRECTIVE_ARG_ADDED,
                'description' => 'A required arg arg1 on directive DirectiveName was added',
            ],
        ];

        self::assertEquals(
            $expectedBreakingChanges,
            BreakingChangesFinder::findAddedNonNullDirectiveArgs($oldSchema, $newSchema)
        );
    }

    /**
     * @see it('should detect locations removed from a directive')
     */
    public function testShouldDetectLocationsRemovedFromADirective(): void
    {
        $d1 = new Directive([
            'name' => 'Directive Name',
            'locations' => [DirectiveLocation::FIELD_DEFINITION, DirectiveLocation::QUERY],
        ]);

        $d2 = new Directive([
            'name' => 'Directive Name',
            'locations' => [DirectiveLocation::FIELD_DEFINITION],
        ]);

        self::assertEquals(
            [DirectiveLocation::QUERY],
            BreakingChangesFinder::findRemovedLocationsForDirective($d1, $d2)
        );
    }

    /**
     * @see it('should detect locations removed directives within a schema')
     */
    public function testShouldDetectLocationsRemovedDirectiveWithinASchema(): void
    {
        $oldSchema = new Schema([
            'directives' => [
                new Directive([
                    'name' => 'Directive Name',
                    'locations' => [
                        DirectiveLocation::FIELD_DEFINITION,
                        DirectiveLocation::QUERY,
                    ],
                ]),
            ],
        ]);

        $newSchema = new Schema([
            'directives' => [
                new Directive([
                    'name' => 'Directive Name',
                    'locations' => [DirectiveLocation::FIELD_DEFINITION],
                ]),
            ],
        ]);

        $expectedBreakingChanges = [
            [
                'type' => BreakingChangesFinder::BREAKING_CHANGE_DIRECTIVE_LOCATION_REMOVED,
                'description' => 'QUERY was removed from Directive Name',
            ],
        ];

        self::assertEquals(
            $expectedBreakingChanges,
            BreakingChangesFinder::findRemovedDirectiveLocations($oldSchema, $newSchema)
        );
    }

    // DESCRIBE: findDangerousChanges
    // DESCRIBE: findArgChanges

    /**
     * @see it('should detect if an argument's defaultValue has changed')
     */
    public function testShouldDetectIfAnArgumentsDefaultValueHasChanged(): void
    {
        $oldType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'name' => [
                            'type' => Type::string(),
                            'defaultValue' => 'test',
                        ],
                    ],
                ],
            ],
        ]);

        $newType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'name' => [
                            'type' => Type::string(),
                            'defaultValue' => 'Test',
                        ],
                    ],
                ],
            ],
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$oldType],
        ]);

        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$newType],
        ]);

        $expected = [
            [
                'type' => BreakingChangesFinder::DANGEROUS_CHANGE_ARG_DEFAULT_VALUE_CHANGED,
                'description' => 'Type1.field1 arg name has changed defaultValue',
            ],
        ];

        self::assertEquals(
            $expected,
            BreakingChangesFinder::findArgChanges($oldSchema, $newSchema)['dangerousChanges']
        );
    }

    /**
     * @see it('should detect if a value was added to an enum type')
     */
    public function testShouldDetectIfAValueWasAddedToAnEnumType(): void
    {
        $oldEnumType = new EnumType([
            'name' => 'EnumType1',
            'values' => [
                'VALUE0' => 0,
                'VALUE1' => 1,
            ],
        ]);
        $newEnumType = new EnumType([
            'name' => 'EnumType1',
            'values' => [
                'VALUE0' => 0,
                'VALUE1' => 1,
                'VALUE2' => 2,
            ],
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$oldEnumType],
        ]);

        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$newEnumType],
        ]);

        $expected = [
            [
                'type' => BreakingChangesFinder::DANGEROUS_CHANGE_VALUE_ADDED_TO_ENUM,
                'description' => 'VALUE2 was added to enum type EnumType1.',
            ],
        ];

        self::assertEquals(
            $expected,
            BreakingChangesFinder::findValuesAddedToEnums($oldSchema, $newSchema)
        );
    }

    /**
     * @see it('should detect interfaces added to types')
     */
    public function testShouldDetectInterfacesAddedToTypes(): void
    {
        $interface1 = new InterfaceType([
            'name' => 'Interface1',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);
        $oldType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);

        $newType = new ObjectType([
            'name' => 'Type1',
            'interfaces' => [$interface1],
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$oldType],
        ]);

        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$newType],
        ]);

        $expected = [
            [
                'type' => BreakingChangesFinder::DANGEROUS_CHANGE_IMPLEMENTED_INTERFACE_ADDED,
                'description' => 'Interface1 added to interfaces implemented by Type1.',
            ],
        ];

        self::assertEquals(
            $expected,
            BreakingChangesFinder::findInterfacesAddedToObjectTypes($oldSchema, $newSchema)
        );
    }

    /**
     * @see it('should detect interfaces added to interfaces')
     */
    public function testShouldDetectInterfacesAddedToInterfaces(): void
    {
        $oldInterface = new InterfaceType([
            'name' => 'OldInterface',
            'fields' => ['irrelevant' => Type::int()],
        ]);
        $newInterface = new InterfaceType([
            'name' => 'NewInterface',
            'fields' => ['notImportant' => Type::int()],
        ]);

        $oldInterface1 = new InterfaceType([
            'name' => 'Interface1',
            'fields' => ['irrelevant' => Type::int()],
            'interfaces' => [$oldInterface],
        ]);
        $newInterface1 = new InterfaceType([
            'name' => 'Interface1',
            'fields' => [
                'irrelevant' => Type::int(),
                'notImportant' => Type::int(),
            ],
            'interfaces' => [$oldInterface, $newInterface],
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$oldInterface1],
        ]);

        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$newInterface1],
        ]);

        $expected = [
            [
                'type' => BreakingChangesFinder::DANGEROUS_CHANGE_IMPLEMENTED_INTERFACE_ADDED,
                'description' => 'NewInterface added to interfaces implemented by Interface1.',
            ],
        ];

        self::assertEquals(
            $expected,
            BreakingChangesFinder::findInterfacesAddedToObjectTypes($oldSchema, $newSchema)
        );
    }

    /**
     * @see it('should detect if a type was added to a union type')
     */
    public function testShouldDetectIfATypeWasAddedToAUnionType(): void
    {
        $type1 = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);
        // logially equivalent to type1; findTypesRemovedFromUnions should not
        //treat this as different than type1
        $type1a = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);
        $type2 = new ObjectType([
            'name' => 'Type2',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);

        $oldUnionType = new UnionType([
            'name' => 'UnionType1',
            'types' => [$type1],
        ]);
        $newUnionType = new UnionType([
            'name' => 'UnionType1',
            'types' => [$type1a, $type2],
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$oldUnionType],
        ]);

        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$newUnionType],
        ]);

        $expected = [
            [
                'type' => BreakingChangesFinder::DANGEROUS_CHANGE_TYPE_ADDED_TO_UNION,
                'description' => 'Type2 was added to union type UnionType1.',
            ],
        ];

        self::assertEquals(
            $expected,
            BreakingChangesFinder::findTypesAddedToUnions($oldSchema, $newSchema)
        );
    }

    /**
     * @see it('should detect if a nullable field was added to an input')
     */
    public function testShouldDetectIfANullableFieldWasAddedToAnInput(): void
    {
        $oldInputType = new InputObjectType([
            'name' => 'InputType1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                ],
            ],
        ]);
        $newInputType = new InputObjectType([
            'name' => 'InputType1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                ],
                'field2' => [
                    'type' => Type::int(),
                ],
            ],
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$oldInputType],
        ]);

        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$newInputType],
        ]);

        $expectedFieldChanges = [
            [
                'description' => 'An optional field field2 on input type InputType1 was added.',
                'type' => BreakingChangesFinder::DANGEROUS_CHANGE_OPTIONAL_INPUT_FIELD_ADDED,
            ],
        ];

        self::assertEquals(
            $expectedFieldChanges,
            BreakingChangesFinder::findFieldsThatChangedTypeOnInputObjectTypes(
                $oldSchema,
                $newSchema
            )['dangerousChanges']
        );
    }

    /**
     * @see it('should find all dangerous changes')
     */
    public function testShouldFindAllDangerousChanges(): void
    {
        $enumThatGainsAValueOld = new EnumType([
            'name' => 'EnumType1',
            'values' => [
                'VALUE0' => 0,
                'VALUE1' => 1,
            ],
        ]);
        $enumThatGainsAValueNew = new EnumType([
            'name' => 'EnumType1',
            'values' => [
                'VALUE0' => 0,
                'VALUE1' => 1,
                'VALUE2' => 2,
            ],
        ]);

        $oldType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'name' => [
                            'type' => Type::string(),
                            'defaultValue' => 'test',
                        ],
                    ],
                ],
            ],
        ]);

        $typeInUnion1 = new ObjectType([
            'name' => 'TypeInUnion1',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);
        $typeInUnion2 = new ObjectType([
            'name' => 'TypeInUnion2',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);
        $unionTypeThatGainsATypeOld = new UnionType([
            'name' => 'UnionTypeThatGainsAType',
            'types' => [$typeInUnion1],
        ]);
        $unionTypeThatGainsATypeNew = new UnionType([
            'name' => 'UnionTypeThatGainsAType',
            'types' => [$typeInUnion1, $typeInUnion2],
        ]);

        $newType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'name' => [
                            'type' => Type::string(),
                            'defaultValue' => 'Test',
                        ],
                    ],
                ],
            ],
        ]);

        $interface1 = new InterfaceType([
            'name' => 'Interface1',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);

        $typeThatGainsInterfaceOld = new ObjectType([
            'name' => 'TypeThatGainsInterface1',
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);

        $typeThatGainsInterfaceNew = new ObjectType([
            'name' => 'TypeThatGainsInterface1',
            'interfaces' => [$interface1],
            'fields' => [
                'field1' => Type::string(),
            ],
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [
                $oldType,
                $enumThatGainsAValueOld,
                $typeThatGainsInterfaceOld,
                $unionTypeThatGainsATypeOld,
            ],
        ]);

        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [
                $newType,
                $enumThatGainsAValueNew,
                $typeThatGainsInterfaceNew,
                $unionTypeThatGainsATypeNew,
            ],
        ]);

        $expectedDangerousChanges = [
            [
                'description' => 'Type1.field1 arg name has changed defaultValue',
                'type' => BreakingChangesFinder::DANGEROUS_CHANGE_ARG_DEFAULT_VALUE_CHANGED,
            ],
            [
                'description' => 'VALUE2 was added to enum type EnumType1.',
                'type' => BreakingChangesFinder::DANGEROUS_CHANGE_VALUE_ADDED_TO_ENUM,
            ],
            [
                'type' => BreakingChangesFinder::DANGEROUS_CHANGE_IMPLEMENTED_INTERFACE_ADDED,
                'description' => 'Interface1 added to interfaces implemented by TypeThatGainsInterface1.',
            ],
            [
                'type' => BreakingChangesFinder::DANGEROUS_CHANGE_TYPE_ADDED_TO_UNION,
                'description' => 'TypeInUnion2 was added to union type UnionTypeThatGainsAType.',
            ],
        ];

        self::assertEquals(
            $expectedDangerousChanges,
            BreakingChangesFinder::findDangerousChanges($oldSchema, $newSchema)
        );
    }

    /**
     * @see it('should detect if a nullable field argument was added')
     */
    public function testShouldDetectIfANullableFieldArgumentWasAdded(): void
    {
        $oldType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'arg1' => [
                            'type' => Type::string(),
                        ],
                    ],
                ],
            ],
        ]);

        $newType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'arg1' => [
                            'type' => Type::string(),
                        ],
                        'arg2' => [
                            'type' => Type::string(),
                        ],
                    ],
                ],
            ],
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$oldType],
        ]);

        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [$newType],
        ]);

        $expectedFieldChanges = [
            [
                'description' => 'An optional arg arg2 on Type1.field1 was added',
                'type' => BreakingChangesFinder::DANGEROUS_CHANGE_OPTIONAL_ARG_ADDED,
            ],
        ];

        self::assertEquals(
            $expectedFieldChanges,
            BreakingChangesFinder::findArgChanges($oldSchema, $newSchema)['dangerousChanges']
        );
    }
}
