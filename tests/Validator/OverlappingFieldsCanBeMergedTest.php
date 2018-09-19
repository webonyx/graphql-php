<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Validator\Rules\OverlappingFieldsCanBeMerged;

class OverlappingFieldsCanBeMergedTest extends ValidatorTestCase
{
    // Validate: Overlapping fields can be merged
    /**
     * @see it('unique fields')
     */
    public function testUniqueFields() : void
    {
        $this->expectPassesRule(
            new OverlappingFieldsCanBeMerged(),
            '
      fragment uniqueFields on Dog {
        name
        nickname
      }
        '
        );
    }

    /**
     * @see it('identical fields')
     */
    public function testIdenticalFields() : void
    {
        $this->expectPassesRule(
            new OverlappingFieldsCanBeMerged(),
            '
      fragment mergeIdenticalFields on Dog {
        name
        name
      }
        '
        );
    }

    /**
     * @see it('identical fields with identical args')
     */
    public function testIdenticalFieldsWithIdenticalArgs() : void
    {
        $this->expectPassesRule(
            new OverlappingFieldsCanBeMerged(),
            '
      fragment mergeIdenticalFieldsWithIdenticalArgs on Dog {
        doesKnowCommand(dogCommand: SIT)
        doesKnowCommand(dogCommand: SIT)
      }
        '
        );
    }

    /**
     * @see it('identical fields with identical directives')
     */
    public function testIdenticalFieldsWithIdenticalDirectives() : void
    {
        $this->expectPassesRule(
            new OverlappingFieldsCanBeMerged(),
            '
      fragment mergeSameFieldsWithSameDirectives on Dog {
        name @include(if: true)
        name @include(if: true)
      }
        '
        );
    }

    /**
     * @see it('different args with different aliases')
     */
    public function testDifferentArgsWithDifferentAliases() : void
    {
        $this->expectPassesRule(
            new OverlappingFieldsCanBeMerged(),
            '
      fragment differentArgsWithDifferentAliases on Dog {
        knowsSit : doesKnowCommand(dogCommand: SIT)
        knowsDown : doesKnowCommand(dogCommand: DOWN)
      }
        '
        );
    }

    /**
     * @see it('different directives with different aliases')
     */
    public function testDifferentDirectivesWithDifferentAliases() : void
    {
        $this->expectPassesRule(
            new OverlappingFieldsCanBeMerged(),
            '
      fragment differentDirectivesWithDifferentAliases on Dog {
        nameIfTrue : name @include(if: true)
        nameIfFalse : name @include(if: false)
      }
        '
        );
    }

    /**
     * @see it('different skip/include directives accepted')
     */
    public function testDifferentSkipIncludeDirectivesAccepted() : void
    {
        // Note: Differing skip/include directives don't create an ambiguous return
        // value and are acceptable in conditions where differing runtime values
        // may have the same desired effect of including or skipping a field.
        $this->expectPassesRule(
            new OverlappingFieldsCanBeMerged(),
            '
      fragment differentDirectivesWithDifferentAliases on Dog {
        name @include(if: true)
        name @include(if: false)
      }
    '
        );
    }

