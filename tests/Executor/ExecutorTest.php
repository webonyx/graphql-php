<?php
namespace GraphQL\Tests\Executor;

require_once __DIR__ . '/TestClasses.php';

use GraphQL\Error\Error;
use GraphQL\Executor\Executor;
use GraphQL\Error\FormattedError;
use GraphQL\Language\Parser;
use GraphQL\Language\SourceLocation;
use GraphQL\Schema;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Utils;

class ExecutorTest extends \PHPUnit_Framework_TestCase
{
    // Execute: Handles basic execution tasks

    /**
     * @it executes arbitrary code
     */
    public function testExecutesArbitraryCode()
    {
        $deepData = null;
        $data = [
            'a' => function () { return 'Apple';},
            'b' => function () {return 'Banana';},
            'c' => function () {return 'Cookie';},
            'd' => function () {return 'Donut';},
            'e' => function () {return 'Egg';},
            'f' => 'Fish',
            'pic' => function ($size = 50) {
                return 'Pic of size: ' . $size;
            },
            'promise' => function() use (&$data) {
                return $data;
            },
            'deep' => function () use (&$deepData) {
                return $deepData;
            }
        ];

        $deepData = [
            'a' => function () { return 'Already Been Done'; },
            'b' => function () { return 'Boring'; },
            'c' => function () {
                return ['Contrived', null, 'Confusing'];
            },
            'deeper' => function () use ($data) {
                return [$data, null, $data];
            }
        ];


        $doc = '
      query Example($size: Int) {
        a,
        b,
        x: c
        ...c
        f
        ...on DataType {
          pic(size: $size)
          promise {
            a
          }
        }
        deep {
          a
          b
          c
          deeper {
            a
            b
          }
        }
      }

      fragment c on DataType {
        d
        e
      }
    ';

        $ast = Parser::parse($doc);
        $expected = [
            'data' => [
                'a' => 'Apple',
                'b' => 'Banana',
                'x' => 'Cookie',
                'd' => 'Donut',
                'e' => 'Egg',
                'f' => 'Fish',
                'pic' => 'Pic of size: 100',
                'promise' => [
                    'a' => 'Apple'
                ],
                'deep' => [
                    'a' => 'Already Been Done',
                    'b' => 'Boring',
                    'c' => [ 'Contrived', null, 'Confusing' ],
                    'deeper' => [
                        [ 'a' => 'Apple', 'b' => 'Banana' ],
                        null,
                        [ 'a' => 'Apple', 'b' => 'Banana' ]
                    ]
                ]
            ]
        ];

        $deepDataType = null;
        $dataType = new ObjectType([
            'name' => 'DataType',
            'fields' => function() use (&$dataType, &$deepDataType) {
                return [
                    'a' => [ 'type' => Type::string() ],
                    'b' => [ 'type' => Type::string() ],
                    'c' => [ 'type' => Type::string() ],
                    'd' => [ 'type' => Type::string() ],
                    'e' => [ 'type' => Type::string() ],
                    'f' => [ 'type' => Type::string() ],
                    'pic' => [
                        'args' => [ 'size' => ['type' => Type::int() ] ],
                        'type' => Type::string(),
                        'resolve' => function($obj, $args) {
                            return $obj['pic']($args['size']);
                        }
                    ],
                    'promise' => ['type' => $dataType],
                    'deep' => ['type' => $deepDataType],
                ];
            }
        ]);

        $deepDataType = new ObjectType([
            'name' => 'DeepDataType',
            'fields' => [
                'a' => [ 'type' => Type::string() ],
                'b' => [ 'type' => Type::string() ],
                'c' => [ 'type' => Type::listOf(Type::string()) ],
                'deeper' => [ 'type' => Type::listOf($dataType) ]
            ]
        ]);
        $schema = new Schema(['query' => $dataType]);

        $this->assertEquals($expected, Executor::execute($schema, $ast, $data, null, ['size' => 100], 'Example')->toArray());
    }

