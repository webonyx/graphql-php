<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use GraphQL\Deferred;
use GraphQL\Error\UserError;
use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

class ListsTest extends TestCase
{
    // Describe: Execute: Handles list nullability
    /**
     * [T]
     */
    public function testHandlesNullableListsWithArray() : void
    {
        // Contains values
        $this->checkHandlesNullableLists(
            [1, 2],
            ['data' => ['nest' => ['test' => [1, 2]]]]
        );

        // Contains null
        $this->checkHandlesNullableLists(
            [1, null, 2],
            ['data' => ['nest' => ['test' => [1, null, 2]]]]
        );

        // Returns null
        $this->checkHandlesNullableLists(
            null,
            ['data' => ['nest' => ['test' => null]]]
        );
    }

    private function checkHandlesNullableLists($testData, $expected)
    {
        $testType = Type::listOf(Type::int());
        $this->check($testType, $testData, $expected);
    }

    private function check($testType, $testData, $expected, $debug = false)
    {
        $data     = ['test' => $testData];
        $dataType = null;

        $dataType = new ObjectType([
            'name'   => 'DataType',
            'fields' => function () use (&$testType, &$dataType, $data) {
                return [
                    'test' => ['type' => $testType],
                    'nest' => [
                        'type'    => $dataType,
                        'resolve' => function () use ($data) {
                            return $data;
                        },
                    ],
                ];
            },
        ]);

        $schema = new Schema(['query' => $dataType]);

        $ast = Parser::parse('{ nest { test } }');

        $result = Executor::execute($schema, $ast, $data);
        $this->assertArraySubset($expected, $result->toArray($debug));
    }

    /**
     * [T]
     */
    public function testHandlesNullableListsWithPromiseArray() : void
    {
        // Contains values
        $this->checkHandlesNullableLists(
            new Deferred(function () {
                return [1, 2];
            }),
            ['data' => ['nest' => ['test' => [1, 2]]]]
        );

        // Contains null
        $this->checkHandlesNullableLists(
            new Deferred(function () {
                return [1, null, 2];
            }),
            ['data' => ['nest' => ['test' => [1, null, 2]]]]
        );

        // Returns null
        $this->checkHandlesNullableLists(
            new Deferred(function () {
                return null;
            }),
            ['data' => ['nest' => ['test' => null]]]
        );

        // Rejected
        $this->checkHandlesNullableLists(
            function () {
                return new Deferred(function () {
                    throw new UserError('bad');
                });
            },
            [
                'data'   => ['nest' => ['test' => null]],
                'errors' => [
                    [
                        'message'   => 'bad',
                        'locations' => [['line' => 1, 'column' => 10]],
                        'path'      => ['nest', 'test'],
                    ],
                ],
            ]
        );
    }

    /**
     * [T]
     */
    public function testHandlesNullableListsWithArrayPromise() : void
    {
        // Contains values
        $this->checkHandlesNullableLists(
            [
                new Deferred(function () {
                    return 1;
                }),
                new Deferred(function () {
                    return 2;
                }),
            ],
            ['data' => ['nest' => ['test' => [1, 2]]]]
        );

        // Contains null
        $this->checkHandlesNullableLists(
            [
                new Deferred(function () {
                    return 1;
                }),
                new Deferred(function () {
                    return null;
                }),
                new Deferred(function () {
                    return 2;
                }),
            ],
            ['data' => ['nest' => ['test' => [1, null, 2]]]]
        );

        // Returns null
        $this->checkHandlesNullableLists(
            new Deferred(function () {
                return null;
            }),
            ['data' => ['nest' => ['test' => null]]]
        );

        // Contains reject
        $this->checkHandlesNullableLists(
            function () {
                return [
                    new Deferred(function () {
                        return 1;
                    }),
                    new Deferred(function () {
                        throw new UserError('bad');
                    }),
                    new Deferred(function () {
                        return 2;
                    }),
                ];
            },
            [
                'data'   => ['nest' => ['test' => [1, null, 2]]],
                'errors' => [
                    [
                        'message'   => 'bad',
                        'locations' => [['line' => 1, 'column' => 10]],
                        'path'      => ['nest', 'test', 1],
                    ],
                ],
            ]
        );
    }

