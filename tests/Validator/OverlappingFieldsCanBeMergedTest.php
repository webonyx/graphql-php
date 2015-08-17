<?php
namespace GraphQL\Validator;

use GraphQL\FormattedError;
use GraphQL\Language\Source;
use GraphQL\Language\SourceLocation;
use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Validator\Rules\OverlappingFieldsCanBeMerged;

class OverlappingFieldsCanBeMergedTest extends TestCase
{
    // Validate: Overlapping fields can be merged

    public function testUniqueFields()
    {
        $this->expectPassesRule(new OverlappingFieldsCanBeMerged(), '
      fragment uniqueFields on Dog {
        name
        nickname
      }
        ');
    }

    public function testIdenticalFields()
    {
        $this->expectPassesRule(new OverlappingFieldsCanBeMerged, '
      fragment mergeIdenticalFields on Dog {
        name
        name
      }
        ');
    }

    public function testIdenticalFieldsWithIdenticalArgs()
    {
        $this->expectPassesRule(new OverlappingFieldsCanBeMerged, '
      fragment mergeIdenticalFieldsWithIdenticalArgs on Dog {
        doesKnowCommand(dogCommand: SIT)
        doesKnowCommand(dogCommand: SIT)
      }
        ');
    }

    public function testIdenticalFieldsWithIdenticalDirectives()
    {
        $this->expectPassesRule(new OverlappingFieldsCanBeMerged, '
      fragment mergeSameFieldsWithSameDirectives on Dog {
        name @include(if: true)
        name @include(if: true)
      }
        ');
    }

    public function testDifferentArgsWithDifferentAliases()
    {
        $this->expectPassesRule(new OverlappingFieldsCanBeMerged, '
      fragment differentArgsWithDifferentAliases on Dog {
        knowsSit : doesKnowCommand(dogCommand: SIT)
        knowsDown : doesKnowCommand(dogCommand: DOWN)
      }
        ');
    }

    public function testDifferentDirectivesWithDifferentAliases()
    {
        $this->expectPassesRule(new OverlappingFieldsCanBeMerged, '
      fragment differentDirectivesWithDifferentAliases on Dog {
        nameIfTrue : name @include(if: true)
        nameIfFalse : name @include(if: false)
      }
        ');
    }

    public function testSameAliasesWithDifferentFieldTargets()
    {
        $this->expectFailsRule(new OverlappingFieldsCanBeMerged, '
      fragment sameAliasesWithDifferentFieldTargets on Dog {
        fido : name
        fido : nickname
      }
        ', [
            FormattedError::create(
                OverlappingFieldsCanBeMerged::fieldsConflictMessage('fido', 'name and nickname are different fields'),
                [new SourceLocation(3, 9), new SourceLocation(4, 9)]
            )
        ]);
    }

    public function testAliasMaskingDirectFieldAccess()
    {
        $this->expectFailsRule(new OverlappingFieldsCanBeMerged, '
      fragment aliasMaskingDirectFieldAccess on Dog {
        name : nickname
        name
      }
        ', [
            FormattedError::create(
                OverlappingFieldsCanBeMerged::fieldsConflictMessage('name', 'nickname and name are different fields'),
                [new SourceLocation(3, 9), new SourceLocation(4, 9)]
            )
        ]);
    }

    public function testConflictingArgs()
    {
        $this->expectFailsRule(new OverlappingFieldsCanBeMerged, '
      fragment conflictingArgs on Dog {
        doesKnowCommand(dogCommand: SIT)
        doesKnowCommand(dogCommand: HEEL)
      }
        ', [
            FormattedError::create(
                OverlappingFieldsCanBeMerged::fieldsConflictMessage('doesKnowCommand', 'they have differing arguments'),
                [new SourceLocation(3,9), new SourceLocation(4,9)]
            )
        ]);
    }

    public function testConflictingDirectives()
    {
        $this->expectFailsRule(new OverlappingFieldsCanBeMerged, '
      fragment conflictingDirectiveArgs on Dog {
        name @include(if: true)
        name @skip(if: true)
      }
        ', [
            FormattedError::create(
                OverlappingFieldsCanBeMerged::fieldsConflictMessage('name', 'they have differing directives'),
                [new SourceLocation(3, 9), new SourceLocation(4, 9)]
            )
        ]);
    }

    public function testConflictingDirectiveArgs()
    {
        $this->expectFailsRule(new OverlappingFieldsCanBeMerged, '
      fragment conflictingDirectiveArgs on Dog {
        name @include(if: true)
        name @include(if: false)
      }
    ', [
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage('name', 'they have differing directives'),
                    [new SourceLocation(3, 9), new SourceLocation(4, 9)]
                )
            ]
        );
    }

