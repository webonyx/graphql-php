<?php

namespace GraphQL\Tests\Executor;

use GraphQL\Deferred;
use GraphQL\Error\UserError;
use GraphQL\Executor\Executor;
use GraphQL\Error\FormattedError;
use GraphQL\Language\Parser;
use GraphQL\Language\SourceLocation;
use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class ListsTest extends \PHPUnit_Framework_TestCase
{
    // Describe: Execute: Handles list nullability

    /**
     * @describe [T]
     */
    public function testHandlesNullableListsWithArray()
    {
        // Contains values
        $this->checkHandlesNullableLists(
            [ 1, 2 ],
            [ 'data' => [ 'nest' => [ 'test' => [ 1, 2 ] ] ] ]
        );

        // Contains null
        $this->checkHandlesNullableLists(
            [ 1, null, 2 ],
            [ 'data' => [ 'nest' => [ 'test' => [ 1, null, 2 ] ] ] ]
        );

        // Returns null
        $this->checkHandlesNullableLists(
            null,
            [ 'data' => [ 'nest' => [ 'test' => null ] ] ]
        );
    }

    /**
     * @describe [T]
     */
    public function testHandlesNullableListsWithPromiseArray()
    {
        // Contains values
        $this->checkHandlesNullableLists(
            new Deferred(function() {
                return [1,2];
            }),
            [ 'data' => [ 'nest' => [ 'test' => [ 1, 2 ] ] ] ]
        );

        // Contains null
        $this->checkHandlesNullableLists(
            new Deferred(function() {
                return [1, null, 2];
            }),
            [ 'data' => [ 'nest' => [ 'test' => [ 1, null, 2 ] ] ] ]
        );

        // Returns null
        $this->checkHandlesNullableLists(
            new Deferred(function() {
                return null;
            }),
            [ 'data' => [ 'nest' => [ 'test' => null ] ] ]
        );

        // Rejected
        $this->checkHandlesNullableLists(
            function () {
                return new Deferred(function () {
                    throw new UserError('bad');
                });
            },
            [
                'data' => ['nest' => ['test' => null]],
                'errors' => [
                    [
                        'message' => 'bad',
                        'locations' => [['line' => 1, 'column' => 10]],
                        'path' => ['nest', 'test']
                    ]
                ]
            ]
        );
    }

    /**
     * @describe [T]
     */
    public function testHandlesNullableListsWithArrayPromise()
    {
        // Contains values
        $this->checkHandlesNullableLists(
            [
                new Deferred(function() {
                    return 1;
                }),
                new Deferred(function() {
                    return 2;
                })],
            [ 'data' => [ 'nest' => [ 'test' => [ 1, 2 ] ] ] ]
        );

        // Contains null
        $this->checkHandlesNullableLists(
            [
                new Deferred(function() {return 1;}),
                new Deferred(function() {return null;}),
                new Deferred(function() {return 2;})
            ],
            [ 'data' => [ 'nest' => [ 'test' => [ 1, null, 2 ] ] ] ]
        );

        // Returns null
        $this->checkHandlesNullableLists(
            new Deferred(function() {
                return null;
            }),
            [ 'data' => [ 'nest' => [ 'test' => null ] ] ]
        );

        // Contains reject
        $this->checkHandlesNullableLists(
            function () {
                return [
                    new Deferred(function() {
                        return 1;
                    }),
                    new Deferred(function() {
                        throw new UserError('bad');
                    }),
                    new Deferred(function() {
                        return 2;
                    })
                ];
            },
            [
                'data' => ['nest' => ['test' => [1, null, 2]]],
                'errors' => [
                    [
                        'message' => 'bad',
                        'locations' => [['line' => 1, 'column' => 10]],
                        'path' => ['nest', 'test', 1]
                    ]
                ]
            ]
        );
    }

    /**
     * @describe [T]!
     */
    public function testHandlesNonNullableListsWithArray()
    {
        // Contains values
        $this->checkHandlesNonNullableLists(
            [ 1, 2 ],
            [ 'data' => [ 'nest' => [ 'test' => [ 1, 2 ] ] ] ]
        );

        // Contains null
        $this->checkHandlesNonNullableLists(
            [ 1, null, 2 ],
            [ 'data' => [ 'nest' => [ 'test' => [ 1, null, 2 ] ] ] ]
        );

        // Returns null
        $this->checkHandlesNonNullableLists(
            null,
            [
                'data' => [ 'nest' => null ],
                'errors' => [
                    [
                        'debugMessage' => 'Cannot return null for non-nullable field DataType.test.',
                        'locations' => [['line' => 1, 'column' => 10]]
                    ]
                ]
            ],
            true
        );
    }

    /**
     * @describe [T]!
     */
    public function testHandlesNonNullableListsWithPromiseArray()
    {
        // Contains values
        $this->checkHandlesNonNullableLists(
            new Deferred(function() {
                return [1,2];
            }),
            [ 'data' => [ 'nest' => [ 'test' => [ 1, 2 ] ] ] ]
        );

        // Contains null
        $this->checkHandlesNonNullableLists(
            new Deferred(function() {
                return [1, null, 2];
            }),
            [ 'data' => [ 'nest' => [ 'test' => [ 1, null, 2 ] ] ] ]
        );

        // Returns null
        $this->checkHandlesNonNullableLists(
            null,
            [
                'data' => [ 'nest' => null ],
                'errors' => [
                    [
                        'debugMessage' => 'Cannot return null for non-nullable field DataType.test.',
                        'locations' => [['line' => 1, 'column' => 10]]
                    ]
                ]
            ],
            true
        );

        // Rejected
        $this->checkHandlesNonNullableLists(
            function () {
                return new Deferred(function() {
                    throw new UserError('bad');
                });
            },
            [
                'data' => ['nest' => null],
                'errors' => [
                    [
                        'message' => 'bad',
                        'locations' => [['line' => 1, 'column' => 10]],
                        'path' => ['nest', 'test']
                    ]
                ]
            ]
        );
    }

    /**
     * @describe [T]!
     */
    public function testHandlesNonNullableListsWithArrayPromise()
    {
        // Contains values
        $this->checkHandlesNonNullableLists(
            [
                new Deferred(function() {
                    return 1;
                }),
                new Deferred(function() {
                    return 2;
                })
            ],
            [ 'data' => [ 'nest' => [ 'test' => [ 1, 2 ] ] ] ]
        );

        // Contains null
        $this->checkHandlesNonNullableLists(
            [
                new Deferred(function() {
                    return 1;
                }),
                new Deferred(function() {
                    return null;
                }),
                new Deferred(function() {
                    return 2;
                })
            ],
            [ 'data' => [ 'nest' => [ 'test' => [ 1, null, 2 ] ] ] ]
        );

        // Contains reject
        $this->checkHandlesNonNullableLists(
            function () {
                return [
                    new Deferred(function() {
                        return 1;
                    }),
                    new Deferred(function() {
                        throw new UserError('bad');
                    }),
                    new Deferred(function() {
                        return 2;
                    })
                ];
            },
            [
                'data' => ['nest' => ['test' => [1, null, 2]]],
                'errors' => [
                    [
                        'message' => 'bad',
                        'locations' => [['line' => 1, 'column' => 10]],
                        'path' => ['nest', 'test', 1]
                    ]
                ]
            ]
        );
    }

    /**
     * @describe [T!]
     */
    public function testHandlesListOfNonNullsWithArray()
    {
        // Contains values
        $this->checkHandlesListOfNonNulls(
            [ 1, 2 ],
            [ 'data' => [ 'nest' => [ 'test' => [ 1, 2 ] ] ] ]
        );

        // Contains null
        $this->checkHandlesListOfNonNulls(
            [ 1, null, 2 ],
            [
                'data' => [ 'nest' => [ 'test' => null ] ],
                'errors' => [
                    [
                        'debugMessage' => 'Cannot return null for non-nullable field DataType.test.',
                        'locations' => [ ['line' => 1, 'column' => 10] ]
                    ]
                ]
            ],
            true
        );

        // Returns null
        $this->checkHandlesListOfNonNulls(
            null,
            [ 'data' => [ 'nest' => [ 'test' => null ] ] ]
        );
    }

    /**
     * @describe [T!]
     */
    public function testHandlesListOfNonNullsWithPromiseArray()
    {
        // Contains values
        $this->checkHandlesListOfNonNulls(
            new Deferred(function() {
                return [1, 2];
            }),
            [ 'data' => [ 'nest' => [ 'test' => [ 1, 2 ] ] ] ]
        );

        // Contains null
        $this->checkHandlesListOfNonNulls(
            new Deferred(function() {
                return [1, null, 2];
            }),
            [
                'data' => [ 'nest' => [ 'test' => null ] ],
                'errors' => [
                    [
                        'debugMessage' => 'Cannot return null for non-nullable field DataType.test.',
                        'locations' => [['line' => 1, 'column' => 10]]
                    ]
                ]
            ],
            true
        );

        // Returns null
        $this->checkHandlesListOfNonNulls(
            new Deferred(function() {
                return null;
            }),
            [ 'data' => [ 'nest' => [ 'test' => null ] ] ]
        );

        // Rejected
        $this->checkHandlesListOfNonNulls(
            function () {
                return new Deferred(function() {
                    throw new UserError('bad');
                });
            },
            [
                'data' => ['nest' => ['test' => null]],
                'errors' => [
                    [
                        'message' => 'bad',
                        'locations' => [['line' => 1, 'column' => 10]],
                        'path' => ['nest', 'test']
                    ]
                ]
            ]
        );
    }

    /**
     * @describe [T]!
     */
    public function testHandlesListOfNonNullsWithArrayPromise()
    {
        // Contains values
        $this->checkHandlesListOfNonNulls(
            [
                new Deferred(function() {
                    return 1;
                }),
                new Deferred(function() {
                    return 2;
                })
            ],
            [ 'data' => [ 'nest' => [ 'test' => [ 1, 2 ] ] ] ]
        );

        // Contains null
        $this->checkHandlesListOfNonNulls(
            [
                new Deferred(function() {return 1;}),
                new Deferred(function() {return null;}),
                new Deferred(function() {return 2;})
            ],
            [ 'data' => [ 'nest' => [ 'test' => null ] ] ]
        );

        // Contains reject
        $this->checkHandlesListOfNonNulls(
            function () {
                return [
                    new Deferred(function() {
                        return 1;
                    }),
                    new Deferred(function() {
                        throw new UserError('bad');
                    }),
                    new Deferred(function() {
                        return 2;
                    })
                ];
            },
            [
                'data' => ['nest' => ['test' => null]],
                'errors' => [
                    [
                        'message' => 'bad',
                        'locations' => [['line' => 1, 'column' => 10]],
                        'path' => ['nest', 'test', 1]
                    ]
                ]
            ]
        );
    }

    /**
     * @describe [T!]!
     */
    public function testHandlesNonNullListOfNonNullsWithArray()
    {
        // Contains values
        $this->checkHandlesNonNullListOfNonNulls(
            [ 1, 2 ],
            [ 'data' => [ 'nest' => [ 'test' => [ 1, 2 ] ] ] ]
        );


        // Contains null
        $this->checkHandlesNonNullListOfNonNulls(
            [ 1, null, 2 ],
            [
                'data' => [ 'nest' => null ],
                'errors' => [
                    [
                        'debugMessage' => 'Cannot return null for non-nullable field DataType.test.',
                        'locations' => [['line' => 1, 'column' => 10 ]]
                    ]
                ]
            ],
            true
        );

        // Returns null
        $this->checkHandlesNonNullListOfNonNulls(
            null,
            [
                'data' => [ 'nest' => null ],
                'errors' => [
                    [
                        'debugMessage' => 'Cannot return null for non-nullable field DataType.test.',
                        'locations' => [ ['line' => 1, 'column' => 10] ]
                    ]
                ]
            ],
            true
        );
    }

    /**
     * @describe [T!]!
     */
    public function testHandlesNonNullListOfNonNullsWithPromiseArray()
    {
        // Contains values
        $this->checkHandlesNonNullListOfNonNulls(
            new Deferred(function() {
                return [1, 2];
            }),
            [ 'data' => [ 'nest' => [ 'test' => [ 1, 2 ] ] ] ]
        );

        // Contains null
        $this->checkHandlesNonNullListOfNonNulls(
            new Deferred(function() {
                return [1, null, 2];
            }),
            [
                'data' => [ 'nest' => null ],
                'errors' => [
                    [
                        'debugMessage' => 'Cannot return null for non-nullable field DataType.test.',
                        'locations' => [ ['line' => 1, 'column' => 10] ]
                    ]
                ]
            ],
            true
        );

        // Returns null
        $this->checkHandlesNonNullListOfNonNulls(
            new Deferred(function() {
                return null;
            }),
            [
                'data' => [ 'nest' => null ],
                'errors' => [
                    [
                        'debugMessage' => 'Cannot return null for non-nullable field DataType.test.',
                        'locations' => [ ['line' => 1, 'column' => 10] ]
                    ]
                ]
            ],
            true
        );

        // Rejected
        $this->checkHandlesNonNullListOfNonNulls(
            function () {
                return new Deferred(function() {
                    throw new UserError('bad');
                });
            },
            [
                'data' => ['nest' => null ],
                'errors' => [
                    [
                        'message' => 'bad',
                        'locations' => [['line' => 1, 'column' => 10]],
                        'path' => ['nest', 'test']
                    ]
                ]
            ]
        );
    }

    /**
     * @describe [T!]!
     */
    public function testHandlesNonNullListOfNonNullsWithArrayPromise()
    {
        // Contains values
        $this->checkHandlesNonNullListOfNonNulls(
            [
                new Deferred(function() {
                    return 1;
                }),
                new Deferred(function() {
                    return 2;
                })

            ],
            [ 'data' => [ 'nest' => [ 'test' => [ 1, 2 ] ] ] ]
        );

        // Contains null
        $this->checkHandlesNonNullListOfNonNulls(
            [
                new Deferred(function() {
                    return 1;
                }),
                new Deferred(function() {
                    return null;
                }),
                new Deferred(function() {
                    return 2;
                })
            ],
            [
                'data' => [ 'nest' => null ],
                'errors' => [
                    [
                        'debugMessage' => 'Cannot return null for non-nullable field DataType.test.',
                        'locations' => [['line' => 1, 'column' => 10]]
                    ]
                ]
            ],
            true
        );

        // Contains reject
        $this->checkHandlesNonNullListOfNonNulls(
            function () {
                return [
                    new Deferred(function() {
                        return 1;
                    }),
                    new Deferred(function() {
                        throw new UserError('bad');
                    }),
                    new Deferred(function() {
                        return 2;
                    })
                ];
            },
            [
                'data' => ['nest' => null ],
                'errors' => [
                    [
                        'message' => 'bad',
                        'locations' => [['line' => 1, 'column' => 10]],
                        'path' => ['nest', 'test']
                    ]
                ]
            ]
        );
    }

    private function checkHandlesNullableLists($testData, $expected)
    {
        $testType = Type::listOf(Type::int());
        $this->check($testType, $testData, $expected);
    }

    private function checkHandlesNonNullableLists($testData, $expected, $debug = false)
    {
        $testType = Type::nonNull(Type::listOf(Type::int()));
        $this->check($testType, $testData, $expected, $debug);
    }

    private function checkHandlesListOfNonNulls($testData, $expected, $debug = false)
    {
        $testType = Type::listOf(Type::nonNull(Type::int()));
        $this->check($testType, $testData, $expected, $debug);
    }

    public function checkHandlesNonNullListOfNonNulls($testData, $expected, $debug = false)
    {
        $testType = Type::nonNull(Type::listOf(Type::nonNull(Type::int())));
        $this->check($testType, $testData, $expected, $debug);
    }

    private function check($testType, $testData, $expected, $debug = false)
    {
        $data = ['test' => $testData];
        $dataType = null;

        $dataType = new ObjectType([
            'name' => 'DataType',
            'fields' => function () use (&$testType, &$dataType, $data) {
                return [
                    'test' => [
                        'type' => $testType
                    ],
                    'nest' => [
                        'type' => $dataType,
                        'resolve' => function () use ($data) {
                            return $data;
                        }
                    ]
                ];
            }
        ]);

        $schema = new Schema([
            'query' => $dataType
        ]);

        $ast = Parser::parse('{ nest { test } }');

        $result = Executor::execute($schema, $ast, $data);
        $this->assertArraySubset($expected, $result->toArray($debug));
    }
}