    /**
     * @it merges parallel fragments
     */
    public function testMergesParallelFragments()
    {
        $ast = Parser::parse('
      { a, ...FragOne, ...FragTwo }

      fragment FragOne on Type {
        b
        deep { b, deeper: deep { b } }
      }

      fragment FragTwo on Type {
        c
        deep { c, deeper: deep { c } }
      }
        ');

        $Type = new ObjectType([
            'name' => 'Type',
            'fields' => function() use (&$Type) {
                return [
                    'a' => ['type' => Type::string(), 'resolve' => function () {
                        return 'Apple';
                    }],
                    'b' => ['type' => Type::string(), 'resolve' => function () {
                        return 'Banana';
                    }],
                    'c' => ['type' => Type::string(), 'resolve' => function () {
                        return 'Cherry';
                    }],
                    'deep' => [
                        'type' => $Type,
                        'resolve' => function () {
                            return [];
                        }
                    ]
                ];
            }
        ]);
        $schema = new Schema(['query' => $Type]);
        $expected = [
            'data' => [
                'a' => 'Apple',
                'b' => 'Banana',
                'c' => 'Cherry',
                'deep' => [
                    'b' => 'Banana',
                    'c' => 'Cherry',
                    'deeper' => [
                        'b' => 'Banana',
                        'c' => 'Cherry'
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, Executor::execute($schema, $ast)->toArray());
    }

    /**
     * @it provides info about current execution state
     */
    public function testProvidesInfoAboutCurrentExecutionState()
    {
        $ast = Parser::parse('query ($var: String) { result: test }');

        /** @var ResolveInfo $info */
        $info = null;
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Test',
                'fields' => [
                    'test' => [
                        'type' => Type::string(),
                        'resolve' => function($val, $args, $ctx, $_info) use (&$info) {
                            $info = $_info;
                        }
                    ]
                ]
            ])
        ]);

        $rootValue = [ 'root' => 'val' ];

        Executor::execute($schema, $ast, $rootValue, null, [ 'var' => 123 ]);

        $this->assertEquals([
            'fieldName',
            'fieldASTs',
            'fieldNodes',
            'returnType',
            'parentType',
            'path',
            'schema',
            'fragments',
            'rootValue',
            'operation',
            'variableValues',
        ], array_keys((array) $info));

        $this->assertEquals('test', $info->fieldName);
        $this->assertEquals(1, count($info->fieldNodes));
        $this->assertSame($ast->definitions[0]->selectionSet->selections[0], $info->fieldNodes[0]);
        $this->assertSame(Type::string(), $info->returnType);
        $this->assertSame($schema->getQueryType(), $info->parentType);
        $this->assertEquals(['result'], $info->path);
        $this->assertSame($schema, $info->schema);
        $this->assertSame($rootValue, $info->rootValue);
        $this->assertEquals($ast->definitions[0], $info->operation);
        $this->assertEquals(['var' => '123'], $info->variableValues);
    }

