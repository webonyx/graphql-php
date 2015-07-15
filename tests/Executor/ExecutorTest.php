<?php
namespace GraphQL\Executor;

use GraphQL\FormattedError;
use GraphQL\Language\Parser;
use GraphQL\Language\SourceLocation;
use GraphQL\Schema;
use GraphQL\Type\Definition\Config;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

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
                    'resolve' => function($obj, $args) { return $obj['pic']($args['size']); }
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

        $this->assertEquals($expected, Executor::execute($schema, $data, $ast, 'Example', ['size' => 100]));
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
                'deep' => ['type' => function () use (&$Type) {
                    return $Type;
                }, 'resolve' => function () {
                    return [];
                }]
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

        $this->assertEquals($expected, Executor::execute($schema, null, $ast));
    }

    public function testThreadsContextCorrectly()
    {
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

        Executor::execute($schema, $data, $ast, 'Example', []);
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
        Executor::execute($schema, null, $docAst, 'Example', []);
        $this->assertSame($gotHere, true);
    }

    public function testNullsOutErrorSubtrees()
    {
        $doc = '{
      sync,
      syncError,
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
                'async' => ['type' => Type::string()],
                'asyncReject' => ['type' => Type::string() ],
                'asyncError' => ['type' => Type::string()],
            ]
        ]));

        $expected = [
            'data' => [
                'sync' => 'sync',
                'syncError' => null,
                'async' => 'async',
                'asyncReject' => null,
                'asyncError' => null,
            ],
            'errors' => [
                new FormattedError('Error getting syncError', [new SourceLocation(3, 7)]),
                new FormattedError('Error getting asyncReject', [new SourceLocation(5, 7)]),
                new FormattedError('Error getting asyncError', [new SourceLocation(6, 7)])
            ]
        ];

        $result = Executor::execute($schema, $data, $docAst);

        $this->assertEquals($expected, $result);
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

        $ex = Executor::execute($schema, $data, $ast);

        $this->assertEquals(['data' => ['a' => 'b']], $ex);
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

        $ex = Executor::execute($schema, $data, $ast);
        $this->assertEquals(['data' => ['a' => 'b']], $ex);
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

        $ex = Executor::execute($schema, $data, $ast);

        $this->assertEquals(
            [
                'data' => null,
                'errors' => [new FormattedError('Must provide operation name if query contains multiple operations')]
            ],
            $ex
        );
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

        $queryResult = Executor::execute($schema, $data, $ast, 'Q');
        $this->assertEquals(['data' => ['a' => 'b']], $queryResult);
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
        $mutationResult = Executor::execute($schema, $data, $ast, 'M');
        $this->assertEquals(['data' => ['c' => 'd']], $mutationResult);
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

        $queryResult = Executor::execute($schema, $data, $ast, 'Q');
        $this->assertEquals(['data' => ['a' => 'b']], $queryResult);
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
        $mutationResult = Executor::execute($schema, null, $ast);
        $this->assertEquals(['data' => []], $mutationResult);
    }
}
