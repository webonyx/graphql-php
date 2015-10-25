<?php
namespace GraphQL\Executor;

use GraphQL\Error;
use GraphQL\FormattedError;
use GraphQL\Language\Parser;
use GraphQL\Language\SourceLocation;
use GraphQL\Schema;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Utils;

class ExecutorTest extends \PHPUnit_Framework_TestCase
{
    // Execute: Handles basic execution tasks
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
            'fields' => [
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
                'promise' => ['type' => function() use (&$dataType) {return $dataType;}],
                'deep' => [ 'type' => function() use(&$deepDataType) {return $deepDataType; }],
            ]
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
        $schema = new Schema($dataType);

        $this->assertEquals($expected, Executor::execute($schema, $ast, $data, ['size' => 100], 'Example')->toArray());
    }

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
            'fields' => [
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
                    'type' => function () use (&$Type) {
                        return $Type;
                    },
                    'resolve' => function () {
                        return [];
                    }
                ]
            ]
        ]);
        $schema = new Schema($Type);
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

    public function testThreadsContextCorrectly()
    {
        // threads context correctly
        $doc = 'query Example { a }';

        $gotHere = false;

        $data = [
            'contextThing' => 'thing',
        ];

        $ast = Parser::parse($doc);
        $schema = new Schema(new ObjectType([
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
        ]));

        Executor::execute($schema, $ast, $data, [], 'Example');
        $this->assertEquals(true, $gotHere);
    }

    public function testCorrectlyThreadsArguments()
    {
        $doc = '
      query Example {
        b(numArg: 123, stringArg: "foo")
      }
        ';

        $gotHere = false;

        $docAst = Parser::parse($doc);
        $schema = new Schema(new ObjectType([
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
        ]));
        Executor::execute($schema, $docAst, null, [], 'Example');
        $this->assertSame($gotHere, true);
    }

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
                throw new Error('Error getting syncError');
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
        $schema = new Schema(new ObjectType([
            'name' => 'Type',
            'fields' => [
                'sync' => ['type' => Type::string()],
                'syncError' => ['type' => Type::string()],
                'syncRawError' => [ 'type' => Type::string() ],
                'async' => ['type' => Type::string()],
                'asyncReject' => ['type' => Type::string() ],
                'asyncError' => ['type' => Type::string()],
            ]
        ]));

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

        $this->assertEquals($expected, $result->toArray());
    }

    public function testUsesTheInlineOperationIfNoOperationIsProvided()
    {
        // uses the inline operation if no operation is provided
        $doc = '{ a }';
        $data = ['a' => 'b'];
        $ast = Parser::parse($doc);
        $schema = new Schema(new ObjectType([
            'name' => 'Type',
            'fields' => [
                'a' => ['type' => Type::string()],
            ]
        ]));

        $ex = Executor::execute($schema, $ast, $data);

        $this->assertEquals(['data' => ['a' => 'b']], $ex->toArray());
    }

    public function testUsesTheOnlyOperationIfNoOperationIsProvided()
    {
        $doc = 'query Example { a }';
        $data = [ 'a' => 'b' ];
        $ast = Parser::parse($doc);
        $schema = new Schema(new ObjectType([
            'name' => 'Type',
            'fields' => [
                'a' => [ 'type' => Type::string() ],
            ]
        ]));

        $ex = Executor::execute($schema, $ast, $data);
        $this->assertEquals(['data' => ['a' => 'b']], $ex->toArray());
    }

    public function testThrowsIfNoOperationIsProvidedWithMultipleOperations()
    {
        $doc = 'query Example { a } query OtherExample { a }';
        $data = [ 'a' => 'b' ];
        $ast = Parser::parse($doc);
        $schema = new Schema(new ObjectType([
            'name' => 'Type',
            'fields' => [
                'a' => [ 'type' => Type::string() ],
            ]
        ]));

        try {
            Executor::execute($schema, $ast, $data);
            $this->fail('Expected exception is not thrown');
        } catch (Error $err) {
            $this->assertEquals('Must provide operation name if query contains multiple operations.', $err->getMessage());
        }
    }

    public function testUsesTheQuerySchemaForQueries()
    {
        $doc = 'query Q { a } mutation M { c }';
        $data = ['a' => 'b', 'c' => 'd'];
        $ast = Parser::parse($doc);
        $schema = new Schema(
            new ObjectType([
                'name' => 'Q',
                'fields' => [
                    'a' => ['type' => Type::string()],
                ]
            ]),
            new ObjectType([
                'name' => 'M',
                'fields' => [
                    'c' => ['type' => Type::string()],
                ]
            ])
        );

        $queryResult = Executor::execute($schema, $ast, $data, [], 'Q');
        $this->assertEquals(['data' => ['a' => 'b']], $queryResult->toArray());
    }

    public function testUsesTheMutationSchemaForMutations()
    {
        $doc = 'query Q { a } mutation M { c }';
        $data = [ 'a' => 'b', 'c' => 'd' ];
        $ast = Parser::parse($doc);
        $schema = new Schema(
            new ObjectType([
                'name' => 'Q',
                'fields' => [
                    'a' => ['type' => Type::string()],
                ]
            ]),
            new ObjectType([
                'name' => 'M',
                'fields' => [
                    'c' => [ 'type' => Type::string() ],
                ]
            ])
        );
        $mutationResult = Executor::execute($schema, $ast, $data, [], 'M');
        $this->assertEquals(['data' => ['c' => 'd']], $mutationResult->toArray());
    }

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
        $schema = new Schema(new ObjectType([
            'name' => 'Type',
            'fields' => [
                'a' => ['type' => Type::string()],
            ]
        ]));

        $queryResult = Executor::execute($schema, $ast, $data, [], 'Q');
        $this->assertEquals(['data' => ['a' => 'b']], $queryResult->toArray());
    }

    public function testDoesNotIncludeIllegalFieldsInOutput()
    {
        $doc = 'mutation M {
      thisIsIllegalDontIncludeMe
    }';
        $ast = Parser::parse($doc);
        $schema = new Schema(
            new ObjectType([
                'name' => 'Q',
                'fields' => [
                    'a' => ['type' => Type::string()],
                ]
            ]),
            new ObjectType([
                'name' => 'M',
                'fields' => [
                    'c' => ['type' => Type::string()],
                ]
            ])
        );
        $mutationResult = Executor::execute($schema, $ast);
        $this->assertEquals(['data' => []], $mutationResult->toArray());
    }

    public function testDoesNotIncludeArgumentsThatWereNotSet()
    {
        $schema = new Schema(
            new ObjectType([
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
        );

        $query = Parser::parse('{ field(a: true, c: false, e: 0) }');
        $result = Executor::execute($schema, $query);
        $expected = [
            'data' => [
                'field' => '{"a":true,"c":false,"e":0}'
            ]
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    public function testSubstitutesArgumentWithDefaultValue()
    {
        $schema = new Schema(
            new ObjectType([
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
                            'g' => ['type' => Type::boolean()]
                        ]
                    ]
                ]
            ])
        );

        $query = Parser::parse('{ field }');
        $result = Executor::execute($schema, $query);
        $expected = [
            'data' => [
                'field' => '{"a":1,"c":0,"d":false,"e":"0","f":"some-string"}'
            ]
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    public function testResolvedValueIsMemoized()
    {
        $doc = '
      query Q {
        a {
          b {
            c
            d
          }
        }
      }
      ';

        $memoizedValue = new \ArrayObject([
            'b' => 'id1'
        ]);

        $A = null;

        $Test = new ObjectType([
            'name' => 'Test',
            'fields' => [
                'a' => [
                    'type' => function() use (&$A) {return Type::listOf($A);},
                    'resolve' => function() use ($memoizedValue) {
                        return [
                            $memoizedValue,
                            new \ArrayObject([
                                'b' => 'id2',
                            ]),
                            $memoizedValue,
                            new \ArrayObject([
                                'b' => 'id2',
                            ])
                        ];
                    }
                ]
            ]
        ]);

        $callCounts = ['id1' => 0, 'id2' => 0];

        $A = new ObjectType([
            'name' => 'A',
            'fields' => [
                'b' => [
                    'type' => new ObjectType([
                        'name' => 'B',
                        'fields' => [
                            'c' => ['type' => Type::string()],
                            'd' => ['type' => Type::string()]
                        ]
                    ]),
                    'resolve' => function($value) use (&$callCounts) {
                        $callCounts[$value['b']]++;

                        switch ($value['b']) {
                            case 'id1':
                                return [
                                    'c' => 'c1',
                                    'd' => 'd1'
                                ];
                            case 'id2':
                                return [
                                    'c' => 'c2',
                                    'd' => 'd2'
                                ];
                        }
                    }
                ]
            ]
        ]);

        // Test that value resolved once is memoized for same query field
        $schema = new Schema($Test);

        $query = Parser::parse($doc);
        $result = Executor::execute($schema, $query);
        $expected = [
            'data' => [
                'a' => [
                    ['b' => ['c' => 'c1', 'd' => 'd1']],
                    ['b' => ['c' => 'c2', 'd' => 'd2']],
                    ['b' => ['c' => 'c1', 'd' => 'd1']],
                    ['b' => ['c' => 'c2', 'd' => 'd2']],
                ]
            ]
        ];

        $this->assertEquals($expected, $result->toArray());

        $this->assertSame($callCounts['id1'], 1); // Result for id1 is expected to be memoized after first call
        $this->assertSame($callCounts['id2'], 2);
    }
}