    /**
     * [T]!
     */
    public function testHandlesNonNullableListsWithArray() : void
    {
        // Contains values
        $this->checkHandlesNonNullableLists(
            [1, 2],
            ['data' => ['nest' => ['test' => [1, 2]]]]
        );

        // Contains null
        $this->checkHandlesNonNullableLists(
            [1, null, 2],
            ['data' => ['nest' => ['test' => [1, null, 2]]]]
        );

        // Returns null
        $this->checkHandlesNonNullableLists(
            null,
            [
                'data'   => ['nest' => null],
                'errors' => [
                    [
                        'debugMessage' => 'Cannot return null for non-nullable field DataType.test.',
                        'locations'    => [['line' => 1, 'column' => 10]],
                    ],
                ],
            ],
            true
        );
    }

    private function checkHandlesNonNullableLists($testData, $expected, $debug = false)
    {
        $testType = Type::nonNull(Type::listOf(Type::int()));
        $this->check($testType, $testData, $expected, $debug);
    }

    /**
     * [T]!
     */
    public function testHandlesNonNullableListsWithPromiseArray() : void
    {
        // Contains values
        $this->checkHandlesNonNullableLists(
            new Deferred(function () {
                return [1, 2];
            }),
            ['data' => ['nest' => ['test' => [1, 2]]]]
        );

        // Contains null
        $this->checkHandlesNonNullableLists(
            new Deferred(function () {
                return [1, null, 2];
            }),
            ['data' => ['nest' => ['test' => [1, null, 2]]]]
        );

        // Returns null
        $this->checkHandlesNonNullableLists(
            null,
            [
                'data'   => ['nest' => null],
                'errors' => [
                    [
                        'debugMessage' => 'Cannot return null for non-nullable field DataType.test.',
                        'locations'    => [['line' => 1, 'column' => 10]],
                    ],
                ],
            ],
            true
        );

        // Rejected
        $this->checkHandlesNonNullableLists(
            function () {
                return new Deferred(function () {
                    throw new UserError('bad');
                });
            },
            [
                'data'   => ['nest' => null],
                'errors' => [
                    [
                        'message'   => 'bad',
                        'locations' => [['line' => 1, 'column' => 10]],
                        'path'      => ['nest', 'test'],
                    ],
                ],
            ]
        );
    }

    /**
     * [T]!
     */
    public function testHandlesNonNullableListsWithArrayPromise() : void
    {
        // Contains values
        $this->checkHandlesNonNullableLists(
            [
                new Deferred(function () {
                    return 1;
                }),
                new Deferred(function () {
                    return 2;
                }),
            ],
            ['data' => ['nest' => ['test' => [1, 2]]]]
        );

        // Contains null
        $this->checkHandlesNonNullableLists(
            [
                new Deferred(function () {
                    return 1;
                }),
                new Deferred(function () {
                    return null;
                }),
                new Deferred(function () {
                    return 2;
                }),
            ],
            ['data' => ['nest' => ['test' => [1, null, 2]]]]
        );

        // Contains reject
        $this->checkHandlesNonNullableLists(
            function () {
                return [
                    new Deferred(function () {
                        return 1;
                    }),
                    new Deferred(function () {
                        throw new UserError('bad');
                    }),
                    new Deferred(function () {
                        return 2;
                    }),
                ];
            },
            [
                'data'   => ['nest' => ['test' => [1, null, 2]]],
                'errors' => [
                    [
                        'message'   => 'bad',
                        'locations' => [['line' => 1, 'column' => 10]],
                        'path'      => ['nest', 'test', 1],
                    ],
                ],
            ]
        );
    }

