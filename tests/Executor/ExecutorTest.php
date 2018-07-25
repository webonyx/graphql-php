<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use GraphQL\Deferred;
use GraphQL\Error\UserError;
use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Tests\Executor\TestClasses\NotSpecial;
use GraphQL\Tests\Executor\TestClasses\Special;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;
use stdClass;
use function array_keys;
use function count;
use function json_encode;

class ExecutorTest extends TestCase
{
    public function tearDown()
    {
        Executor::setPromiseAdapter(null);
    }

    // Execute: Handles basic execution tasks

    /**
     * @see it('executes arbitrary code')
     */
    public function testExecutesArbitraryCode() : void
    {
        $deepData = null;
        $data     = null;

        $promiseData = static function () use (&$data) {
            return new Deferred(static function () use (&$data) {
                return $data;
            });
        };

        $data = [
            'a'       => static function () {
                return 'Apple';
            },
            'b'       => static function () {
                return 'Banana';
            },
            'c'       => static function () {
                return 'Cookie';
            },
            'd'       => static function () {
                return 'Donut';
            },
            'e'       => static function () {
                return 'Egg';
            },
            'f'       => 'Fish',
            'pic'     => static function ($size = 50) {
                return 'Pic of size: ' . $size;
            },
            'promise' => static function () use ($promiseData) {
                return $promiseData();
            },
            'deep'    => static function () use (&$deepData) {
                return $deepData;
            },
        ];

        // Required for that & reference above
        $deepData = [
            'a'      => static function () {
                return 'Already Been Done';
            },
            'b'      => static function () {
                return 'Boring';
            },
            'c'      => static function () {
                return ['Contrived', null, 'Confusing'];
            },
            'deeper' => static function () use (&$data) {
                return [$data, null, $data];
            },
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

        $ast      = Parser::parse($doc);
        $expected = [
            'data' => [
                'a'       => 'Apple',
                'b'       => 'Banana',
                'x'       => 'Cookie',
                'd'       => 'Donut',
                'e'       => 'Egg',
                'f'       => 'Fish',
                'pic'     => 'Pic of size: 100',
                'promise' => ['a' => 'Apple'],
                'deep'    => [
                    'a'      => 'Already Been Done',
                    'b'      => 'Boring',
                    'c'      => ['Contrived', null, 'Confusing'],
                    'deeper' => [
                        ['a' => 'Apple', 'b' => 'Banana'],
                        null,
                        ['a' => 'Apple', 'b' => 'Banana'],
                    ],
                ],
            ],
        ];

        $deepDataType = null;
        $dataType     = new ObjectType([
            'name'   => 'DataType',
            'fields' => static function () use (&$dataType, &$deepDataType) {
                return [
                    'a'       => ['type' => Type::string()],
                    'b'       => ['type' => Type::string()],
                    'c'       => ['type' => Type::string()],
                    'd'       => ['type' => Type::string()],
                    'e'       => ['type' => Type::string()],
                    'f'       => ['type' => Type::string()],
                    'pic'     => [
                        'args'    => ['size' => ['type' => Type::int()]],
                        'type'    => Type::string(),
                        'resolve' => static function ($obj, $args) {
                            return $obj['pic']($args['size']);
                        },
                    ],
                    'promise' => ['type' => $dataType],
                    'deep'    => ['type' => $deepDataType],
                ];
            },
        ]);

        // Required for that & reference above
        $deepDataType = new ObjectType([
            'name'   => 'DeepDataType',
            'fields' => [
                'a'      => ['type' => Type::string()],
                'b'      => ['type' => Type::string()],
                'c'      => ['type' => Type::listOf(Type::string())],
                'deeper' => ['type' => Type::listOf($dataType)],
            ],
        ]);
        $schema       = new Schema(['query' => $dataType]);

        self::assertEquals(
            $expected,
            Executor::execute($schema, $ast, $data, null, ['size' => 100], 'Example')->toArray()
        );
    }

    /**
     * @see it('merges parallel fragments')
     */
    public function testMergesParallelFragments() : void
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
            'name'   => 'Type',
            'fields' => static function () use (&$Type) {
                return [
                    'a'    => [
                        'type'    => Type::string(),
                        'resolve' => static function () {
                            return 'Apple';
                        },
                    ],
                    'b'    => [
                        'type'    => Type::string(),
                        'resolve' => static function () {
                            return 'Banana';
                        },
                    ],
                    'c'    => [
                        'type'    => Type::string(),
                        'resolve' => static function () {
                            return 'Cherry';
                        },
                    ],
                    'deep' => [
                        'type'    => $Type,
                        'resolve' => static function () {
                            return [];
                        },
                    ],
                ];
            },
        ]);

        $schema   = new Schema(['query' => $Type]);
        $expected = [
            'data' => [
                'a'    => 'Apple',
                'b'    => 'Banana',
                'c'    => 'Cherry',
                'deep' => [
                    'b'      => 'Banana',
                    'c'      => 'Cherry',
                    'deeper' => [
                        'b' => 'Banana',
                        'c' => 'Cherry',
                    ],
                ],
            ],
        ];

        self::assertEquals($expected, Executor::execute($schema, $ast)->toArray());
    }

    /**
     * @see it('provides info about current execution state')
     */
    public function testProvidesInfoAboutCurrentExecutionState() : void
    {
        $ast = Parser::parse('query ($var: String) { result: test }');

        /** @var ResolveInfo $info */
        $info   = null;
        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Test',
                'fields' => [
                    'test' => [
                        'type'    => Type::string(),
                        'resolve' => static function ($val, $args, $ctx, $_info) use (&$info) {
                            $info = $_info;
                        },
                    ],
                ],
            ]),
        ]);

        $rootValue = ['root' => 'val'];

        Executor::execute($schema, $ast, $rootValue, null, ['var' => '123']);

        self::assertEquals(
            [
                'fieldName',
                'fieldNodes',
                'returnType',
                'parentType',
                'path',
                'schema',
                'fragments',
                'rootValue',
                'operation',
                'variableValues',
            ],
            array_keys((array) $info)
        );

        self::assertEquals('test', $info->fieldName);
        self::assertEquals(1, count($info->fieldNodes));
        self::assertSame($ast->definitions[0]->selectionSet->selections[0], $info->fieldNodes[0]);
        self::assertSame(Type::string(), $info->returnType);
        self::assertSame($schema->getQueryType(), $info->parentType);
        self::assertEquals(['result'], $info->path);
        self::assertSame($schema, $info->schema);
        self::assertSame($rootValue, $info->rootValue);
        self::assertEquals($ast->definitions[0], $info->operation);
        self::assertEquals(['var' => '123'], $info->variableValues);
    }

    /**
     * @see it('threads root value context correctly')
     */
    public function testThreadsContextCorrectly() : void
    {
        // threads context correctly
        $doc = 'query Example { a }';

        $gotHere = false;

        $data = ['contextThing' => 'thing'];

        $ast    = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Type',
                'fields' => [
                    'a' => [
                        'type'    => Type::string(),
                        'resolve' => static function ($context) use (&$gotHere) {
                            self::assertEquals('thing', $context['contextThing']);
                            $gotHere = true;
                        },
                    ],
                ],
            ]),
        ]);

        Executor::execute($schema, $ast, $data, null, [], 'Example');
        self::assertEquals(true, $gotHere);
    }

    /**
     * @see it('correctly threads arguments')
     */
    public function testCorrectlyThreadsArguments() : void
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
                'name'   => 'Type',
                'fields' => [
                    'b' => [
                        'args'    => [
                            'numArg'    => ['type' => Type::int()],
                            'stringArg' => ['type' => Type::string()],
                        ],
                        'type'    => Type::string(),
                        'resolve' => static function ($_, $args) use (&$gotHere) {
                            self::assertEquals(123, $args['numArg']);
                            self::assertEquals('foo', $args['stringArg']);
                            $gotHere = true;
                        },
                    ],
                ],
            ]),
        ]);
        Executor::execute($schema, $docAst, null, null, [], 'Example');
        self::assertSame($gotHere, true);
    }

    /**
     * @see it('nulls out error subtrees')
     */
    public function testNullsOutErrorSubtrees() : void
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
            'sync'                => static function () {
                return 'sync';
            },
            'syncError'           => static function () {
                throw new UserError('Error getting syncError');
            },
            'syncRawError'        => static function () {
                throw new UserError('Error getting syncRawError');
            },
            // inherited from JS reference implementation, but make no sense in this PHP impl
            // leaving it just to simplify migrations from newer js versions
            'syncReturnError'     => static function () {
                return new UserError('Error getting syncReturnError');
            },
            'syncReturnErrorList' => static function () {
                return [
                    'sync0',
                    new UserError('Error getting syncReturnErrorList1'),
                    'sync2',
                    new UserError('Error getting syncReturnErrorList3'),
                ];
            },
            'async'               => static function () {
                return new Deferred(static function () {
                    return 'async';
                });
            },
            'asyncReject'         => static function () {
                return new Deferred(static function () {
                    throw new UserError('Error getting asyncReject');
                });
            },
            'asyncRawReject'      => static function () {
                return new Deferred(static function () {
                    throw new UserError('Error getting asyncRawReject');
                });
            },
            'asyncEmptyReject'    => static function () {
                return new Deferred(static function () {
                    throw new UserError();
                });
            },
            'asyncError'          => static function () {
                return new Deferred(static function () {
                    throw new UserError('Error getting asyncError');
                });
            },
            // inherited from JS reference implementation, but make no sense in this PHP impl
            // leaving it just to simplify migrations from newer js versions
            'asyncRawError'       => static function () {
                return new Deferred(static function () {
                    throw new UserError('Error getting asyncRawError');
                });
            },
            'asyncReturnError'    => static function () {
                return new Deferred(static function () {
                    throw new UserError('Error getting asyncReturnError');
                });
            },
        ];

        $docAst = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Type',
                'fields' => [
                    'sync'                => ['type' => Type::string()],
                    'syncError'           => ['type' => Type::string()],
                    'syncRawError'        => ['type' => Type::string()],
                    'syncReturnError'     => ['type' => Type::string()],
                    'syncReturnErrorList' => ['type' => Type::listOf(Type::string())],
                    'async'               => ['type' => Type::string()],
                    'asyncReject'         => ['type' => Type::string()],
                    'asyncRawReject'      => ['type' => Type::string()],
                    'asyncEmptyReject'    => ['type' => Type::string()],
                    'asyncError'          => ['type' => Type::string()],
                    'asyncRawError'       => ['type' => Type::string()],
                    'asyncReturnError'    => ['type' => Type::string()],
                ],
            ]),
        ]);

        $expected = [
            'data'   => [
                'sync'                => 'sync',
                'syncError'           => null,
                'syncRawError'        => null,
                'syncReturnError'     => null,
                'syncReturnErrorList' => ['sync0', null, 'sync2', null],
                'async'               => 'async',
                'asyncReject'         => null,
                'asyncRawReject'      => null,
                'asyncEmptyReject'    => null,
                'asyncError'          => null,
                'asyncRawError'       => null,
                'asyncReturnError'    => null,
            ],
            'errors' => [
                [
                    'message'   => 'Error getting syncError',
                    'locations' => [['line' => 3, 'column' => 7]],
                    'path'      => ['syncError'],
                ],
                [
                    'message'   => 'Error getting syncRawError',
                    'locations' => [['line' => 4, 'column' => 7]],
                    'path'      => ['syncRawError'],
                ],
                [
                    'message'   => 'Error getting syncReturnError',
                    'locations' => [['line' => 5, 'column' => 7]],
                    'path'      => ['syncReturnError'],
                ],
                [
                    'message'   => 'Error getting syncReturnErrorList1',
                    'locations' => [['line' => 6, 'column' => 7]],
                    'path'      => ['syncReturnErrorList', 1],
                ],
                [
                    'message'   => 'Error getting syncReturnErrorList3',
                    'locations' => [['line' => 6, 'column' => 7]],
                    'path'      => ['syncReturnErrorList', 3],
                ],
                [
                    'message'   => 'Error getting asyncReject',
                    'locations' => [['line' => 8, 'column' => 7]],
                    'path'      => ['asyncReject'],
                ],
                [
                    'message'   => 'Error getting asyncRawReject',
                    'locations' => [['line' => 9, 'column' => 7]],
                    'path'      => ['asyncRawReject'],
                ],
                [
                    'message'   => 'An unknown error occurred.',
                    'locations' => [['line' => 10, 'column' => 7]],
                    'path'      => ['asyncEmptyReject'],
                ],
                [
                    'message'   => 'Error getting asyncError',
                    'locations' => [['line' => 11, 'column' => 7]],
                    'path'      => ['asyncError'],
                ],
                [
                    'message'   => 'Error getting asyncRawError',
                    'locations' => [['line' => 12, 'column' => 7]],
                    'path'      => ['asyncRawError'],
                ],
                [
                    'message'   => 'Error getting asyncReturnError',
                    'locations' => [['line' => 13, 'column' => 7]],
                    'path'      => ['asyncReturnError'],
                ],
            ],
        ];

        $result = Executor::execute($schema, $docAst, $data)->toArray();

        self::assertArraySubset($expected, $result);
    }

    /**
     * @see it('uses the inline operation if no operation name is provided')
     */
    public function testUsesTheInlineOperationIfNoOperationIsProvided() : void
    {
        $doc    = '{ a }';
        $data   = ['a' => 'b'];
        $ast    = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Type',
                'fields' => [
                    'a' => ['type' => Type::string()],
                ],
            ]),
        ]);

        $ex = Executor::execute($schema, $ast, $data);

        self::assertEquals(['data' => ['a' => 'b']], $ex->toArray());
    }

    /**
     * @see it('uses the only operation if no operation name is provided')
     */
    public function testUsesTheOnlyOperationIfNoOperationIsProvided() : void
    {
        $doc    = 'query Example { a }';
        $data   = ['a' => 'b'];
        $ast    = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Type',
                'fields' => [
                    'a' => ['type' => Type::string()],
                ],
            ]),
        ]);

        $ex = Executor::execute($schema, $ast, $data);
        self::assertEquals(['data' => ['a' => 'b']], $ex->toArray());
    }

    /**
     * @see it('uses the named operation if operation name is provided')
     */
    public function testUsesTheNamedOperationIfOperationNameIsProvided() : void
    {
        $doc    = 'query Example { first: a } query OtherExample { second: a }';
        $data   = ['a' => 'b'];
        $ast    = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Type',
                'fields' => [
                    'a' => ['type' => Type::string()],
                ],
            ]),
        ]);

        $result = Executor::execute($schema, $ast, $data, null, null, 'OtherExample');
        self::assertEquals(['data' => ['second' => 'b']], $result->toArray());
    }

    /**
     * @see it('provides error if no operation is provided')
     */
    public function testProvidesErrorIfNoOperationIsProvided() : void
    {
        $doc    = 'fragment Example on Type { a }';
        $data   = ['a' => 'b'];
        $ast    = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Type',
                'fields' => [
                    'a' => ['type' => Type::string()],
                ],
            ]),
        ]);

        $result   = Executor::execute($schema, $ast, $data);
        $expected = [
            'errors' => [
                ['message' => 'Must provide an operation.'],
            ],
        ];

        self::assertArraySubset($expected, $result->toArray());
    }

    /**
     * @see it('errors if no op name is provided with multiple operations')
     */
    public function testErrorsIfNoOperationIsProvidedWithMultipleOperations() : void
    {
        $doc    = 'query Example { a } query OtherExample { a }';
        $data   = ['a' => 'b'];
        $ast    = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Type',
                'fields' => [
                    'a' => ['type' => Type::string()],
                ],
            ]),
        ]);

        $result = Executor::execute($schema, $ast, $data);

        $expected = [
            'errors' => [
                ['message' => 'Must provide operation name if query contains multiple operations.'],
            ],
        ];

        self::assertArraySubset($expected, $result->toArray());
    }

    /**
     * @see it('errors if unknown operation name is provided')
     */
    public function testErrorsIfUnknownOperationNameIsProvided() : void
    {
        $doc    = 'query Example { a } query OtherExample { a }';
        $ast    = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Type',
                'fields' => [
                    'a' => ['type' => Type::string()],
                ],
            ]),
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
                ['message' => 'Unknown operation named "UnknownExample".'],
            ],

        ];

        self::assertArraySubset($expected, $result->toArray());
    }

    /**
     * @see it('uses the query schema for queries')
     */
    public function testUsesTheQuerySchemaForQueries() : void
    {
        $doc    = 'query Q { a } mutation M { c }';
        $data   = ['a' => 'b', 'c' => 'd'];
        $ast    = Parser::parse($doc);
        $schema = new Schema([
            'query'    => new ObjectType([
                'name'   => 'Q',
                'fields' => [
                    'a' => ['type' => Type::string()],
                ],
            ]),
            'mutation' => new ObjectType([
                'name'   => 'M',
                'fields' => [
                    'c' => ['type' => Type::string()],
                ],
            ]),
        ]);

        $queryResult = Executor::execute($schema, $ast, $data, null, [], 'Q');
        self::assertEquals(['data' => ['a' => 'b']], $queryResult->toArray());
    }

    /**
     * @see it('uses the mutation schema for mutations')
     */
    public function testUsesTheMutationSchemaForMutations() : void
    {
        $doc            = 'query Q { a } mutation M { c }';
        $data           = ['a' => 'b', 'c' => 'd'];
        $ast            = Parser::parse($doc);
        $schema         = new Schema([
            'query'    => new ObjectType([
                'name'   => 'Q',
                'fields' => [
                    'a' => ['type' => Type::string()],
                ],
            ]),
            'mutation' => new ObjectType([
                'name'   => 'M',
                'fields' => [
                    'c' => ['type' => Type::string()],
                ],
            ]),
        ]);
        $mutationResult = Executor::execute($schema, $ast, $data, null, [], 'M');
        self::assertEquals(['data' => ['c' => 'd']], $mutationResult->toArray());
    }

    /**
     * @see it('uses the subscription schema for subscriptions')
     */
    public function testUsesTheSubscriptionSchemaForSubscriptions() : void
    {
        $doc    = 'query Q { a } subscription S { a }';
        $data   = ['a' => 'b', 'c' => 'd'];
        $ast    = Parser::parse($doc);
        $schema = new Schema([
            'query'        => new ObjectType([
                'name'   => 'Q',
                'fields' => [
                    'a' => ['type' => Type::string()],
                ],
            ]),
            'subscription' => new ObjectType([
                'name'   => 'S',
                'fields' => [
                    'a' => ['type' => Type::string()],
                ],
            ]),
        ]);

        $subscriptionResult = Executor::execute($schema, $ast, $data, null, [], 'S');
        self::assertEquals(['data' => ['a' => 'b']], $subscriptionResult->toArray());
    }

    public function testCorrectFieldOrderingDespiteExecutionOrder() : void
    {
        $doc  = '{
      a,
      b,
      c,
      d,
      e
    }';
        $data = [
            'a' => static function () {
                return 'a';
            },
            'b' => static function () {
                return new Deferred(static function () {
                    return 'b';
                });
            },
            'c' => static function () {
                return 'c';
            },
            'd' => static function () {
                return new Deferred(static function () {
                    return 'd';
                });
            },
            'e' => static function () {
                return 'e';
            },
        ];

        $ast = Parser::parse($doc);

        $queryType = new ObjectType([
            'name'   => 'DeepDataType',
            'fields' => [
                'a' => ['type' => Type::string()],
                'b' => ['type' => Type::string()],
                'c' => ['type' => Type::string()],
                'd' => ['type' => Type::string()],
                'e' => ['type' => Type::string()],
            ],
        ]);
        $schema    = new Schema(['query' => $queryType]);

        $expected = [
            'data' => [
                'a' => 'a',
                'b' => 'b',
                'c' => 'c',
                'd' => 'd',
                'e' => 'e',
            ],
        ];

        self::assertEquals($expected, Executor::execute($schema, $ast, $data)->toArray());
    }

    /**
     * @see it('Avoids recursion')
     */
    public function testAvoidsRecursion() : void
    {
        $doc    = '
      query Q {
        a
        ...Frag
        ...Frag
      }

      fragment Frag on Type {
        a,
        ...Frag
      }
        ';
        $data   = ['a' => 'b'];
        $ast    = Parser::parse($doc);
        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Type',
                'fields' => [
                    'a' => ['type' => Type::string()],
                ],
            ]),
        ]);

        $queryResult = Executor::execute($schema, $ast, $data, null, [], 'Q');
        self::assertEquals(['data' => ['a' => 'b']], $queryResult->toArray());
    }

    /**
     * @see it('does not include illegal fields in output')
     */
    public function testDoesNotIncludeIllegalFieldsInOutput() : void
    {
        $doc            = 'mutation M {
      thisIsIllegalDontIncludeMe
    }';
        $ast            = Parser::parse($doc);
        $schema         = new Schema([
            'query'    => new ObjectType([
                'name'   => 'Q',
                'fields' => [
                    'a' => ['type' => Type::string()],
                ],
            ]),
            'mutation' => new ObjectType([
                'name'   => 'M',
                'fields' => [
                    'c' => ['type' => Type::string()],
                ],
            ]),
        ]);
        $mutationResult = Executor::execute($schema, $ast);
        self::assertEquals(['data' => []], $mutationResult->toArray());
    }

    /**
     * @see it('does not include arguments that were not set')
     */
    public function testDoesNotIncludeArgumentsThatWereNotSet() : void
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Type',
                'fields' => [
                    'field' => [
                        'type'    => Type::string(),
                        'resolve' => static function ($data, $args) {
                            return $args ? json_encode($args) : '';
                        },
                        'args'    => [
                            'a' => ['type' => Type::boolean()],
                            'b' => ['type' => Type::boolean()],
                            'c' => ['type' => Type::boolean()],
                            'd' => ['type' => Type::int()],
                            'e' => ['type' => Type::int()],
                        ],
                    ],
                ],
            ]),
        ]);

        $query    = Parser::parse('{ field(a: true, c: false, e: 0) }');
        $result   = Executor::execute($schema, $query);
        $expected = [
            'data' => ['field' => '{"a":true,"c":false,"e":0}'],
        ];

        self::assertEquals($expected, $result->toArray());
    }

    /**
     * @see it('fails when an isTypeOf check is not met')
     */
    public function testFailsWhenAnIsTypeOfCheckIsNotMet() : void
    {
        $SpecialType = new ObjectType([
            'name'     => 'SpecialType',
            'isTypeOf' => static function ($obj) {
                return $obj instanceof Special;
            },
            'fields'   => [
                'value' => ['type' => Type::string()],
            ],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'specials' => [
                        'type'    => Type::listOf($SpecialType),
                        'resolve' => static function ($rootValue) {
                            return $rootValue['specials'];
                        },
                    ],
                ],
            ]),
        ]);

        $query  = Parser::parse('{ specials { value } }');
        $value  = [
            'specials' => [new Special('foo'), new NotSpecial('bar')],
        ];
        $result = Executor::execute($schema, $query, $value);

        self::assertEquals(
            [
                'specials' => [
                    ['value' => 'foo'],
                    null,
                ],
            ],
            $result->data
        );

        self::assertEquals(1, count($result->errors));
        self::assertEquals(
            [
                'message'   => 'Expected value of type "SpecialType" but got: instance of GraphQL\Tests\Executor\TestClasses\NotSpecial.',
                'locations' => [['line' => 1, 'column' => 3]],
                'path'      => ['specials', 1],
            ],
            $result->errors[0]->toSerializableArray()
        );
    }

    /**
     * @see it('executes ignoring invalid non-executable definitions')
     */
    public function testExecutesIgnoringInvalidNonExecutableDefinitions() : void
    {
        $query = Parser::parse('
      { foo }

      type Query { bar: String }
    ');

        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'foo' => ['type' => Type::string()],
                ],
            ]),
        ]);

        $result = Executor::execute($schema, $query);

        $expected = [
            'data' => ['foo' => null],
        ];

        self::assertArraySubset($expected, $result->toArray());
    }

    /**
     * @see it('uses a custom field resolver')
     */
    public function testUsesACustomFieldResolver() : void
    {
        $query = Parser::parse('{ foo }');

        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'foo' => ['type' => Type::string()],
                ],
            ]),
        ]);

        // For the purposes of test, just return the name of the field!
        $customResolver = static function ($source, $args, $context, ResolveInfo $info) {
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
            'data' => ['foo' => 'foo'],
        ];

        self::assertEquals($expected, $result->toArray());
    }

    public function testSubstitutesArgumentWithDefaultValue() : void
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Type',
                'fields' => [
                    'field' => [
                        'type'    => Type::string(),
                        'resolve' => static function ($data, $args) {
                            return $args ? json_encode($args) : '';
                        },
                        'args'    => [
                            'a' => ['type' => Type::boolean(), 'defaultValue' => 1],
                            'b' => ['type' => Type::boolean(), 'defaultValue' => null],
                            'c' => ['type' => Type::boolean(), 'defaultValue' => 0],
                            'd' => ['type' => Type::int(), 'defaultValue' => false],
                            'e' => ['type' => Type::int(), 'defaultValue' => '0'],
                            'f' => ['type' => Type::int(), 'defaultValue' => 'some-string'],
                            'g' => ['type' => Type::boolean()],
                            'h' => [
                                'type'             => new InputObjectType([
                                    'name'   => 'ComplexType',
                                    'fields' => [
                                        'a' => ['type' => Type::int()],
                                        'b' => ['type' => Type::string()],
                                    ],
                                ]), 'defaultValue' => ['a' => 1, 'b' => 'test'],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $query    = Parser::parse('{ field }');
        $result   = Executor::execute($schema, $query);
        $expected = [
            'data' => ['field' => '{"a":1,"b":null,"c":0,"d":false,"e":"0","f":"some-string","h":{"a":1,"b":"test"}}'],
        ];

        self::assertEquals($expected, $result->toArray());
    }

    /**
     * @see https://github.com/webonyx/graphql-php/issues/59
     */
    public function testSerializesToEmptyObjectVsEmptyArray() : void
    {
        $iface = null;

        $a = new ObjectType([
            'name'       => 'A',
            'fields'     => [
                'id' => Type::id(),
            ],
            'interfaces' => static function () use (&$iface) {
                return [$iface];
            },
        ]);

        $b = new ObjectType([
            'name'       => 'B',
            'fields'     => [
                'id' => Type::id(),
            ],
            'interfaces' => static function () use (&$iface) {
                return [$iface];
            },
        ]);

        $iface = new InterfaceType([
            'name'        => 'Iface',
            'fields'      => [
                'id' => Type::id(),
            ],
            'resolveType' => static function ($v) use ($a, $b) {
                return $v['type'] === 'A' ? $a : $b;
            },
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'ab' => Type::listOf($iface),
                ],
            ]),
            'types' => [$a, $b],
        ]);

        $data = [
            'ab' => [
                ['id' => 1, 'type' => 'A'],
                ['id' => 2, 'type' => 'A'],
                ['id' => 3, 'type' => 'B'],
                ['id' => 4, 'type' => 'B'],
            ],
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

        self::assertEquals(
            [
                'data' => [
                    'ab' => [
                        ['id' => '1'],
                        ['id' => '2'],
                        new stdClass(),
                        new stdClass(),
                    ],
                ],
            ],
            $result->toArray()
        );
    }
}