    public function testConflictingArgsWithMatchingDirectives()
    {
        $this->expectFailsRule(new OverlappingFieldsCanBeMerged, '
      fragment conflictingArgsWithMatchingDirectiveArgs on Dog {
        doesKnowCommand(dogCommand: SIT) @include(if: true)
        doesKnowCommand(dogCommand: HEEL) @include(if: true)
      }
        ', [
            FormattedError::create(
                OverlappingFieldsCanBeMerged::fieldsConflictMessage('doesKnowCommand', 'they have differing arguments'),
                [new SourceLocation(3, 9), new SourceLocation(4, 9)]
            )
        ]);
    }

    public function testConflictingDirectivesWithMatchingArgs()
    {
        $this->expectFailsRule(new OverlappingFieldsCanBeMerged, '
      fragment conflictingDirectiveArgsWithMatchingArgs on Dog {
        doesKnowCommand(dogCommand: SIT) @include(if: true)
        doesKnowCommand(dogCommand: SIT) @skip(if: true)
      }
        ', [
            FormattedError::create(
                OverlappingFieldsCanBeMerged::fieldsConflictMessage('doesKnowCommand', 'they have differing directives'),
                [new SourceLocation(3, 9), new SourceLocation(4, 9)]
            )
        ]);
    }

    public function testEncountersConflictInFragments()
    {
        $this->expectFailsRule(new OverlappingFieldsCanBeMerged, '
      {
        ...A
        ...B
      }
      fragment A on Type {
        x: a
      }
      fragment B on Type {
        x: b
      }
        ', [
            FormattedError::create(
                OverlappingFieldsCanBeMerged::fieldsConflictMessage('x', 'a and b are different fields'),
                [new SourceLocation(7, 9), new SourceLocation(10, 9)]
            )
        ]);
    }

    public function testReportsEachConflictOnce()
    {
        $this->expectFailsRule(new OverlappingFieldsCanBeMerged, '
      {
        f1 {
          ...A
          ...B
        }
        f2 {
          ...B
          ...A
        }
        f3 {
          ...A
          ...B
          x: c
        }
      }
      fragment A on Type {
        x: a
      }
      fragment B on Type {
        x: b
      }
    ', [
            FormattedError::create(
                OverlappingFieldsCanBeMerged::fieldsConflictMessage('x', 'a and b are different fields'),
                [new SourceLocation(18, 9), new SourceLocation(21, 9)]
            ),
            FormattedError::create(
                OverlappingFieldsCanBeMerged::fieldsConflictMessage('x', 'a and c are different fields'),
                [new SourceLocation(18, 9), new SourceLocation(14, 11)]
            ),
            FormattedError::create(
                OverlappingFieldsCanBeMerged::fieldsConflictMessage('x', 'b and c are different fields'),
                [new SourceLocation(21, 9), new SourceLocation(14, 11)]
            )
        ]);
    }

    public function testDeepConflict()
    {
        $this->expectFailsRule(new OverlappingFieldsCanBeMerged, '
      {
        field {
          x: a
        },
        field {
          x: b
        }
      }
        ', [
            FormattedError::create(
                OverlappingFieldsCanBeMerged::fieldsConflictMessage('field', [['x', 'a and b are different fields']]),
                [
                    new SourceLocation(3, 9),
                    new SourceLocation(6,9),
                    new SourceLocation(4, 11),
                    new SourceLocation(7, 11)
                ]
            )
        ]);
    }

    public function testDeepConflictWithMultipleIssues()
    {
        $this->expectFailsRule(new OverlappingFieldsCanBeMerged, '
      {
        field {
          x: a
          y: c
        },
        field {
          x: b
          y: d
        }
      }
        ', [
            FormattedError::create(
                OverlappingFieldsCanBeMerged::fieldsConflictMessage('field', [
                    ['x', 'a and b are different fields'],
                    ['y', 'c and d are different fields']
                ]),
                [
                    new SourceLocation(3,9),
                    new SourceLocation(7,9),
                    new SourceLocation(4,11),
                    new SourceLocation(8,11),
                    new SourceLocation(5,11),
                    new SourceLocation(9,11)
                ]
            )
        ]);
    }

    public function testVeryDeepConflict()
    {
        $this->expectFailsRule(new OverlappingFieldsCanBeMerged, '
      {
        field {
          deepField {
            x: a
          }
        },
        field {
          deepField {
            x: b
          }
        }
      }
        ', [
            FormattedError::create(
                OverlappingFieldsCanBeMerged::fieldsConflictMessage('field', [['deepField', [['x', 'a and b are different fields']]]]),
                [
                    new SourceLocation(3,9),
                    new SourceLocation(8,9),
                    new SourceLocation(4,11),
                    new SourceLocation(9,11),
                    new SourceLocation(5,13),
                    new SourceLocation(10,13)
                ]
            )
        ]);
    }

    public function testReportsDeepConflictToNearestCommonAncestor()
    {
        $this->expectFailsRule(new OverlappingFieldsCanBeMerged, '
      {
        field {
          deepField {
            x: a
          }
          deepField {
            x: b
          }
        },
        field {
          deepField {
            y
          }
        }
      }
        ', [
            FormattedError::create(
                OverlappingFieldsCanBeMerged::fieldsConflictMessage('deepField', [['x', 'a and b are different fields']]),
                [
                    new SourceLocation(4,11),
                    new SourceLocation(7,11),
                    new SourceLocation(5,13),
                    new SourceLocation(8,13)
                ]
            )
        ]);
    }

    // return types must be unambiguous
    public function testConflictingScalarReturnTypes()
    {
        $this->expectFailsRuleWithSchema($this->getTestSchema(), new OverlappingFieldsCanBeMerged, '
        {
          boxUnion {
            ...on IntBox {
              scalar
            }
            ...on StringBox {
              scalar
            }
          }
        }
      ', [
            FormattedError::create(
                OverlappingFieldsCanBeMerged::fieldsConflictMessage('scalar', 'they return differing types Int and String'),
                [ new SourceLocation(5,15), new SourceLocation(8,15) ]
            )
        ]);
    }

    public function testSameWrappedScalarReturnTypes()
    {
        $this->expectPassesRuleWithSchema($this->getTestSchema(), new OverlappingFieldsCanBeMerged, '
        {
          boxUnion {
            ...on NonNullStringBox1 {
              scalar
            }
            ...on NonNullStringBox2 {
              scalar
            }
          }
        }
        ');
    }

    public function testComparesDeepTypesIncludingList()
    {
        $this->expectFailsRuleWithSchema($this->getTestSchema(), new OverlappingFieldsCanBeMerged, '
        {
          connection {
            ...edgeID
            edges {
              node {
                id: name
              }
            }
          }
        }

        fragment edgeID on Connection {
          edges {
            node {
              id
            }
          }
        }
      ', [
            FormattedError::create(
                OverlappingFieldsCanBeMerged::fieldsConflictMessage('edges', [['node', [['id', 'id and name are different fields']]]]),
                [
                    new SourceLocation(14, 11), new SourceLocation(5, 13),
                    new SourceLocation(15, 13), new SourceLocation(6, 15),
                    new SourceLocation(16, 15), new SourceLocation(7, 17),
                ]
            )
        ]);
     }

    public function testIgnoresUnknownTypes()
    {
        $this->expectPassesRuleWithSchema($this->getTestSchema(), new OverlappingFieldsCanBeMerged, '
        {
          boxUnion {
            ...on UnknownType {
              scalar
            }
            ...on NonNullStringBox2 {
              scalar
            }
          }
        }
        ');
    }

    private function getTestSchema()
    {
        $StringBox = new ObjectType([
            'name' => 'StringBox',
            'fields' => [
                'scalar' => [ 'type' => Type::string() ]
            ]
        ]);

        $IntBox = new ObjectType([
            'name' => 'IntBox',
            'fields' => [
                'scalar' => ['type' => Type::int() ]
            ]
        ]);

        $NonNullStringBox1 = new ObjectType([
            'name' => 'NonNullStringBox1',
            'fields' => [
                'scalar' => [ 'type' => Type::nonNull(Type::string()) ]
            ]
        ]);

        $NonNullStringBox2 = new ObjectType([
            'name' => 'NonNullStringBox2',
            'fields' => [
                'scalar' => ['type' => Type::nonNull(Type::string())]
            ]
        ]);

        $BoxUnion = new UnionType([
            'name' => 'BoxUnion',
            'resolveType' => function()  use ($StringBox) {return $StringBox;},
            'types' => [ $StringBox, $IntBox, $NonNullStringBox1, $NonNullStringBox2 ]
        ]);

        $Connection = new ObjectType([
            'name' => 'Connection',
            'fields' => [
                'edges' => [
                    'type' => Type::listOf(new ObjectType([
                        'name' => 'Edge',
                        'fields' => [
                            'node' => [
                                'type' => new ObjectType([
                                    'name' => 'Node',
                                    'fields' => [
                                        'id' => ['type' => Type::id()],
                                        'name' => ['type' => Type::string()]
                                    ]
                                ])
                            ]
                        ]
                    ]))
                ]
            ]
        ]);

        $schema = new Schema(new ObjectType([
            'name' => 'QueryRoot',
            'fields' => [
                'boxUnion' => ['type' => $BoxUnion ],
                'connection' => ['type' => $Connection]
            ]
        ]));

        return $schema;
    }
}