    /**
     * [T!]
     */
    public function testHandlesListOfNonNullsWithArray() : void
    {
        // Contains values
        $this->checkHandlesListOfNonNulls(
            [1, 2],
            ['data' => ['nest' => ['test' => [1, 2]]]]
        );

        // Contains null
        $this->checkHandlesListOfNonNulls(
            [1, null, 2],
            [
                'data'   => ['nest' => ['test' => null]],
                'errors' => [
                    [
                        'debugMessage' => 'Cannot return null for non-nullable field DataType.test.',
                        'locations'    => [['line' => 1, 'column' => 10]],
                    ],
                ],
            ],
            true
        );

        // Returns null
        $this->checkHandlesListOfNonNulls(
            null,
            ['data' => ['nest' => ['test' => null]]]
        );
    }

    private function checkHandlesListOfNonNulls($testData, $expected, $debug = false)
    {
        $testType = Type::listOf(Type::nonNull(Type::int()));
        $this->check($testType, $testData, $expected, $debug);
    }

    /**
     * [T!]
     */
    public function testHandlesListOfNonNullsWithPromiseArray() : void
    {
        // Contains values
        $this->checkHandlesListOfNonNulls(
            new Deferred(function () {
                return [1, 2];
            }),
            ['data' => ['nest' => ['test' => [1, 2]]]]
        );

        // Contains null
        $this->checkHandlesListOfNonNulls(
            new Deferred(function () {
                return [1, null, 2];
            }),
            [
                'data'   => ['nest' => ['test' => null]],
                'errors' => [
                    [
                        'debugMessage' => 'Cannot return null for non-nullable field DataType.test.',
                        'locations'    => [['line' => 1, 'column' => 10]],
                    ],
                ],
            ],
            true
        );

        // Returns null
        $this->checkHandlesListOfNonNulls(
            new Deferred(function () {
                return null;
            }),
            ['data' => ['nest' => ['test' => null]]]
        );

        // Rejected
        $this->checkHandlesListOfNonNulls(
            function () {
                return new Deferred(function () {
                    throw new UserError('bad');
                });
            },
            [
                'data'   => ['nest' => ['test' => null]],
                'errors' => [
                    [
                        'message'   => 'bad',
                        'locations' => [['line' => 1, 'column' => 10]],
                        'path'      => ['nest', 'test'],
                    ],
                ],
            ]
        );
    }

    /**
     * [T]!
     */
    public function testHandlesListOfNonNullsWithArrayPromise() : void
    {
        // Contains values
        $this->checkHandlesListOfNonNulls(
            [
                new Deferred(function () {
                    return 1;
                }),
                new Deferred(function () {
                    return 2;
                }),
            ],
            ['data' => ['nest' => ['test' => [1, 2]]]]
        );

        // Contains null
        $this->checkHandlesListOfNonNulls(
            [
                new Deferred(function () {
                    return 1;
                }),
                new Deferred(function () {
                    return null;
                }),
                new Deferred(function () {
                    return 2;
                }),
            ],
            ['data' => ['nest' => ['test' => null]]]
        );

        // Contains reject
        $this->checkHandlesListOfNonNulls(
            function () {
                return [
                    new Deferred(function () {
                        return 1;
                    }),
                    new Deferred(function () {
                        throw new UserError('bad');
                    }),
                    new Deferred(function () {
                        return 2;
                    }),
                ];
            },
            [
                'data'   => ['nest' => ['test' => null]],
                'errors' => [
                    [
                        'message'   => 'bad',
                        'locations' => [['line' => 1, 'column' => 10]],
                        'path'      => ['nest', 'test', 1],
                    ],
                ],
            ]
        );
    }