    /**
     * @see it('Same aliases with different field targets')
     */
    public function testSameAliasesWithDifferentFieldTargets() : void
    {
        $this->expectFailsRule(
            new OverlappingFieldsCanBeMerged(),
            '
      fragment sameAliasesWithDifferentFieldTargets on Dog {
        fido : name
        fido : nickname
      }
        ',
            [
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                        'fido',
                        'name and nickname are different fields'
                    ),
                    [new SourceLocation(3, 9), new SourceLocation(4, 9)]
                ),
            ]
        );
    }

    /**
     * @see it('Same aliases allowed on non-overlapping fields')
     */
    public function testSameAliasesAllowedOnNonOverlappingFields() : void
    {
        // This is valid since no object can be both a "Dog" and a "Cat", thus
        // these fields can never overlap.
        $this->expectPassesRule(
            new OverlappingFieldsCanBeMerged(),
            '
      fragment sameAliasesWithDifferentFieldTargets on Pet {
        ... on Dog {
          name
        }
        ... on Cat {
          name: nickname
        }
      }
        '
        );
    }

    /**
     * @see it('Alias masking direct field access')
     */
    public function testAliasMaskingDirectFieldAccess() : void
    {
        $this->expectFailsRule(
            new OverlappingFieldsCanBeMerged(),
            '
      fragment aliasMaskingDirectFieldAccess on Dog {
        name : nickname
        name
      }
        ',
            [
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                        'name',
                        'nickname and name are different fields'
                    ),
                    [new SourceLocation(3, 9), new SourceLocation(4, 9)]
                ),
            ]
        );
    }

    /**
     * @see it('different args, second adds an argument')
     */
    public function testDifferentArgsSecondAddsAnArgument() : void
    {
        $this->expectFailsRule(
            new OverlappingFieldsCanBeMerged(),
            '
      fragment conflictingArgs on Dog {
        doesKnowCommand
        doesKnowCommand(dogCommand: HEEL)
      }
        ',
            [
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                        'doesKnowCommand',
                        'they have differing arguments'
                    ),
                    [new SourceLocation(3, 9), new SourceLocation(4, 9)]
                ),
            ]
        );
    }

    /**
     * @see it('different args, second missing an argument')
     */
    public function testDifferentArgsSecondMissingAnArgument() : void
    {
        $this->expectFailsRule(
            new OverlappingFieldsCanBeMerged(),
            '
      fragment conflictingArgs on Dog {
        doesKnowCommand(dogCommand: SIT)
        doesKnowCommand
      }
        ',
            [
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                        'doesKnowCommand',
                        'they have differing arguments'
                    ),
                    [new SourceLocation(3, 9), new SourceLocation(4, 9)]
                ),
            ]
        );
    }

    /**
     * @see it('conflicting args')
     */
    public function testConflictingArgs() : void
    {
        $this->expectFailsRule(
            new OverlappingFieldsCanBeMerged(),
            '
      fragment conflictingArgs on Dog {
        doesKnowCommand(dogCommand: SIT)
        doesKnowCommand(dogCommand: HEEL)
      }
        ',
            [
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                        'doesKnowCommand',
                        'they have differing arguments'
                    ),
                    [new SourceLocation(3, 9), new SourceLocation(4, 9)]
                ),
            ]
        );
    }

    /**
     * @see it('allows different args where no conflict is possible')
     */
    public function testAllowsDifferentArgsWhereNoConflictIsPossible() : void
    {
        // This is valid since no object can be both a "Dog" and a "Cat", thus
        // these fields can never overlap.
        $this->expectPassesRule(
            new OverlappingFieldsCanBeMerged(),
            '
      fragment conflictingArgs on Pet {
        ... on Dog {
          name(surname: true)
        }
        ... on Cat {
          name
        }
      }
        '
        );
    }

    /**
     * @see it('encounters conflict in fragments')
     */
    public function testEncountersConflictInFragments() : void
    {
        $this->expectFailsRule(
            new OverlappingFieldsCanBeMerged(),
            '
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
        ',
            [
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage('x', 'a and b are different fields'),
                    [new SourceLocation(7, 9), new SourceLocation(10, 9)]
                ),
            ]
        );
    }

    /**
     * @see it('reports each conflict once')
     */
    public function testReportsEachConflictOnce() : void
    {
        $this->expectFailsRule(
            new OverlappingFieldsCanBeMerged(),
            '
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
    ',
            [
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage('x', 'a and b are different fields'),
                    [new SourceLocation(18, 9), new SourceLocation(21, 9)]
                ),
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage('x', 'c and a are different fields'),
                    [new SourceLocation(14, 11), new SourceLocation(18, 9)]
                ),
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage('x', 'c and b are different fields'),
                    [new SourceLocation(14, 11), new SourceLocation(21, 9)]
                ),
            ]
        );
    }

    /**
     * @see it('deep conflict')
     */
    public function testDeepConflict() : void
    {
        $this->expectFailsRule(
            new OverlappingFieldsCanBeMerged(),
            '
      {
        field {
          x: a
        },
        field {
          x: b
        }
      }
        ',
            [
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                        'field',
                        [['x', 'a and b are different fields']]
                    ),
                    [
                        new SourceLocation(3, 9),
                        new SourceLocation(4, 11),
                        new SourceLocation(6, 9),
                        new SourceLocation(7, 11),
                    ]
                ),
            ]
        );
    }

    /**
     * @see it('deep conflict with multiple issues')
     */
    public function testDeepConflictWithMultipleIssues() : void
    {
        $this->expectFailsRule(
            new OverlappingFieldsCanBeMerged(),
            '
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
        ',
            [
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                        'field',
                        [
                            ['x', 'a and b are different fields'],
                            ['y', 'c and d are different fields'],
                        ]
                    ),
                    [
                        new SourceLocation(3, 9),
                        new SourceLocation(4, 11),
                        new SourceLocation(5, 11),
                        new SourceLocation(7, 9),
                        new SourceLocation(8, 11),
                        new SourceLocation(9, 11),
                    ]
                ),
            ]
        );
    }

    /**
     * @see it('very deep conflict')
     */
    public function testVeryDeepConflict() : void
    {
        $this->expectFailsRule(
            new OverlappingFieldsCanBeMerged(),
            '
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
        ',
            [
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                        'field',
                        [['deepField', [['x', 'a and b are different fields']]]]
                    ),
                    [
                        new SourceLocation(3, 9),
                        new SourceLocation(4, 11),
                        new SourceLocation(5, 13),
                        new SourceLocation(8, 9),
                        new SourceLocation(9, 11),
                        new SourceLocation(10, 13),
                    ]
                ),
            ]
        );
    }

    /**
     * @see it('reports deep conflict to nearest common ancestor')
     */
    public function testReportsDeepConflictToNearestCommonAncestor() : void
    {
        $this->expectFailsRule(
            new OverlappingFieldsCanBeMerged(),
            '
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
        ',
            [
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                        'deepField',
                        [['x', 'a and b are different fields']]
                    ),
                    [
                        new SourceLocation(4, 11),
                        new SourceLocation(5, 13),
                        new SourceLocation(7, 11),
                        new SourceLocation(8, 13),
                    ]
                ),
            ]
        );
    }

    /**
     * @see it('reports deep conflict to nearest common ancestor in fragments')
     */
    public function testReportsDeepConflictToNearestCommonAncestorInFragments() : void
    {
        $this->expectFailsRule(
            new OverlappingFieldsCanBeMerged(),
            '
      {
        field {
          ...F
        }
        field {
          ...F
        }
      }
      fragment F on T {
        deepField {
          deeperField {
            x: a
          }
          deeperField {
            x: b
          }
        }
        deepField {
          deeperField {
            y
          }
        }
      }
        ',
            [
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                        'deeperField',
                        [['x', 'a and b are different fields']]
                    ),
                    [
                        new SourceLocation(12, 11),
                        new SourceLocation(13, 13),
                        new SourceLocation(15, 11),
                        new SourceLocation(16, 13),
                    ]
                ),
            ]
        );
    }

    /**
     * @see it('reports deep conflict in nested fragments')
     */
    public function testReportsDeepConflictInNestedFragments() : void
    {
        $this->expectFailsRule(
            new OverlappingFieldsCanBeMerged(),
            '
      {
        field {
          ...F
        }
        field {
          ...I
        }
      }
      fragment F on T {
        x: a
        ...G
      }
      fragment G on T {
        y: c
      }
      fragment I on T {
        y: d
        ...J
      }
      fragment J on T {
        x: b
      }
        ',
            [
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                        'field',
                        [
                            ['x', 'a and b are different fields'],
                            ['y', 'c and d are different fields'],
                        ]
                    ),
                    [
                        new SourceLocation(3, 9),
                        new SourceLocation(11, 9),
                        new SourceLocation(15, 9),
                        new SourceLocation(6, 9),
                        new SourceLocation(22, 9),
                        new SourceLocation(18, 9),
                    ]
                ),
            ]
        );
    }

    /**
     * @see it('ignores unknown fragments')
     */
    public function testIgnoresUnknownFragments() : void
    {
        $this->expectPassesRule(
            new OverlappingFieldsCanBeMerged(),
            '
      {
        field {
          ...Unknown
          ...Known
        }
      }
      fragment Known on T {
        field
        ...OtherUnknown
      }
        '
        );
    }

    // Describe: return types must be unambiguous

    /**
     * @see it('conflicting return types which potentially overlap')
     */
    public function testConflictingReturnTypesWhichPotentiallyOverlap() : void
    {
        // This is invalid since an object could potentially be both the Object
        // type IntBox and the interface type NonNullStringBox1. While that
        // condition does not exist in the current schema, the schema could
        // expand in the future to allow this. Thus it is invalid.
        $this->expectFailsRuleWithSchema(
            $this->getSchema(),
            new OverlappingFieldsCanBeMerged(),
            '
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
        ',
            [
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                        'scalar',
                        'they return conflicting types Int and String!'
                    ),
                    [
                        new SourceLocation(5, 15),
                        new SourceLocation(8, 15),
                    ]
                ),
            ]
        );
    }

    private function getSchema()
    {
        $StringBox = null;
        $IntBox    = null;
        $SomeBox   = null;

        $SomeBox = new InterfaceType([
            'name'   => 'SomeBox',
            'fields' => function () use (&$SomeBox) {
                return [
                    'deepBox'        => ['type' => $SomeBox],
                    'unrelatedField' => ['type' => Type::string()],
                ];
            },
        ]);

        $StringBox = new ObjectType([
            'name'       => 'StringBox',
            'interfaces' => [$SomeBox],
            'fields'     => function () use (&$StringBox, &$IntBox) {
                return [
                    'scalar'         => ['type' => Type::string()],
                    'deepBox'        => ['type' => $StringBox],
                    'unrelatedField' => ['type' => Type::string()],
                    'listStringBox'  => ['type' => Type::listOf($StringBox)],
                    'stringBox'      => ['type' => $StringBox],
                    'intBox'         => ['type' => $IntBox],
                ];
            },
        ]);

        $IntBox = new ObjectType([
            'name'       => 'IntBox',
            'interfaces' => [$SomeBox],
            'fields'     => function () use (&$StringBox, &$IntBox) {
                return [
                    'scalar'         => ['type' => Type::int()],
                    'deepBox'        => ['type' => $IntBox],
                    'unrelatedField' => ['type' => Type::string()],
                    'listStringBox'  => ['type' => Type::listOf($StringBox)],
                    'stringBox'      => ['type' => $StringBox],
                    'intBox'         => ['type' => $IntBox],
                ];
            },
        ]);

        $NonNullStringBox1 = new InterfaceType([
            'name'   => 'NonNullStringBox1',
            'fields' => [
                'scalar' => ['type' => Type::nonNull(Type::string())],
            ],
        ]);

        $NonNullStringBox1Impl = new ObjectType([
            'name'       => 'NonNullStringBox1Impl',
            'interfaces' => [$SomeBox, $NonNullStringBox1],
            'fields'     => [
                'scalar'         => ['type' => Type::nonNull(Type::string())],
                'unrelatedField' => ['type' => Type::string()],
                'deepBox'        => ['type' => $SomeBox],
            ],
        ]);

        $NonNullStringBox2 = new InterfaceType([
            'name'   => 'NonNullStringBox2',
            'fields' => [
                'scalar' => ['type' => Type::nonNull(Type::string())],
            ],
        ]);

        $NonNullStringBox2Impl = new ObjectType([
            'name'       => 'NonNullStringBox2Impl',
            'interfaces' => [$SomeBox, $NonNullStringBox2],
            'fields'     => [
                'scalar'         => ['type' => Type::nonNull(Type::string())],
                'unrelatedField' => ['type' => Type::string()],
                'deepBox'        => ['type' => $SomeBox],
            ],
        ]);

        $Connection = new ObjectType([
            'name'   => 'Connection',
            'fields' => [
                'edges' => [
                    'type' => Type::listOf(new ObjectType([
                        'name'   => 'Edge',
                        'fields' => [
                            'node' => [
                                'type' => new ObjectType([
                                    'name'   => 'Node',
                                    'fields' => [
                                        'id'   => ['type' => Type::id()],
                                        'name' => ['type' => Type::string()],
                                    ],
                                ]),
                            ],
                        ],
                    ])),
                ],
            ],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'QueryRoot',
                'fields' => [
                    'someBox'    => ['type' => $SomeBox],
                    'connection' => ['type' => $Connection],
                ],
            ]),
            'types' => [$IntBox, $StringBox, $NonNullStringBox1Impl, $NonNullStringBox2Impl],
        ]);

        return $schema;
    }

    /**
     * @see it('compatible return shapes on different return types')
     */
    public function testCompatibleReturnShapesOnDifferentReturnTypes() : void
    {
        // In this case `deepBox` returns `SomeBox` in the first usage, and
        // `StringBox` in the second usage. These return types are not the same!
        // however this is valid because the return *shapes* are compatible.
        $this->expectPassesRuleWithSchema(
            $this->getSchema(),
            new OverlappingFieldsCanBeMerged(),
            '
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
        '
        );
    }

    /**
     * @see it('disallows differing return types despite no overlap')
     */
    public function testDisallowsDifferingReturnTypesDespiteNoOverlap() : void
    {
        $this->expectFailsRuleWithSchema(
            $this->getSchema(),
            new OverlappingFieldsCanBeMerged(),
            '
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
        ',
            [
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                        'scalar',
                        'they return conflicting types Int and String'
                    ),
                    [
                        new SourceLocation(5, 15),
                        new SourceLocation(8, 15),
                    ]
                ),
            ]
        );
    }

    /**
     * @see it('reports correctly when a non-exclusive follows an exclusive')
     */
    public function testReportsCorrectlyWhenANonExclusiveFollowsAnExclusive() : void
    {
        $this->expectFailsRuleWithSchema(
            $this->getSchema(),
            new OverlappingFieldsCanBeMerged(),
            '
        {
          someBox {
            ... on IntBox {
              deepBox {
                ...X
              }
            }
          }
          someBox {
            ... on StringBox {
              deepBox {
                ...Y
              }
            }
          }
          memoed: someBox {
            ... on IntBox {
              deepBox {
                ...X
              }
            }
          }
          memoed: someBox {
            ... on StringBox {
              deepBox {
                ...Y
              }
            }
          }
          other: someBox {
            ...X
          }
          other: someBox {
            ...Y
          }
        }
        fragment X on SomeBox {
          scalar
        }
        fragment Y on SomeBox {
          scalar: unrelatedField
        }
        ',
            [
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                        'other',
                        [['scalar', 'scalar and unrelatedField are different fields']]
                    ),
                    [
                        new SourceLocation(31, 11),
                        new SourceLocation(39, 11),
                        new SourceLocation(34, 11),
                        new SourceLocation(42, 11),
                    ]
                ),
            ]
        );
    }

    /**
     * @see it('disallows differing return type nullability despite no overlap')
     */
    public function testDisallowsDifferingReturnTypeNullabilityDespiteNoOverlap() : void
    {
        $this->expectFailsRuleWithSchema(
            $this->getSchema(),
            new OverlappingFieldsCanBeMerged(),
            '
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
        ',
            [
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                        'scalar',
                        'they return conflicting types String! and String'
                    ),
                    [
                        new SourceLocation(5, 15),
                        new SourceLocation(8, 15),
                    ]
                ),
            ]
        );
    }

    /**
     * @see it('disallows differing return type list despite no overlap')
     */
    public function testDisallowsDifferingReturnTypeListDespiteNoOverlap() : void
    {
        $this->expectFailsRuleWithSchema(
            $this->getSchema(),
            new OverlappingFieldsCanBeMerged(),
            '
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
        ',
            [
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                        'box',
                        'they return conflicting types [StringBox] and StringBox'
                    ),
                    [
                        new SourceLocation(5, 15),
                        new SourceLocation(10, 15),
                    ]
                ),
            ]
        );

        $this->expectFailsRuleWithSchema(
            $this->getSchema(),
            new OverlappingFieldsCanBeMerged(),
            '
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
        ',
            [
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                        'box',
                        'they return conflicting types StringBox and [StringBox]'
                    ),
                    [
                        new SourceLocation(5, 15),
                        new SourceLocation(10, 15),
                    ]
                ),
            ]
        );
    }

    public function testDisallowsDifferingSubfields() : void
    {
        $this->expectFailsRuleWithSchema(
            $this->getSchema(),
            new OverlappingFieldsCanBeMerged(),
            '
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
        ',
            [

                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                        'val',
                        'scalar and unrelatedField are different fields'
                    ),
                    [
                        new SourceLocation(6, 17),
                        new SourceLocation(7, 17),
                    ]
                ),
            ]
        );
    }

    /**
     * @see it('disallows differing deep return types despite no overlap')
     */
    public function testDisallowsDifferingDeepReturnTypesDespiteNoOverlap() : void
    {
        $this->expectFailsRuleWithSchema(
            $this->getSchema(),
            new OverlappingFieldsCanBeMerged(),
            '
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
        ',
            [
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                        'box',
                        [['scalar', 'they return conflicting types String and Int']]
                    ),
                    [
                        new SourceLocation(5, 15),
                        new SourceLocation(6, 17),
                        new SourceLocation(10, 15),
                        new SourceLocation(11, 17),
                    ]
                ),
            ]
        );
    }

    /**
     * @see it('allows non-conflicting overlaping types')
     */
    public function testAllowsNonConflictingOverlapingTypes() : void
    {
        $this->expectPassesRuleWithSchema(
            $this->getSchema(),
            new OverlappingFieldsCanBeMerged(),
            '
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
        '
        );
    }

    /**
     * @see it('same wrapped scalar return types')
     */
    public function testSameWrappedScalarReturnTypes() : void
    {
        $this->expectPassesRuleWithSchema(
            $this->getSchema(),
            new OverlappingFieldsCanBeMerged(),
            '
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
        '
        );
    }

    /**
     * @see it('allows inline typeless fragments')
     */
    public function testAllowsInlineTypelessFragments() : void
    {
        $this->expectPassesRuleWithSchema(
            $this->getSchema(),
            new OverlappingFieldsCanBeMerged(),
            '
        {
          a
          ... {
            a
          }
        }
        '
        );
    }

    /**
     * @see it('compares deep types including list')
     */
    public function testComparesDeepTypesIncludingList() : void
    {
        $this->expectFailsRuleWithSchema(
            $this->getSchema(),
            new OverlappingFieldsCanBeMerged(),
            '
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
      ',
            [
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                        'edges',
                        [['node', [['id', 'name and id are different fields']]]]
                    ),
                    [
                        new SourceLocation(5, 13),
                        new SourceLocation(6, 15),
                        new SourceLocation(7, 17),
                        new SourceLocation(14, 11),
                        new SourceLocation(15, 13),
                        new SourceLocation(16, 15),
                    ]
                ),
            ]
        );
    }

    /**
     * @see it('ignores unknown types')
     */
    public function testIgnoresUnknownTypes() : void
    {
        $this->expectPassesRuleWithSchema(
            $this->getSchema(),
            new OverlappingFieldsCanBeMerged(),
            '
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
        '
        );
    }

    /**
     * @see it('error message contains hint for alias conflict')
     */
    public function testErrorMessageContainsHintForAliasConflict() : void
    {
        // The error template should end with a hint for the user to try using
        // different aliases.
        $error = OverlappingFieldsCanBeMerged::fieldsConflictMessage('x', 'a and b are different fields');
        $hint  = 'Use different aliases on the fields to fetch both if this was intentional.';

        self::assertStringEndsWith($hint, $error);
    }

    /**
     * @see it('does not infinite loop on recursive fragment')
     */
    public function testDoesNotInfiniteLoopOnRecursiveFragment() : void
    {
        $this->expectPassesRule(
            new OverlappingFieldsCanBeMerged(),
            '
        fragment fragA on Human { name, relatives { name, ...fragA } }
        '
        );
    }

    /**
     * @see it('does not infinite loop on immediately recursive fragment')
     */
    public function testDoesNotInfiniteLoopOnImmeditelyRecursiveFragment() : void
    {
        $this->expectPassesRule(
            new OverlappingFieldsCanBeMerged(),
            '
        fragment fragA on Human { name, ...fragA }
        '
        );
    }

    /**
     * @see it('does not infinite loop on transitively recursive fragment')
     */
    public function testDoesNotInfiniteLoopOnTransitivelyRecursiveFragment() : void
    {
        $this->expectPassesRule(
            new OverlappingFieldsCanBeMerged(),
            '
        fragment fragA on Human { name, ...fragB }
        fragment fragB on Human { name, ...fragC }
        fragment fragC on Human { name, ...fragA }
        '
        );
    }

    /**
     * @see it('find invalid case even with immediately recursive fragment')
     */
    public function testFindInvalidCaseEvenWithImmediatelyRecursiveFragment() : void
    {
        $this->expectFailsRule(
            new OverlappingFieldsCanBeMerged(),
            '
      fragment sameAliasesWithDifferentFieldTargets on Dob {
        ...sameAliasesWithDifferentFieldTargets
        fido: name
        fido: nickname
      }
        ',
            [
                FormattedError::create(
                    OverlappingFieldsCanBeMerged::fieldsConflictMessage(
                        'fido',
                        'name and nickname are different fields'
                    ),
                    [
                        new SourceLocation(4, 9),
                        new SourceLocation(5, 9),
                    ]
                ),
            ]
        );
    }
}