    /**
     * @it threads root value context correctly
     */
    public function testThreadsContextCorrectly()
    {
        // threads context correctly
        $doc = 'query Example { a }';

        $gotHere = false;

        $data = [
            'contextThing' => 'thing',
        ];

        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'a' => [
                        'type' => Type::string(),
                        'resolve' => function ($context) use ($doc, &$gotHere) {
                            $this->assertEquals('thing', $context['contextThing']);
                            $gotHere = true;
                        }
                    ]
                ]
            ])
        ]);

        Executor::execute($schema, $ast, $data, null, [], 'Example');
        $this->assertEquals(true, $gotHere);
    }

    /**
     * @it correctly threads arguments
     */
    public function testCorrectlyThreadsArguments()
    {
        $doc = '
      query Example {
        b(numArg: 123, stringArg: "foo")
      }
        ';

        $gotHere = false;

        $docAst = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'b' => [
                        'args' => [
                            'numArg' => ['type' => Type::int()],
                            'stringArg' => ['type' => Type::string()]
                        ],
                        'type' => Type::string(),
                        'resolve' => function ($_, $args) use (&$gotHere) {
                            $this->assertEquals(123, $args['numArg']);
                            $this->assertEquals('foo', $args['stringArg']);
                            $gotHere = true;
                        }
                    ]
                ]
            ])
        ]);
        Executor::execute($schema, $docAst, null, null, [], 'Example');
        $this->assertSame($gotHere, true);
    }

    /**
     * @it nulls out error subtrees
     */
    public function testNullsOutErrorSubtrees()
    {
        $doc = '{
      sync,
      syncError,
      syncRawError,
      async,
      asyncReject,
      asyncError
        }';

        $data = [
            'sync' => function () {
                return 'sync';
            },
            'syncError' => function () {
                throw new \Exception('Error getting syncError');
            },
            'syncRawError' => function() {
                throw new \Exception('Error getting syncRawError');
            },
            // Following are inherited from JS reference implementation, but make no sense in this PHP impl
            // leaving them just to simplify migrations from newer js versions
            'async' => function() {
                return 'async';
            },
            'asyncReject' => function() {
                throw new \Exception('Error getting asyncReject');
            },
            'asyncError' => function() {
                throw new \Exception('Error getting asyncError');
            }
        ];

        $docAst = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'sync' => ['type' => Type::string()],
                    'syncError' => ['type' => Type::string()],
                    'syncRawError' => [ 'type' => Type::string() ],
                    'async' => ['type' => Type::string()],
                    'asyncReject' => ['type' => Type::string() ],
                    'asyncError' => ['type' => Type::string()],
                ]
            ])
        ]);

        $expected = [
            'data' => [
                'sync' => 'sync',
                'syncError' => null,
                'syncRawError' => null,
                'async' => 'async',
                'asyncReject' => null,
                'asyncError' => null,
            ],
            'errors' => [
                FormattedError::create('Error getting syncError', [new SourceLocation(3, 7)]),
                FormattedError::create('Error getting syncRawError', [new SourceLocation(4, 7)]),
                FormattedError::create('Error getting asyncReject', [new SourceLocation(6, 7)]),
                FormattedError::create('Error getting asyncError', [new SourceLocation(7, 7)])
            ]
        ];

        $result = Executor::execute($schema, $docAst, $data);

        $this->assertArraySubset($expected, $result->toArray());
    }

    /**
     * @it uses the inline operation if no operation name is provided
     */
    public function testUsesTheInlineOperationIfNoOperationIsProvided()
    {
        $doc = '{ a }';
        $data = ['a' => 'b'];
        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'a' => ['type' => Type::string()],
                ]
            ])
        ]);

        $ex = Executor::execute($schema, $ast, $data);

        $this->assertEquals(['data' => ['a' => 'b']], $ex->toArray());
    }

    /**
     * @it uses the only operation if no operation name is provided
     */
    public function testUsesTheOnlyOperationIfNoOperationIsProvided()
    {
        $doc = 'query Example { a }';
        $data = [ 'a' => 'b' ];
        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'a' => [ 'type' => Type::string() ],
                ]
            ])
        ]);

        $ex = Executor::execute($schema, $ast, $data);
        $this->assertEquals(['data' => ['a' => 'b']], $ex->toArray());
    }

    /**
     * @it uses the named operation if operation name is provided
     */
    public function testUsesTheNamedOperationIfOperationNameIsProvided()
    {
        $doc = 'query Example { first: a } query OtherExample { second: a }';
        $data = [ 'a' => 'b' ];
        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'a' => [ 'type' => Type::string() ],
                ]
            ])
        ]);

        $result = Executor::execute($schema, $ast, $data, null, null, 'OtherExample');
        $this->assertEquals(['data' => ['second' => 'b']], $result->toArray());
    }

    /**
     * @it throws if no operation is provided
     */
    public function testThrowsIfNoOperationIsProvided()
    {
        $doc = 'fragment Example on Type { a }';
        $data = [ 'a' => 'b' ];
        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'a' => [ 'type' => Type::string() ],
                ]
            ])
        ]);

        try {
            Executor::execute($schema, $ast, $data);
            $this->fail('Expected exception was not thrown');
        } catch (Error $e) {
            $this->assertEquals('Must provide an operation.', $e->getMessage());
        }
    }

    /**
     * @it throws if no operation name is provided with multiple operations
     */
    public function testThrowsIfNoOperationIsProvidedWithMultipleOperations()
    {
        $doc = 'query Example { a } query OtherExample { a }';
        $data = [ 'a' => 'b' ];
        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'a' => [ 'type' => Type::string() ],
                ]
            ])
        ]);

        try {
            Executor::execute($schema, $ast, $data);
            $this->fail('Expected exception is not thrown');
        } catch (Error $err) {
            $this->assertEquals('Must provide operation name if query contains multiple operations.', $err->getMessage());
        }
    }

    /**
     * @it throws if unknown operation name is provided
     */
    public function testThrowsIfUnknownOperationNameIsProvided()
    {
        $doc = 'query Example { a } query OtherExample { a }';
        $data = [ 'a' => 'b' ];
        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'a' => [ 'type' => Type::string() ],
                ]
            ])
        ]);

        try {
            Executor::execute($schema, $ast, $data, null, null, 'UnknownExample');
            $this->fail('Expected exception was not thrown');
        } catch (Error $e) {
            $this->assertEquals('Unknown operation named "UnknownExample".', $e->getMessage());
        }
    }

    /**
     * @it uses the query schema for queries
     */
    public function testUsesTheQuerySchemaForQueries()
    {
        $doc = 'query Q { a } mutation M { c }';
        $data = ['a' => 'b', 'c' => 'd'];
        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Q',
                'fields' => [
                    'a' => ['type' => Type::string()],
                ]
            ]),
            'mutation' => new ObjectType([
                'name' => 'M',
                'fields' => [
                    'c' => ['type' => Type::string()],
                ]
            ])
        ]);

        $queryResult = Executor::execute($schema, $ast, $data, null, [], 'Q');
        $this->assertEquals(['data' => ['a' => 'b']], $queryResult->toArray());
    }

    /**
     * @it uses the mutation schema for mutations
     */
    public function testUsesTheMutationSchemaForMutations()
    {
        $doc = 'query Q { a } mutation M { c }';
        $data = [ 'a' => 'b', 'c' => 'd' ];
        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Q',
                'fields' => [
                    'a' => ['type' => Type::string()],
                ]
            ]),
            'mutation' => new ObjectType([
                'name' => 'M',
                'fields' => [
                    'c' => [ 'type' => Type::string() ],
                ]
            ])
        ]);
        $mutationResult = Executor::execute($schema, $ast, $data, null, [], 'M');
        $this->assertEquals(['data' => ['c' => 'd']], $mutationResult->toArray());
    }

    /**
     * @it uses the subscription schema for subscriptions
     */
    public function testUsesTheSubscriptionSchemaForSubscriptions()
    {
        $doc = 'query Q { a } subscription S { a }';
        $data = [ 'a' => 'b', 'c' => 'd' ];
        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Q',
                'fields' => [
                    'a' => [ 'type' => Type::string() ],
                ]
            ]),
            'subscription' => new ObjectType([
                'name' => 'S',
                'fields' => [
                    'a' => [ 'type' => Type::string() ],
                ]
            ])
        ]);

        $subscriptionResult = Executor::execute($schema, $ast, $data, null, [], 'S');
        $this->assertEquals(['data' => ['a' => 'b']], $subscriptionResult->toArray());
    }

    /**
     * @it Avoids recursion
     */
    public function testAvoidsRecursion()
    {
        $doc = '
      query Q {
        a
        ...Frag
        ...Frag
      }

      fragment Frag on DataType {
        a,
        ...Frag
      }
        ';
        $data = ['a' => 'b'];
        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'a' => ['type' => Type::string()],
                ]
            ])
        ]);

        $queryResult = Executor::execute($schema, $ast, $data, null, [], 'Q');
        $this->assertEquals(['data' => ['a' => 'b']], $queryResult->toArray());
    }

    /**
     * @it does not include illegal fields in output
     */
    public function testDoesNotIncludeIllegalFieldsInOutput()
    {
        $doc = 'mutation M {
      thisIsIllegalDontIncludeMe
    }';
        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Q',
                'fields' => [
                    'a' => ['type' => Type::string()],
                ]
            ]),
            'mutation' => new ObjectType([
                'name' => 'M',
                'fields' => [
                    'c' => ['type' => Type::string()],
                ]
            ])
        ]);
        $mutationResult = Executor::execute($schema, $ast);
        $this->assertEquals(['data' => []], $mutationResult->toArray());
    }

    /**
     * @it does not include arguments that were not set
     */
    public function testDoesNotIncludeArgumentsThatWereNotSet()
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'field' => [
                        'type' => Type::string(),
                        'resolve' => function($data, $args) {return $args ? json_encode($args) : '';},
                        'args' => [
                            'a' => ['type' => Type::boolean()],
                            'b' => ['type' => Type::boolean()],
                            'c' => ['type' => Type::boolean()],
                            'd' => ['type' => Type::int()],
                            'e' => ['type' => Type::int()]
                        ]
                    ]
                ]
            ])
        ]);

        $query = Parser::parse('{ field(a: true, c: false, e: 0) }');
        $result = Executor::execute($schema, $query);
        $expected = [
            'data' => [
                'field' => '{"a":true,"c":false,"e":0}'
            ]
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * @it fails when an isTypeOf check is not met
     */
    public function testFailsWhenAnIsTypeOfCheckIsNotMet()
    {
        $SpecialType = new ObjectType([
            'name' => 'SpecialType',
            'isTypeOf' => function($obj) {
                return $obj instanceof Special;
            },
            'fields' => [
                'value' => ['type' => Type::string()]
            ]
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'specials' => [
                        'type' => Type::listOf($SpecialType),
                        'resolve' => function($rootValue) {
                            return $rootValue['specials'];
                        }
                    ]
                ]
            ])
        ]);

        $query = Parser::parse('{ specials { value } }');
        $value = [
            'specials' => [ new Special('foo'), new NotSpecial('bar') ]
        ];
        $result = Executor::execute($schema, $query, $value);

        $this->assertEquals([
            'specials' => [
                ['value' => 'foo'],
                null
            ]
        ], $result->data);

        $this->assertEquals(1, count($result->errors));
        $this->assertArraySubset([
            'message' => 'Expected value of type SpecialType but got: GraphQL\Tests\Executor\NotSpecial',
            'locations' => [['line' => 1, 'column' => 3]]
        ], $result->errors[0]->toSerializableArray());
    }

    /**
     * @it fails to execute a query containing a type definition
     */
    public function testFailsToExecuteQueryContainingTypeDefinition()
    {
        $query = Parser::parse('
      { foo }

      type Query { foo: String }
    ');

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'foo' => ['type' => Type::string() ]
                ]
            ])
        ]);

        try {
            Executor::execute($schema, $query);
            $this->fail('Expected exception was not thrown');
        } catch (Error $e) {
            $this->assertEquals([
                'message' => 'GraphQL cannot execute a request containing a ObjectTypeDefinition.',
                'locations' => [['line' => 4, 'column' => 7]]
            ], $e->toSerializableArray());
        }
    }

    public function testSubstitutesArgumentWithDefaultValue()
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'field' => [
                        'type' => Type::string(),
                        'resolve' => function($data, $args) {return $args ? json_encode($args) : '';},
                        'args' => [
                            'a' => ['type' => Type::boolean(), 'defaultValue' => 1],
                            'b' => ['type' => Type::boolean(), 'defaultValue' => null],
                            'c' => ['type' => Type::boolean(), 'defaultValue' => 0],
                            'd' => ['type' => Type::int(), 'defaultValue' => false],
                            'e' => ['type' => Type::int(), 'defaultValue' => '0'],
                            'f' => ['type' => Type::int(), 'defaultValue' => 'some-string'],
                            'g' => ['type' => Type::boolean()],
                            'h' => ['type' => new InputObjectType([
                                'name' => 'ComplexType',
                                'fields' => [
                                    'a' => ['type' => Type::int()],
                                    'b' => ['type' => Type::string()]
                                ]
                            ]), 'defaultValue' => ['a' => 1, 'b' => 'test']]
                        ]
                    ]
                ]
            ])
        ]);

        $query = Parser::parse('{ field }');
        $result = Executor::execute($schema, $query);
        $expected = [
            'data' => [
                'field' => '{"a":1,"b":null,"c":0,"d":false,"e":"0","f":"some-string","h":{"a":1,"b":"test"}}'
            ]
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * @see https://github.com/webonyx/graphql-php/issues/59
     */
    public function testSerializesToEmptyObjectVsEmptyArray()
    {
        $iface = null;

        $a = new ObjectType([
            'name' => 'A',
            'fields' => [
                'id' => Type::id()
            ],
            'interfaces' => function() use (&$iface) {
                return [$iface];
            }
        ]);

        $b = new ObjectType([
            'name' => 'B',
            'fields' => [
                'id' => Type::id()
            ],
            'interfaces' => function() use (&$iface) {
                return [$iface];
            }
        ]);

        $iface = new InterfaceType([
            'name' => 'Iface',
            'fields' => [
                'id' => Type::id()
            ],
            'resolveType' => function($v) use ($a, $b) {
                return $v['type'] === 'A' ? $a : $b;
            }
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'ab' => Type::listOf($iface)
                ]
            ]),
            'types' => [$a, $b]
        ]);

        $data = [
            'ab' => [
                ['id' => 1, 'type' => 'A'],
                ['id' => 2, 'type' => 'A'],
                ['id' => 3, 'type' => 'B'],
                ['id' => 4, 'type' => 'B']
            ]
        ];

        $query = Parser::parse('
            {
                ab {
                    ... on A{
                        id
                    }
                }
            }
        ');

        $result = Executor::execute($schema, $query, $data, null);

        $this->assertEquals([
            'data' => [
                'ab' => [
                    ['id' => '1'],
                    ['id' => '2'],
                    new \stdClass(),
                    new \stdClass()
                ]
            ]
        ], $result->toArray());
    }
}
