<?php
/**
 * FindBreakingChanges tests
 */

namespace GraphQL\Tests\Utils;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use GraphQL\Utils\FindBreakingChanges;

class FindBreakingChangesTest extends \PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        $this->queryType = new ObjectType([
            'name' => 'Type1',
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
}