    /**
     * [T!]!
     */
    public function testHandlesNonNullListOfNonNullsWithArray() : void
    {
        // Contains values
        $this->checkHandlesNonNullListOfNonNulls(
            [1, 2],
            ['data' => ['nest' => ['test' => [1, 2]]]]
        );

        // Contains null
        $this->checkHandlesNonNullListOfNonNulls(
            [1, null, 2],
            [
                'data'   => ['nest' => null],
                'errors' => [
                    [
                        'debugMessage' => 'Cannot return null for non-nullable field DataType.test.',
                        'locations'    => [['line' => 1, 'column' => 10]],
                    ],
                ],
            ],
            true
        );

        // Returns null
        $this->checkHandlesNonNullListOfNonNulls(
            null,
            [
                'data'   => ['nest' => null],
                'errors' => [
                    [
                        'debugMessage' => 'Cannot return null for non-nullable field DataType.test.',
                        'locations'    => [['line' => 1, 'column' => 10]],
                    ],
                ],
            ],
            true
        );
    }

    public function checkHandlesNonNullListOfNonNulls($testData, $expected, $debug = false)
    {
        $testType = Type::nonNull(Type::listOf(Type::nonNull(Type::int())));
        $this->check($testType, $testData, $expected, $debug);
    }

    /**
     * [T!]!
     */
    public function testHandlesNonNullListOfNonNullsWithPromiseArray() : void
    {
        // Contains values
        $this->checkHandlesNonNullListOfNonNulls(
            new Deferred(function () {
                return [1, 2];
            }),
            ['data' => ['nest' => ['test' => [1, 2]]]]
        );

        // Contains null
        $this->checkHandlesNonNullListOfNonNulls(
            new Deferred(function () {
                return [1, null, 2];
            }),
            [
                'data'   => ['nest' => null],
                'errors' => [
                    [
                        'debugMessage' => 'Cannot return null for non-nullable field DataType.test.',
                        'locations'    => [['line' => 1, 'column' => 10]],
                    ],
                ],
            ],
            true
        );

        // Returns null
        $this->checkHandlesNonNullListOfNonNulls(
            new Deferred(function () {
                return null;
            }),
            [
                'data'   => ['nest' => null],
                'errors' => [
                    [
                        'debugMessage' => 'Cannot return null for non-nullable field DataType.test.',
                        'locations'    => [['line' => 1, 'column' => 10]],
                    ],
                ],
            ],
            true
        );

        // Rejected
        $this->checkHandlesNonNullListOfNonNulls(
            function () {
                return new Deferred(function () {
                    throw new UserError('bad');
                });
            },
            [
                'data'   => ['nest' => null],
                'errors' => [
                    [
                        'message'   => 'bad',
                        'locations' => [['line' => 1, 'column' => 10]],
                        'path'      => ['nest', 'test'],
                    ],
                ],
            ]
        );
    }

    /**
     * [T!]!
     */
    public function testHandlesNonNullListOfNonNullsWithArrayPromise() : void
    {
        // Contains values
        $this->checkHandlesNonNullListOfNonNulls(
            [
                new Deferred(function () {
                    return 1;
                }),
                new Deferred(function () {
                    return 2;
                }),

            ],
            ['data' => ['nest' => ['test' => [1, 2]]]]
        );

        // Contains null
        $this->checkHandlesNonNullListOfNonNulls(
            [
                new Deferred(function () {
                    return 1;
                }),
                new Deferred(function () {
                    return null;
                }),
                new Deferred(function () {
                    return 2;
                }),
            ],
            [
                'data'   => ['nest' => null],
                'errors' => [
                    [
                        'debugMessage' => 'Cannot return null for non-nullable field DataType.test.',
                        'locations'    => [['line' => 1, 'column' => 10]],
                    ],
                ],
            ],
            true
        );

        // Contains reject
        $this->checkHandlesNonNullListOfNonNulls(
            function () {
                return [
                    new Deferred(function () {
                        return 1;
                    }),
                    new Deferred(function () {
                        throw new UserError('bad');
                    }),
                    new Deferred(function () {
                        return 2;
                    }),
                ];
            },
            [
                'data'   => ['nest' => null],
                'errors' => [
                    [
                        'message'   => 'bad',
                        'locations' => [['line' => 1, 'column' => 10]],
                        'path'      => ['nest', 'test'],
                    ],
                ],
            ]
        );
    }
}
