<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\Source;
use GraphQL\Language\SourceLocation;
use GraphQL\Schema;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Validator\Rules\OverlappingFieldsCanBeMerged;

class OverlappingFieldsCanBeMergedTest extends TestCase
{
    // Validate: Overlapping fields can be merged

    /**
     * @it unique fields
     */
    public function testUniqueFields()
    {
        $this->expectPassesRule(new OverlappingFieldsCanBeMerged(), '
      fragment uniqueFields on Dog {
        name
        nickname
      }
        ');
    }

    /**
     * @it identical fields
     */
    public function testIdenticalFields()
    {
        $this->expectPassesRule(new OverlappingFieldsCanBeMerged, '
      fragment mergeIdenticalFields on Dog {
        name
        name
      }
        ');
    }

    /**
     * @it identical fields with identical args
     */
    public function testIdenticalFieldsWithIdenticalArgs()
    {
        $this->expectPassesRule(new OverlappingFieldsCanBeMerged, '
      fragment mergeIdenticalFieldsWithIdenticalArgs on Dog {
        doesKnowCommand(dogCommand: SIT)
        doesKnowCommand(dogCommand: SIT)
      }
        ');
    }

    /**
     * @it identical fields with identical directives
     */
    public function testIdenticalFieldsWithIdenticalDirectives()
    {
        $this->expectPassesRule(new OverlappingFieldsCanBeMerged, '
      fragment mergeSameFieldsWithSameDirectives on Dog {
        name @include(if: true)
        name @include(if: true)
      }
        ');
    }

    /**
     * @it different args with different aliases
     */
    public function testDifferentArgsWithDifferentAliases()
    {
        $this->expectPassesRule(new OverlappingFieldsCanBeMerged, '
      fragment differentArgsWithDifferentAliases on Dog {
        knowsSit : doesKnowCommand(dogCommand: SIT)
        knowsDown : doesKnowCommand(dogCommand: DOWN)
      }
        ');
    }

    /**
     * @it different directives with different aliases
     */
    public function testDifferentDirectivesWithDifferentAliases()
    {
        $this->expectPassesRule(new OverlappingFieldsCanBeMerged, '
      fragment differentDirectivesWithDifferentAliases on Dog {
        nameIfTrue : name @include(if: true)
        nameIfFalse : name @include(if: false)
      }
        ');
    }

    /**
     * @it different skip/include directives accepted
     */
    public function testDifferentSkipIncludeDirectivesAccepted()
    {
        // Note: Differing skip/include directives don't create an ambiguous return
        // value and are acceptable in conditions where differing runtime values
        // may have the same desired effect of including or skipping a field.
        $this->expectPassesRule(new OverlappingFieldsCanBeMerged, '
      fragment differentDirectivesWithDifferentAliases on Dog {
        name @include(if: true)
        name @include(if: false)
      }
    ');
    }

    /**
     * @it Same aliases with different field targets
     */
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

    /**
     * @it Same aliases allowed on non-overlapping fields
     */
    public function testSameAliasesAllowedOnNonOverlappingFields()
    {
        // This is valid since no object can be both a "Dog" and a "Cat", thus
        // these fields can never overlap.
        $this->expectPassesRule(new OverlappingFieldsCanBeMerged, '
      fragment sameAliasesWithDifferentFieldTargets on Pet {
        ... on Dog {
          name
        }
        ... on Cat {
          name: nickname
        }
      }
        ');
    }

    /**
     * @it Alias masking direct field access
     */
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

    /**
     * @it different args, second adds an argument
     */
    public function testDifferentArgsSecondAddsAnArgument()
    {
        $this->expectFailsRule(new OverlappingFieldsCanBeMerged, '
      fragment conflictingArgs on Dog {
        doesKnowCommand
        doesKnowCommand(dogCommand: HEEL)
      }
        ', [
            FormattedError::create(
                OverlappingFieldsCanBeMerged::fieldsConflictMessage('doesKnowCommand', 'they have differing arguments'),
                [new SourceLocation(3, 9), new SourceLocation(4, 9)]
            )
        ]);
    }

    /**
     * @it different args, second missing an argument
     */
    public function testDifferentArgsSecondMissingAnArgument()
    {
        $this->expectFailsRule(new OverlappingFieldsCanBeMerged, '
      fragment conflictingArgs on Dog {
        doesKnowCommand(dogCommand: SIT)
        doesKnowCommand
      }
        ',
            [
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage('doesKnowCommand', 'they have differing arguments'),
                    [new SourceLocation(3, 9), new SourceLocation(4, 9)]
                )
            ]
        );
    }

    /**
     * @it conflicting args
     */
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

    /**
     * @it allows different args where no conflict is possible
     */
    public function testAllowsDifferentArgsWhereNoConflictIsPossible()
    {
        // This is valid since no object can be both a "Dog" and a "Cat", thus
        // these fields can never overlap.
        $this->expectPassesRule(new OverlappingFieldsCanBeMerged, '
      fragment conflictingArgs on Pet {
        ... on Dog {
          name(surname: true)
        }
        ... on Cat {
          name
        }
      }
        ');
    }

    /**
     * @it encounters conflict in fragments
     */
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

    /**
     * @it reports each conflict once
     */
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

    /**
     * @it deep conflict
     */
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
                    new SourceLocation(4, 11),
                    new SourceLocation(6,9),
                    new SourceLocation(7, 11)
                ]
            )
        ]);
    }

    /**
     * @it deep conflict with multiple issues
     */
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
                    new SourceLocation(4,11),
                    new SourceLocation(5,11),
                    new SourceLocation(7,9),
                    new SourceLocation(8,11),
                    new SourceLocation(9,11)
                ]
            )
        ]);
    }

    /**
     * @it very deep conflict
     */
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
                    new SourceLocation(4,11),
                    new SourceLocation(5,13),
                    new SourceLocation(8,9),
                    new SourceLocation(9,11),
                    new SourceLocation(10,13)
                ]
            )
        ]);
    }

    /**
     * @it reports deep conflict to nearest common ancestor
     */
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
                    new SourceLocation(5,13),
                    new SourceLocation(7,11),
                    new SourceLocation(8,13)
                ]
            )
        ]);
    }

    // Describe: return types must be unambiguous

    /**
     * @it conflicting return types which potentially overlap
     */
    public function testConflictingReturnTypesWhichPotentiallyOverlap()
    {
        // This is invalid since an object could potentially be both the Object
        // type IntBox and the interface type NonNullStringBox1. While that
        // condition does not exist in the current schema, the schema could
        // expand in the future to allow this. Thus it is invalid.
        $this->expectFailsRuleWithSchema($this->getTestSchema(), new OverlappingFieldsCanBeMerged, '
        {
          someBox {
            ...on IntBox {
              scalar
            }
            ...on NonNullStringBox1 {
              scalar
            }
          }
        }
        ', [
            FormattedError::create(
                OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                'scalar',
                'they return conflicting types Int and String!'
                ),
                [new SourceLocation(5, 15),
                new SourceLocation(8, 15)]
            )
        ]);
    }

    /**
     * @it compatible return shapes on different return types
     */
    public function testCompatibleReturnShapesOnDifferentReturnTypes()
    {
        // In this case `deepBox` returns `SomeBox` in the first usage, and
        // `StringBox` in the second usage. These return types are not the same!
        // however this is valid because the return *shapes* are compatible.
        $this->expectPassesRuleWithSchema($this->getTestSchema(), new OverlappingFieldsCanBeMerged, '
      {
        someBox {
          ... on SomeBox {
            deepBox {
              unrelatedField
            }
          }
          ... on StringBox {
            deepBox {
              unrelatedField
            }
          }
        }
      }
        ');
    }

    /**
     * @it disallows differing return types despite no overlap
     */
    public function testDisallowsDifferingReturnTypesDespiteNoOverlap()
    {
        $this->expectFailsRuleWithSchema($this->getTestSchema(), new OverlappingFieldsCanBeMerged, '
        {
          someBox {
            ... on IntBox {
              scalar
            }
            ... on StringBox {
              scalar
            }
          }
        }
        ', [
            FormattedError::create(
                OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                    'scalar',
                    'they return conflicting types Int and String'
                ),
                [ new SourceLocation(5, 15),
                    new SourceLocation(8, 15)]
            )
        ]);
    }

    /**
     * @it disallows differing return type nullability despite no overlap
     */
    public function testDisallowsDifferingReturnTypeNullabilityDespiteNoOverlap()
    {
        $this->expectFailsRuleWithSchema($this->getTestSchema(), new OverlappingFieldsCanBeMerged, '
        {
          someBox {
            ... on NonNullStringBox1 {
              scalar
            }
            ... on StringBox {
              scalar
            }
          }
        }
        ', [
            FormattedError::create(
                OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                    'scalar',
                    'they return conflicting types String! and String'
                ),
                [new SourceLocation(5, 15),
                 new SourceLocation(8, 15)]
            )
        ]);
    }

    /**
     * @it disallows differing return type list despite no overlap
     */
    public function testDisallowsDifferingReturnTypeListDespiteNoOverlap()
    {
        $this->expectFailsRuleWithSchema($this->getTestSchema(), new OverlappingFieldsCanBeMerged, '
        {
          someBox {
            ... on IntBox {
              box: listStringBox {
                scalar
              }
            }
            ... on StringBox {
              box: stringBox {
                scalar
              }
            }
          }
        }
        ', [
            FormattedError::create(
                OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                    'box',
                    'they return conflicting types [StringBox] and StringBox'
                ),
                [new SourceLocation(5, 15),
                new SourceLocation(10, 15)]
            )
        ]);


        $this->expectFailsRuleWithSchema($this->getTestSchema(), new OverlappingFieldsCanBeMerged, '
        {
          someBox {
            ... on IntBox {
              box: stringBox {
                scalar
              }
            }
            ... on StringBox {
              box: listStringBox {
                scalar
              }
            }
          }
        }
        ', [
            FormattedError::create(
                OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                    'box',
                    'they return conflicting types StringBox and [StringBox]'
                ),
                [new SourceLocation(5, 15),
                new SourceLocation(10, 15)]
            )
      ]);
    }

    public function testDisallowsDifferingSubfields()
    {
        $this->expectFailsRuleWithSchema($this->getTestSchema(), new OverlappingFieldsCanBeMerged, '
        {
          someBox {
            ... on IntBox {
              box: stringBox {
                val: scalar
                val: unrelatedField
              }
            }
            ... on StringBox {
              box: stringBox {
                val: scalar
              }
            }
          }
        }
        ', [

            FormattedError::create(
                OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                    'val',
                    'scalar and unrelatedField are different fields'
                ),
                [new SourceLocation(6, 17),
                new SourceLocation(7, 17)]
            )
        ]);
    }

    /**
     * @it disallows differing deep return types despite no overlap
     */
    public function testDisallowsDifferingDeepReturnTypesDespiteNoOverlap()
    {
        $this->expectFailsRuleWithSchema($this->getTestSchema(), new OverlappingFieldsCanBeMerged, '
        {
          someBox {
            ... on IntBox {
              box: stringBox {
                scalar
              }
            }
            ... on StringBox {
              box: intBox {
                scalar
              }
            }
          }
        }
        ', [
            FormattedError::create(
                OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                    'box',
                    [ [ 'scalar', 'they return conflicting types String and Int' ] ]
                ),
                [
                    new SourceLocation(5, 15),
                    new SourceLocation(6, 17),
                    new SourceLocation(10, 15),
                    new SourceLocation(11, 17)
                ]
            )
        ]);
    }

    /**
     * @it allows non-conflicting overlaping types
     */
    public function testAllowsNonConflictingOverlapingTypes()
    {
        $this->expectPassesRuleWithSchema($this->getTestSchema(), new OverlappingFieldsCanBeMerged, '
        {
          someBox {
            ... on IntBox {
              scalar: unrelatedField
            }
            ... on StringBox {
              scalar
            }
          }
        }
        ');
    }

    /**
     * @it same wrapped scalar return types
     */
    public function testSameWrappedScalarReturnTypes()
    {
        $this->expectPassesRuleWithSchema($this->getTestSchema(), new OverlappingFieldsCanBeMerged, '
        {
          someBox {
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

    /**
     * @it allows inline typeless fragments
     */
    public function testAllowsInlineTypelessFragments()
    {
        $this->expectPassesRuleWithSchema($this->getTestSchema(), new OverlappingFieldsCanBeMerged, '
        {
          a
          ... {
            a
          }
        }
        ');
    }

    /**
     * @it compares deep types including list
     */
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
                    new SourceLocation(14, 11),
                    new SourceLocation(15, 13),
                    new SourceLocation(16, 15),
                    new SourceLocation(5, 13),
                    new SourceLocation(6, 15),
                    new SourceLocation(7, 17),
                ]
            )
        ]);
     }

    /**
     * @it ignores unknown types
     */
    public function testIgnoresUnknownTypes()
    {
        $this->expectPassesRuleWithSchema($this->getTestSchema(), new OverlappingFieldsCanBeMerged, '
        {
          someBox {
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
        $StringBox = null;
        $IntBox = null;
        $SomeBox = null;

        $SomeBox = new InterfaceType([
            'name' => 'SomeBox',
            'resolveType' => function() use (&$StringBox) {return $StringBox;},
            'fields' => function() use (&$SomeBox) {
                return [
                    'deepBox' => ['type' => $SomeBox],
                    'unrelatedField' =>  ['type' => Type::string()]
                ];

            }
        ]);

        $StringBox = new ObjectType([
            'name' => 'StringBox',
            'interfaces' => [$SomeBox],
            'fields' => function() use (&$StringBox, &$IntBox) {
                return [
                    'scalar' => ['type' => Type::string()],
                    'deepBox' => ['type' => $StringBox],
                    'unrelatedField' => ['type' => Type::string()],
                    'listStringBox' => ['type' => Type::listOf($StringBox)],
                    'stringBox' => ['type' => $StringBox],
                    'intBox' => ['type' => $IntBox],
                ];
            }
        ]);

        $IntBox = new ObjectType([
            'name' => 'IntBox',
            'interfaces' => [$SomeBox],
            'fields' => function() use (&$StringBox, &$IntBox) {
                return [
                    'scalar' => ['type' => Type::int()],
                    'deepBox' => ['type' => $IntBox],
                    'unrelatedField' => ['type' => Type::string()],
                    'listStringBox' => ['type' => Type::listOf($StringBox)],
                    'stringBox' => ['type' => $StringBox],
                    'intBox' => ['type' => $IntBox],
                ];
            }
        ]);

        $NonNullStringBox1 = new InterfaceType([
            'name' => 'NonNullStringBox1',
            'resolveType' => function() use (&$StringBox) {return $StringBox;},
            'fields' => [
                'scalar' => [ 'type' => Type::nonNull(Type::string()) ]
            ]
        ]);

        $NonNullStringBox1Impl = new ObjectType([
            'name' => 'NonNullStringBox1Impl',
            'interfaces' => [ $SomeBox, $NonNullStringBox1 ],
            'fields' => [
                'scalar' => [ 'type' => Type::nonNull(Type::string()) ],
                'unrelatedField' => ['type' => Type::string() ],
                'deepBox' => [ 'type' => $SomeBox ],
            ]
        ]);

        $NonNullStringBox2 = new InterfaceType([
            'name' => 'NonNullStringBox2',
            'resolveType' => function() use (&$StringBox) {return $StringBox;},
            'fields' => [
                'scalar' => ['type' => Type::nonNull(Type::string())]
            ]
        ]);

        $NonNullStringBox2Impl = new ObjectType([
            'name' => 'NonNullStringBox2Impl',
            'interfaces' => [ $SomeBox, $NonNullStringBox2 ],
            'fields' => [
                'scalar' => [ 'type' => Type::nonNull(Type::string()) ],
                'unrelatedField' => [ 'type' => Type::string() ],
                'deepBox' => [ 'type' => $SomeBox ],
            ]
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

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'QueryRoot',
                'fields' => [
                    'someBox' => ['type' => $SomeBox],
                    'connection' => ['type' => $Connection]
                ]
            ]),
            'types' => [$IntBox, $StringBox, $NonNullStringBox1Impl, $NonNullStringBox2Impl]
        ]);

        return $schema;
    }
}
