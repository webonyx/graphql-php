<?php
namespace GraphQL\Tests\Executor;

require_once __DIR__ . '/TestClasses.php';

use GraphQL\Deferred;
use GraphQL\Error\Error;
use GraphQL\Error\UserError;
use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Schema;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class ExecutorTest extends \PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        Executor::setPromiseAdapter(null);
    }

    // Execute: Handles basic execution tasks

    /**
     * @it executes arbitrary code
     */
    public function testExecutesArbitraryCode()
    {
        $deepData = null;
        $data = null;

        $promiseData = function () use (&$data) {
            return new Deferred(function () use (&$data) {
                return $data;
            });
        };

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
            'promise' => function() use ($promiseData) {
                return $promiseData();
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
            'deeper' => function () use (&$data) {
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

        Executor::execute($schema, $ast, $rootValue, null, [ 'var' => '123' ]);

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
      sync
      syncError
      syncRawError
      syncReturnError
      syncReturnErrorList
      async
      asyncReject
      asyncRawReject
      asyncEmptyReject
      asyncError
      asyncRawError
      asyncReturnError
        }';

        $data = [
            'sync' => function () {
                return 'sync';
            },
            'syncError' => function () {
                throw new UserError('Error getting syncError');
            },
            'syncRawError' => function() {
                throw new UserError('Error getting syncRawError');
            },
            // inherited from JS reference implementation, but make no sense in this PHP impl
            // leaving it just to simplify migrations from newer js versions
            'syncReturnError' => function() {
                return new UserError('Error getting syncReturnError');
            },
            'syncReturnErrorList' => function () {
                return [
                    'sync0',
                    new UserError('Error getting syncReturnErrorList1'),
                    'sync2',
                    new UserError('Error getting syncReturnErrorList3')
                ];
            },
            'async' => function() {
                return new Deferred(function() { return 'async'; });
            },
            'asyncReject' => function() {
                return new Deferred(function() { throw new UserError('Error getting asyncReject'); });
            },
            'asyncRawReject' => function () {
                return new Deferred(function() {
                    throw new UserError('Error getting asyncRawReject');
                });
            },
            'asyncEmptyReject' => function () {
                return new Deferred(function() {
                    throw new UserError();
                });
            },
            'asyncError' => function() {
                return new Deferred(function() {
                    throw new UserError('Error getting asyncError');
                });
            },
            // inherited from JS reference implementation, but make no sense in this PHP impl
            // leaving it just to simplify migrations from newer js versions
            'asyncRawError' => function() {
                return new Deferred(function() {
                    throw new UserError('Error getting asyncRawError');
                });
            },
            'asyncReturnError' => function() {
                return new Deferred(function() {
                    throw new UserError('Error getting asyncReturnError');
                });
            },
        ];

        $docAst = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'sync' => ['type' => Type::string()],
                    'syncError' => ['type' => Type::string()],
                    'syncRawError' => ['type' => Type::string()],
                    'syncReturnError' => ['type' => Type::string()],
                    'syncReturnErrorList' => ['type' => Type::listOf(Type::string())],
                    'async' => ['type' => Type::string()],
                    'asyncReject' => ['type' => Type::string() ],
                    'asyncRawReject' => ['type' => Type::string() ],
                    'asyncEmptyReject' => ['type' => Type::string() ],
                    'asyncError' => ['type' => Type::string()],
                    'asyncRawError' => ['type' => Type::string()],
                    'asyncReturnError' => ['type' => Type::string()],
                ]
            ])
        ]);

        $expected = [
            'data' => [
                'sync' => 'sync',
                'syncError' => null,
                'syncRawError' => null,
                'syncReturnError' => null,
                'syncReturnErrorList' => ['sync0', null, 'sync2', null],
                'async' => 'async',
                'asyncReject' => null,
                'asyncRawReject' => null,
                'asyncEmptyReject' => null,
                'asyncError' => null,
                'asyncRawError' => null,
                'asyncReturnError' => null,
            ],
            'errors' => [
                [
                    'message' => 'Error getting syncError',
                    'locations' => [['line' => 3, 'column' => 7]],
                    'path' => ['syncError']
                ],
                [
                    'message' => 'Error getting syncRawError',
                    'locations' => [ [ 'line' => 4, 'column' => 7 ] ],
                    'path'=> [ 'syncRawError' ]
                ],
                [
                    'message' => 'Error getting syncReturnError',
                    'locations' => [['line' => 5, 'column' => 7]],
                    'path' => ['syncReturnError']
                ],
                [
                    'message' => 'Error getting syncReturnErrorList1',
                    'locations' => [['line' => 6, 'column' => 7]],
                    'path' => ['syncReturnErrorList', 1]
                ],
                [
                    'message' => 'Error getting syncReturnErrorList3',
                    'locations' => [['line' => 6, 'column' => 7]],
                    'path' => ['syncReturnErrorList', 3]
                ],
                [
                    'message' => 'Error getting asyncReject',
                    'locations' => [['line' => 8, 'column' => 7]],
                    'path' => ['asyncReject']
                ],
                [
                    'message' => 'Error getting asyncRawReject',
                    'locations' => [['line' => 9, 'column' => 7]],
                    'path' => ['asyncRawReject']
                ],
                [
                    'message' => 'An unknown error occurred.',
                    'locations' => [['line' => 10, 'column' => 7]],
                    'path' => ['asyncEmptyReject']
                ],
                [
                    'message' => 'Error getting asyncError',
                    'locations' => [['line' => 11, 'column' => 7]],
                    'path' => ['asyncError']
                ],
                [
                    'message' => 'Error getting asyncRawError',
                    'locations' => [ [ 'line' => 12, 'column' => 7 ] ],
                    'path' => [ 'asyncRawError' ]
                ],
                [
                    'message' => 'Error getting asyncReturnError',
                    'locations' => [['line' => 13, 'column' => 7]],
                    'path' => ['asyncReturnError']
                ],
            ]
        ];

        $result = Executor::execute($schema, $docAst, $data)->toArray();

        $this->assertArraySubset($expected, $result);
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
     * @it provides error if no operation is provided
     */
    public function testProvidesErrorIfNoOperationIsProvided()
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

        $result = Executor::execute($schema, $ast, $data);
        $expected = [
            'errors' => [
                [
                    'message' => 'Must provide an operation.',
                ]
            ]
        ];

        $this->assertArraySubset($expected, $result->toArray());
    }

    /**
     * @it errors if no op name is provided with multiple operations
     */
    public function testErrorsIfNoOperationIsProvidedWithMultipleOperations()
    {
        $doc = 'query Example { a } query OtherExample { a }';
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

        $result = Executor::execute($schema, $ast, $data);

        $expected = [
            'errors' => [
                [
                    'message' => 'Must provide operation name if query contains multiple operations.',
                ]
            ]
        ];

        $this->assertArraySubset($expected, $result->toArray());
    }

    /**
     * @it errors if unknown operation name is provided
     */
    public function testErrorsIfUnknownOperationNameIsProvided()
    {
        $doc = 'query Example { a } query OtherExample { a }';
        $ast = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Type',
                'fields' => [
                    'a' => ['type' => Type::string()],
                ]
            ])
        ]);


        $result = Executor::execute(
            $schema,
            $ast,
            null,
            null,
            null,
            'UnknownExample'
        );

        $expected = [
            'errors' => [
                [
                    'message' => 'Unknown operation named "UnknownExample".',
                ]
            ]

        ];

        $this->assertArraySubset($expected, $result->toArray());
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

    public function testCorrectFieldOrderingDespiteExecutionOrder()
    {
        $doc = '{
      a,
      b,
      c,
      d,
      e
    }';
        $data = [
            'a' => function () {
                return 'a';
            },
            'b' => function () {
                return new Deferred(function () { return 'b'; });
            },
            'c' => function () {
                return 'c';
            },
            'd' => function () {
                return new Deferred(function () { return 'd'; });
            },
            'e' => function () {
                return 'e';
            },
        ];

        $ast = Parser::parse($doc);

        $queryType = new ObjectType([
            'name' => 'DeepDataType',
            'fields' => [
                'a' => [ 'type' => Type::string() ],
                'b' => [ 'type' => Type::string() ],
                'c' => [ 'type' => Type::string() ],
                'd' => [ 'type' => Type::string() ],
                'e' => [ 'type' => Type::string() ],
            ]
        ]);
        $schema = new Schema(['query' => $queryType]);

        $expected = [
            'data' => [
                'a' => 'a',
                'b' => 'b',
                'c' => 'c',
                'd' => 'd',
                'e' => 'e',
            ]
        ];

        $this->assertEquals($expected, Executor::execute($schema, $ast, $data)->toArray());
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
        $this->assertEquals([
            'message' => 'Expected value of type "SpecialType" but got: instance of GraphQL\Tests\Executor\NotSpecial.',
            'locations' => [['line' => 1, 'column' => 3]],
            'path' => ['specials', 1]
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
                    'foo' => ['type' => Type::string()]
                ]
            ])
        ]);


        $result = Executor::execute($schema, $query);

        $expected = [
            'errors' => [
                [
                    'message' => 'GraphQL cannot execute a request containing a ObjectTypeDefinition.',
                    'locations' => [['line' => 4, 'column' => 7]],
                ]
            ]
        ];

        $this->assertArraySubset($expected, $result->toArray());
    }

    /**
     * @it uses a custom field resolver
     */
    public function testUsesACustomFieldResolver()
    {
        $query = Parser::parse('{ foo }');

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'foo' => ['type' => Type::string()]
                ]
            ])
        ]);

        // For the purposes of test, just return the name of the field!
        $customResolver = function ($source, $args, $context, ResolveInfo $info) {
            return $info->fieldName;
        };

        $result = Executor::execute(
            $schema,
            $query,
            null,
            null,
            null,
            null,
            $customResolver
        );

        $expected = [
            'data' => ['foo' => 'foo']
        ];

        $this->assertEquals($expected, $result->toArray());
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
