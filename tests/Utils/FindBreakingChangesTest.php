<?php
/**
 * FindBreakingChanges tests
 */

namespace GraphQL\Tests\Utils;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use GraphQL\Utils\FindBreakingChanges;

class FindBreakingChangesTest extends \PHPUnit_Framework_TestCase
{

    private $queryType;

    public function setUp()
    {
        $this->queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'field1' => [
                    'type' => Type::string()
                ]
            ]
        ]);
    }

    public function testShouldDetectIfTypeWasRemoved()
    {
        $type1 = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => ['type' => Type::string()],
            ]
        ]);
        $type2 = new ObjectType([
            'name' => 'Type2',
            'fields' => [
                'field1' => ['type' => Type::string()],
            ]
        ]);
        $oldSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $type1,
                    'type2' => $type2
                ]
            ])
        ]);
        $newSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type2' => $type2
                ]
            ])
        ]);

        $this->assertEquals(['type' => FindBreakingChanges::BREAKING_CHANGE_TYPE_REMOVED, 'description' => 'Type1 was removed.'],
            FindBreakingChanges::findRemovedTypes($oldSchema, $newSchema)[0]
        );

        $this->assertEquals([], FindBreakingChanges::findRemovedTypes($oldSchema, $oldSchema));
    }

    public function testShouldDetectTypeChanges()
    {
        $objectType = new ObjectType([
            'name' => 'ObjectType',
            'fields' => [
                'field1' => ['type' => Type::string()],
            ]
        ]);

        $interfaceType = new InterfaceType([
            'name' => 'Type1',
            'fields' => [
                'field1' => ['type' => Type::string()]
            ]
        ]);

        $unionType = new UnionType([
            'name' => 'Type1',
            'types' => [new ObjectType(['name' => 'blah'])],
        ]);

        $oldSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $interfaceType
                ]
            ])
        ]);

        $newSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $unionType
                ]
            ])
        ]);

        $this->assertEquals(
            ['type' => FindBreakingChanges::BREAKING_CHANGE_TYPE_CHANGED, 'description' => 'Type1 changed from an Interface type to a Union type.'],
            FindBreakingChanges::findTypesThatChangedKind($oldSchema, $newSchema)[0]
        );
    }

    public function testShouldDetectFieldChangesAndDeletions()
    {
        $typeA1 = new ObjectType([
            'name' => 'TypeA',
            'fields' => [
                'field1' => ['type' => Type::string()],
            ]
        ]);
        $typeA2 = new ObjectType([
            'name' => 'TypeA',
            'fields' => [
                'field1' => ['type' => Type::string()],
            ]
        ]);
        $typeB = new ObjectType([
            'name' => 'TypeB',
            'fields' => [
                'field1' => ['type' => Type::string()],
            ]
        ]);
        $oldType1 = new InterfaceType([
            'name' => 'Type1',
            'fields' => [
                'field1' => ['type' => $typeA1],
                'field2' => ['type' => Type::string()],
                'field3' => ['type' => Type::string()],
                'field4' => ['type' => $typeA1],
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
                        Type::listOf(Type::nonNull(Type::int())))),
                ],
            ]
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
                        Type::listOf(Type::nonNull(Type::int()))),
                ],
            ]
        ]);

        $expectedFieldChanges = [
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_REMOVED,
                'description' => 'Type1->field2 was removed.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_CHANGED,
                'description' => 'Type1->field3 changed type from String to Boolean.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_CHANGED,
                'description' => 'Type1->field4 changed type from TypeA to TypeB.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_CHANGED,
                'description' => 'Type1->field6 changed type from String to [String].',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_CHANGED,
                'description' => 'Type1->field7 changed type from [String] to String.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_CHANGED,
                'description' => 'Type1->field9 changed type from Int! to Int.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_CHANGED,
                'description' => 'Type1->field10 changed type from [Int]! to [Int].',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_CHANGED,
                'description' => 'Type1->field11 changed type from Int to [Int]!.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_CHANGED,
                'description' => 'Type1->field13 changed type from [Int!] to [Int].',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_CHANGED,
                'description' => 'Type1->field14 changed type from [Int] to [[Int]].',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_CHANGED,
                'description' => 'Type1->field15 changed type from [[Int]] to [Int].',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_CHANGED,
                'description' => 'Type1->field16 changed type from Int! to [Int]!.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_CHANGED,
                'description' => 'Type1->field18 changed type from [[Int!]!] to [[Int!]].',
            ],
        ];

        $oldSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'Type1' => $oldType1
                ]
            ])
        ]);

        $newSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'Type1' => $newType1
                ]
            ])
        ]);

        $this->assertEquals($expectedFieldChanges, FindBreakingChanges::findFieldsThatChangedType($oldSchema, $newSchema));
    }


    public function testShouldDetectInputFieldChanges()
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
                    'type' => Type::listOf(Type::string())
                ],
                'field4' => [
                    'type' => Type::nonNull(Type::string()),
                ],
                'field5' => [
                    'type' => Type::string(),
                ],
                'field6' => [
                    'type' => Type::listOf(Type::int())
                ],
                'field7' => [
                    'type' => Type::nonNull(Type::listOf(Type::int()))
                ],
                'field8' => [
                    'type' => Type::int(),
                ],
                'field9' => [
                    'type' => Type::listOf(Type::int())
                ],
                'field10' => [
                    'type' => Type::listOf(Type::nonNull(Type::int()))
                ],
                'field11' => [
                    'type' => Type::listOf(Type::int())
                ],
                'field12' => [
                    'type' => Type::listOf(Type::listOf(Type::int()))
                ],
                'field13' => [
                    'type' => Type::nonNull(Type::int())
                ],
                'field14' => [
                    'type' => Type::listOf(Type::nonNull(Type::listOf(Type::int())))
                ],
                'field15' => [
                    'type' => Type::listOf(Type::nonNull(Type::listOf(Type::int())))
                ]
            ]
        ]);

        $newInputType = new InputObjectType([
            'name' => 'InputType1',
            'fields' => [
                'field1' => [
                    'type' => Type::int(),
                ],
                'field3' => [
                    'type' => Type::string()
                ],
                'field4' => [
                    'type' => Type::string()
                ],
                'field5' => [
                    'type' => Type::nonNull(Type::string())
                ],
                'field6' => [
                    'type' => Type::nonNull(Type::listOf(Type::int()))
                ],
                'field7' => [
                    'type' => Type::listOf(Type::int())
                ],
                'field8' => [
                    'type' => Type::nonNull(Type::listOf(Type::int()))
                ],
                'field9' => [
                    'type' => Type::listOf(Type::nonNull(Type::int()))
                ],
                'field10' => [
                    'type' => Type::listOf(Type::int())
                ],
                'field11' => [
                    'type' => Type::listOf(Type::listOf(Type::int()))
                ],
                'field12' => [
                    'type' => Type::listOf(Type::int())
                ],
                'field13' => [
                    'type' => Type::nonNull(Type::listOf(Type::int()))
                ],
                'field14' => [
                    'type' => Type::listOf(Type::listOf(Type::int()))
                ],
                'field15' => [
                    'type' => Type::listOf(Type::nonNull(Type::listOf(Type::nonNull(Type::int()))))
                ]
            ]
        ]);

        $oldSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $oldInputType
                ]
            ])
        ]);

        $newSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $newInputType
                ]
            ])
        ]);

        $expectedFieldChanges = [
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_CHANGED,
                'description' => 'InputType1->field1 changed type from String to Int.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_REMOVED,
                'description' => 'InputType1->field2 was removed.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_CHANGED,
                'description' => 'InputType1->field3 changed type from [String] to String.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_CHANGED,
                'description' => 'InputType1->field5 changed type from String to String!.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_CHANGED,
                'description' => 'InputType1->field6 changed type from [Int] to [Int]!.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_CHANGED,
                'description' => 'InputType1->field8 changed type from Int to [Int]!.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_CHANGED,
                'description' => 'InputType1->field9 changed type from [Int] to [Int!].',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_CHANGED,
                'description' => 'InputType1->field11 changed type from [Int] to [[Int]].',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_CHANGED,
                'description' => 'InputType1->field12 changed type from [[Int]] to [Int].',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_CHANGED,
                'description' => 'InputType1->field13 changed type from Int! to [Int]!.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_CHANGED,
                'description' => 'InputType1->field15 changed type from [[Int]!] to [[Int!]!].',
            ],
        ];

        $this->assertEquals($expectedFieldChanges, FindBreakingChanges::findFieldsThatChangedType($oldSchema, $newSchema));
    }

    public function testDetectsNonNullFieldAddedToInputType()
    {
        $oldInputType = new InputObjectType([
            'name' => 'InputType1',
            'fields' => [
                'field1' => Type::string()
            ]
        ]);

        $newInputType = new InputObjectType([
            'name' => 'InputType1',
            'fields' => [
                'field1' => Type::string(),
                'requiredField' => Type::nonNull(Type::int()),
                'optionalField' => Type::boolean()
            ]
        ]);

        $oldSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $oldInputType
                ]
            ])
        ]);

        $newSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $newInputType
                ]
            ])
        ]);

        $this->assertEquals(
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_NON_NULL_INPUT_FIELD_ADDED,
                'description' => 'A non-null field requiredField on input type InputType1 was added.'
            ],
            FindBreakingChanges::findFieldsThatChangedType($oldSchema, $newSchema)[0]
        );
    }

    public function testDetectsIfTypeWasRemovedFromUnion()
    {
        $type1 = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => Type::string()
            ]
        ]);

        $type1a = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => Type::string()
            ]
        ]);

        $type2 = new ObjectType([
            'name' => 'Type2',
            'fields' => [
                'field1' => Type::string()
            ]
        ]);

        $type3 = new ObjectType([
            'name' => 'Type3',
            'fields' => [
                'field1' => Type::string()
            ]
        ]);

        $oldUnionType = new UnionType([
            'name' => 'UnionType1',
            'types' => [$type1, $type2],
            'resolveType' => function () {
            }
        ]);


        $newUnionType = new UnionType([
            'name' => 'UnionType1',
            'types' => [$type1a, $type3],
            'resolveType' => function () {
            }
        ]);

        $oldSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $oldUnionType
                ]
            ])
        ]);

        $newSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $newUnionType
                ]
            ])
        ]);

        $this->assertEquals(
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_TYPE_REMOVED_FROM_UNION,
                'description' => 'Type2 was removed from union type UnionType1.'
            ],
            FindBreakingChanges::findTypesRemovedFromUnions($oldSchema, $newSchema)[0]
        );
    }

    public function testDetectsValuesRemovedFromEnum()
    {
        $oldEnumType = new EnumType([
            'name' => 'EnumType1',
            'values' => [
                'VALUE0' => 0,
                'VALUE1' => 1,
                'VALUE2' => 2
            ]
        ]);
        $newEnumType = new EnumType([
            'name' => 'EnumType1',
            'values' => [
                'VALUE0' => 0,
                'VALUE2' => 1,
                'VALUE3' => 2
            ]
        ]);

        $oldSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $oldEnumType
                ]
            ])
        ]);

        $newSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $newEnumType
                ]
            ])
        ]);

        $this->assertEquals(
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_VALUE_REMOVED_FROM_ENUM,
                'description' => 'VALUE1 was removed from enum type EnumType1.'
            ],
            FindBreakingChanges::findValuesRemovedFromEnums($oldSchema, $newSchema)[0]
        );
    }

    public function testDetectsRemovalOfFieldArgument()
    {

        $oldType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'name' => Type::string()
                    ]
                ]
            ]
        ]);


        $inputType = new InputObjectType([
            'name' => 'InputType1',
            'fields' => [
                'field1' => Type::string()
            ]
        ]);

        $oldInterfaceType = new InterfaceType([
            'name' => 'Interface1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'arg1' => Type::boolean(),
                        'objectArg' => $inputType
                    ]
                ]
            ]
        ]);

        $newType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => []
                ]
            ]
        ]);

        $newInterfaceType = new InterfaceType([
            'name' => 'Interface1',
            'fields' => [
                'field1' => Type::string()
            ]
        ]);

        $oldSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $oldType,
                    'type2' => $oldInterfaceType
                ],
                'types' => [$oldType, $oldInterfaceType]
            ])
        ]);

        $newSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $newType,
                    'type2' => $newInterfaceType
                ],
                'types' => [$newType, $newInterfaceType]
            ])
        ]);

        $expectedChanges = [
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_ARG_REMOVED,
                'description' => 'Type1->field1 arg name was removed',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_ARG_REMOVED,
                'description' => 'Interface1->field1 arg arg1 was removed',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_ARG_REMOVED,
                'description' => 'Interface1->field1 arg objectArg was removed',
            ]
        ];

        $this->assertEquals($expectedChanges, FindBreakingChanges::findArgChanges($oldSchema, $newSchema)['breakingChanges']);
    }

    public function testDetectsFieldArgumentTypeChange()
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
                        'arg15' => Type::listOf(Type::nonNull(Type::listOf(Type::int())))
                    ]
                ]
            ]
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
                        'arg15' => Type::listOf(Type::nonNull(Type::listOf(Type::nonNull(Type::int()))))
                    ]
                ]
            ]
        ]);

        $oldSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $oldType
                ]
            ])
        ]);

        $newSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $newType
                ]
            ])
        ]);

        $expectedChanges = [
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_ARG_CHANGED,
                'description' => 'Type1->field1 arg arg1 has changed type from String to Int.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_ARG_CHANGED,
                'description' => 'Type1->field1 arg arg2 has changed type from String to [String].'
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_ARG_CHANGED,
                'description' => 'Type1->field1 arg arg3 has changed type from [String] to String.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_ARG_CHANGED,
                'description' => 'Type1->field1 arg arg4 has changed type from String to String!.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_ARG_CHANGED,
                'description' => 'Type1->field1 arg arg5 has changed type from String! to Int.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_ARG_CHANGED,
                'description' => 'Type1->field1 arg arg6 has changed type from String! to Int!.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_ARG_CHANGED,
                'description' => 'Type1->field1 arg arg8 has changed type from Int to [Int]!.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_ARG_CHANGED,
                'description' => 'Type1->field1 arg arg9 has changed type from [Int] to [Int!].',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_ARG_CHANGED,
                'description' => 'Type1->field1 arg arg11 has changed type from [Int] to [[Int]].',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_ARG_CHANGED,
                'description' => 'Type1->field1 arg arg12 has changed type from [[Int]] to [Int].',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_ARG_CHANGED,
                'description' => 'Type1->field1 arg arg13 has changed type from Int! to [Int]!.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_ARG_CHANGED,
                'description' => 'Type1->field1 arg arg15 has changed type from [[Int]!] to [[Int!]!].',
            ],
        ];

        $this->assertEquals($expectedChanges, FindBreakingChanges::findArgChanges($oldSchema, $newSchema)['breakingChanges']);
    }

    public function testDetectsAdditionOfFieldArg()
    {
        $oldType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'arg1' => Type::string()
                    ]]
            ]
        ]);
        $newType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'arg1' => Type::string(),
                        'newRequiredArg' => Type::nonNull(Type::string()),
                        'newOptionalArg' => Type::int()
                    ]]
            ]
        ]);
        $oldSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $oldType,
                ]
            ])
        ]);
        $newSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $newType
                ]
            ])
        ]);

        $this->assertEquals(
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_NON_NULL_ARG_ADDED,
                'description' => 'A non-null arg newRequiredArg on Type1->field1 was added.'
            ],
            FindBreakingChanges::findArgChanges($oldSchema, $newSchema)['breakingChanges'][0]);
    }

    public function testDoesNotFlagArgsWithSameTypeSignature()
    {
        $inputType1a = new InputObjectType([
            'name' => 'InputType1',
            'fields' => [
                'field1' => Type::string()
            ]
        ]);

        $inputType1b = new InputObjectType([
            'name' => 'InputType1',
            'fields' => [
                'field1' => Type::string()
            ]
        ]);

        $oldType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::int(),
                    'args' => [
                        'arg1' => Type::nonNull(Type::int()),
                        'arg2' => $inputType1a
                    ]
                ]
            ]
        ]);

        $newType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::int(),
                    'args' => [
                        'arg1' => Type::nonNull(Type::int()),
                        'arg2' => $inputType1b
                    ]
                ]
            ]
        ]);

        $oldSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $oldType,
                ]
            ])
        ]);
        $newSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $newType
                ]
            ])
        ]);

        $this->assertEquals([], FindBreakingChanges::findArgChanges($oldSchema, $newSchema)['breakingChanges']);
    }

    public function testArgsThatMoveAwayFromNonNull()
    {
        $oldType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'arg1' => Type::nonNull(Type::string()),
                    ]
                ]
            ]
        ]);
        $newType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'arg1' => Type::string()
                    ]
                ]
            ]
        ]);

        $oldSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $oldType,
                ]
            ])
        ]);
        $newSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $newType
                ]
            ])
        ]);

        $this->assertEquals([], FindBreakingChanges::findArgChanges($oldSchema, $newSchema)['breakingChanges']);
    }

    public function testDetectsRemovalOfInterfaces()
    {
        $interface1 = new InterfaceType([
            'name' => 'Interface1',
            'fields' => [
                'field1' => Type::string()
            ],
            'resolveType' => function () {
            }
        ]);
        $oldType = new ObjectType([
            'name' => 'Type1',
            'interfaces' => [$interface1],
            'fields' => [
                'field1' => Type::string()
            ]
        ]);
        $newType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => Type::string()
            ]
        ]);

        $oldSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $oldType,
                ]
            ])
        ]);
        $newSchema = new Schema([
            'query' => new ObjectType([
                'name' => 'root',
                'fields' => [
                    'type1' => $newType
                ]
            ])
        ]);

        $this->assertEquals(
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_INTERFACE_REMOVED_FROM_OBJECT,
                'description' => 'Type1 no longer implements interface Interface1.'
            ],
            FindBreakingChanges::findInterfacesRemovedFromObjectTypes($oldSchema, $newSchema)[0]);
    }

    public function testDetectsAllBreakingChanges()
    {
        $typeThatGetsRemoved = new ObjectType([
            'name' => 'TypeThatGetsRemoved',
            'fields' => [
                'field1' => Type::string()
            ]
        ]);

        $argThatChanges = new ObjectType([
            'name' => 'ArgThatChanges',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'id' => Type::int()
                    ]
                ]
            ]
        ]);

        $argChanged = new ObjectType([
            'name' => 'ArgThatChanges',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'id' => Type::string()
                    ]
                ]
            ]
        ]);

        $typeThatChangesTypeOld = new ObjectType([
            'name' => 'TypeThatChangesType',
            'fields' => [
                'field1' => Type::string()
            ]
        ]);

        $typeThatChangesTypeNew = new InterfaceType([
            'name' => 'TypeThatChangesType',
            'fields' => [
                'field1' => Type::string()
            ]
        ]);

        $typeThatHasBreakingFieldChangesOld = new InterfaceType([
            'name' => 'TypeThatHasBreakingFieldChanges',
            'fields' => [
                'field1' => Type::string(),
                'field2' => Type::string()
            ]
        ]);

        $typeThatHasBreakingFieldChangesNew = new InterfaceType([
            'name' => 'TypeThatHasBreakingFieldChanges',
            'fields' => [
                'field2' => Type::boolean()
            ]
        ]);

        $typeInUnion1 = new ObjectType([
            'name' => 'TypeInUnion1',
            'fields' => [
                'field1' => Type::string()
            ]
        ]);

        $typeInUnion2 = new ObjectType([
            'name' => 'TypeInUnion2',
            'fields' => [
                'field1' => Type::string()
            ]
        ]);

        $unionTypeThatLosesATypeOld = new UnionType([
            'name' => 'UnionTypeThatLosesAType',
            'types' => [$typeInUnion1, $typeInUnion2],
            'resolveType' => function () {
            }
        ]);

        $unionTypeThatLosesATypeNew = new UnionType([
            'name' => 'UnionTypeThatLosesAType',
            'types' => [$typeInUnion1],
            'resolveType' => function () {
            }
        ]);

        $enumTypeThatLosesAValueOld = new EnumType([
            'name' => 'EnumTypeThatLosesAValue',
            'values' => [
                'VALUE0' => 0,
                'VALUE1' => 1,
                'VALUE2' => 2
            ]
        ]);

        $enumTypeThatLosesAValueNew = new EnumType([
            'name' => 'EnumTypeThatLosesAValue',
            'values' => [
                'VALUE1' => 1,
                'VALUE2' => 2
            ]
        ]);

        $interface1 = new InterfaceType([
            'name' => 'Interface1',
            'fields' => [
                'field1' => Type::string()
            ],
            'resolveType' => function () {
            }
        ]);

        $typeThatLosesInterfaceOld = new ObjectType([
            'name' => 'TypeThatLosesInterface1',
            'interfaces' => [$interface1],
            'fields' => [
                'field1' => Type::string()
            ]
        ]);

        $typeThatLosesInterfaceNew = new ObjectType([
            'name' => 'TypeThatLosesInterface1',
            'fields' => [
                'field1' => Type::string()
            ]
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' =>
                [
                    'TypeThatGetsRemoved' => $typeThatGetsRemoved,
                    'TypeThatChangesType' => $typeThatChangesTypeOld,
                    'TypeThatHasBreakingFieldChanges' => $typeThatHasBreakingFieldChangesOld,
                    'UnionTypeThatLosesAType' => $unionTypeThatLosesATypeOld,
                    'EnumTypeThatLosesAValue' => $enumTypeThatLosesAValueOld,
                    'ArgThatChanges' => $argThatChanges,
                    'TypeThatLosesInterface' => $typeThatLosesInterfaceOld
                ]
        ]);

        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' =>
                [
                    'TypeThatChangesType' => $typeThatChangesTypeNew,
                    'TypeThatHasBreakingFieldChanges' => $typeThatHasBreakingFieldChangesNew,
                    'UnionTypeThatLosesAType' => $unionTypeThatLosesATypeNew,
                    'EnumTypeThatLosesAValue' => $enumTypeThatLosesAValueNew,
                    'ArgThatChanges' => $argChanged,
                    'TypeThatLosesInterface' => $typeThatLosesInterfaceNew,
                    'Interface1' => $interface1
                ]
        ]);

        $expectedBreakingChanges = [
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_TYPE_REMOVED,
                'description' => 'TypeThatGetsRemoved was removed.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_TYPE_REMOVED,
                'description' => 'TypeInUnion2 was removed.',
            ],
            /*
            // NB the below assertion is included in the graphql-js tests, but it makes no sense.
            // Seriously, look for what `int` type was supposed to be removed between the two schemas.  There is none.
            // I honestly think it's a bug in the js implementation and was put into the test just to make it pass.
             [
                'type' => FindBreakingChanges::BREAKING_CHANGE_TYPE_REMOVED,
                'description' => 'Int was removed.'
            ],*/
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_TYPE_CHANGED,
                'description' => 'TypeThatChangesType changed from an Object type to an Interface type.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_REMOVED,
                'description' => 'TypeThatHasBreakingFieldChanges->field1 was removed.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_FIELD_CHANGED,
                'description' => 'TypeThatHasBreakingFieldChanges->field2 changed type from String to Boolean.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_TYPE_REMOVED_FROM_UNION,
                'description' => 'TypeInUnion2 was removed from union type UnionTypeThatLosesAType.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_VALUE_REMOVED_FROM_ENUM,
                'description' => 'VALUE0 was removed from enum type EnumTypeThatLosesAValue.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_ARG_CHANGED,
                'description' => 'ArgThatChanges->field1 arg id has changed type from Int to String.',
            ],
            [
                'type' => FindBreakingChanges::BREAKING_CHANGE_INTERFACE_REMOVED_FROM_OBJECT,
                'description' => 'TypeThatLosesInterface1 no longer implements interface Interface1.',
            ]
        ];

        $this->assertEquals($expectedBreakingChanges, FindBreakingChanges::findBreakingChanges($oldSchema, $newSchema));
    }

    // findDangerousChanges tests below here

    public function testFindDangerousArgChanges()
    {
        $oldType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'name' => [
                            'type' => Type::string(),
                            'defaultValue' => 'test'
                        ]
                    ]
                ]
            ]
        ]);

        $newType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'name' => [
                            'type' => Type::string(),
                            'defaultValue' => 'Testertest'
                        ]
                    ]
                ]
            ]
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [
                $oldType
            ]
        ]);

        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [
                $newType
            ]
        ]);

        $this->assertEquals(
            [
                'type' => FindBreakingChanges::DANGEROUS_CHANGE_ARG_DEFAULT_VALUE,
                'description' => 'Type1->field1 arg name has changed defaultValue'
            ],
            FindBreakingChanges::findArgChanges($oldSchema, $newSchema)['dangerousChanges'][0]
        );
    }

    public function testDetectsEnumValueAdditions()
    {
        $oldEnumType = new EnumType([
            'name' => 'EnumType1',
            'values' => [
                'VALUE0' => 0,
                'VALUE1' => 1,
            ]
        ]);
        $newEnumType = new EnumType([
            'name' => 'EnumType1',
            'values' => [
                'VALUE0' => 0,
                'VALUE1' => 1,
                'VALUE2' => 2
            ]
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [
                $oldEnumType
            ]
        ]);

        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [
                $newEnumType
            ]
        ]);

        $this->assertEquals(
            [
                'type' => FindBreakingChanges::DANGEROUS_CHANGE_VALUE_ADDED_TO_ENUM,
                'description' => 'VALUE2 was added to enum type EnumType1'
            ],
            FindBreakingChanges::findValuesAddedToEnums($oldSchema, $newSchema)[0]
        );
    }

    public function testDetectsAdditionsToUnionType()
    {
        $type1 = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => Type::string()
            ]
        ]);

        $type1a = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => Type::string()
            ]
        ]);

        $type2 = new ObjectType([
            'name' => 'Type2',
            'fields' => [
                'field1' => Type::string()
            ]
        ]);

        $oldUnionType = new UnionType([
            'name' => 'UnionType1',
            'types' => [$type1],
            'resolveType' => function () {
            }
        ]);

        $newUnionType = new UnionType([
            'name' => 'UnionType1',
            'types' => [$type1a, $type2],
            'resolveType' => function () {
            }
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [
                $oldUnionType
            ]
        ]);

        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [
                $newUnionType
            ]
        ]);

        $this->assertEquals(
            [
                'type' => FindBreakingChanges::DANGEROUS_CHANGE_TYPE_ADDED_TO_UNION,
                'description' => 'Type2 was added to union type UnionType1'
            ],
            FindBreakingChanges::findTypesAddedToUnions($oldSchema, $newSchema)[0]
        );
    }

    public function testFindsAllDangerousChanges()
    {
        $enumThatGainsAValueOld = new EnumType([
            'name' => 'EnumType1',
            'values' => [
                'VALUE0' => 0,
                'VALUE1' => 1,
            ]
        ]);
        $enumThatGainsAValueNew = new EnumType([
            'name' => 'EnumType1',
            'values' => [
                'VALUE0' => 0,
                'VALUE1' => 1,
                'VALUE2' => 2
            ]
        ]);

        $oldType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'name' => [
                            'type' => Type::string(),
                            'defaultValue' => 'test'
                        ]
                    ]
                ]
            ]
        ]);

        $newType = new ObjectType([
            'name' => 'Type1',
            'fields' => [
                'field1' => [
                    'type' => Type::string(),
                    'args' => [
                        'name' => [
                            'type' => Type::string(),
                            'defaultValue' => 'Testertest'
                        ]
                    ]
                ]
            ]
        ]);

        $typeInUnion1 = new ObjectType([
            'name' => 'TypeInUnion1',
            'fields' => [
                'field1' => Type::string()
            ]
        ]);

        $typeInUnion2 = new ObjectType([
            'name' => 'TypeInUnion2',
            'fields' => [
                'field1' => Type::string()
            ]
        ]);

        $unionTypeThatGainsATypeOld = new UnionType([
            'name' => 'UnionType1',
            'types' => [$typeInUnion1],
            'resolveType' => function () {
            }
        ]);

        $unionTypeThatGainsATypeNew = new UnionType([
            'name' => 'UnionType1',
            'types' => [$typeInUnion1, $typeInUnion2],
            'resolveType' => function () {
            }
        ]);

        $oldSchema = new Schema([
            'query' => $this->queryType,
            'types' => [
                $oldType,
                $enumThatGainsAValueOld,
                $unionTypeThatGainsATypeOld
            ]
        ]);

        $newSchema = new Schema([
            'query' => $this->queryType,
            'types' => [
                $newType,
                $enumThatGainsAValueNew,
                $unionTypeThatGainsATypeNew
            ]
        ]);

        $expectedDangerousChanges = [
            [
                'description' => 'Type1->field1 arg name has changed defaultValue',
                'type' => FindBreakingChanges::DANGEROUS_CHANGE_ARG_DEFAULT_VALUE
            ],
            [
                'description' => 'VALUE2 was added to enum type EnumType1',
                'type' => FindBreakingChanges::DANGEROUS_CHANGE_VALUE_ADDED_TO_ENUM
            ],
            [
                'type' => FindBreakingChanges::DANGEROUS_CHANGE_TYPE_ADDED_TO_UNION,
                'description' => 'TypeInUnion2 was added to union type UnionType1',
            ]
        ];

        $this->assertEquals($expectedDangerousChanges, FindBreakingChanges::findDangerousChanges($oldSchema, $newSchema));
    }
}