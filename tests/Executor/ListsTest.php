<?php
namespace GraphQL\Executor;

use GraphQL\Error;
use GraphQL\FormattedError;
use GraphQL\Language\Parser;
use GraphQL\Language\SourceLocation;
use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class ListsTest extends \PHPUnit_Framework_TestCase
{
    // Execute: Handles list nullability

    public function testHandlesListsWhenTheyReturnNonNullValues()
    {
        $doc = '
      query Q {
        nest {
          list,
        }
      }
        ';

        $ast = Parser::parse($doc);
        $expected = ['data' => ['nest' => ['list' => [1,2]]]];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, $this->data(), [], 'Q')->toArray());
    }

    public function testHandlesListsOfNonNullsWhenTheyReturnNonNullValues()
    {
        $doc = '
      query Q {
        nest {
          listOfNonNull,
        }
      }
        ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'nest' => [
                    'listOfNonNull' => [1, 2],
                ]
            ]
        ];

        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, $this->data(), [], 'Q')->toArray());
    }

    public function testHandlesNonNullListsOfWhenTheyReturnNonNullValues()
    {
        $doc = '
      query Q {
        nest {
          nonNullList,
        }
      }
        ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'nest' => [
                    'nonNullList' => [1, 2],
                ]
            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, $this->data(), [], 'Q')->toArray());
    }

    public function testHandlesNonNullListsOfNonNullsWhenTheyReturnNonNullValues()
    {
        $doc = '
      query Q {
        nest {
          nonNullListOfNonNull,
        }
      }
        ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'nest' => [
                    'nonNullListOfNonNull' => [1, 2],
                ]
            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, $this->data(), [], 'Q')->toArray());
    }

    public function testHandlesListsWhenTheyReturnNullAsAValue()
    {
        $doc = '
      query Q {
        nest {
          listContainsNull,
        }
      }
        ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'nest' => [
                    'listContainsNull' => [1, null, 2],
                ]
            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, $this->data(), [], 'Q')->toArray());
    }

    public function testHandlesListsOfNonNullsWhenTheyReturnNullAsAValue()
    {
        $doc = '
      query Q {
        nest {
          listOfNonNullContainsNull,
        }
      }
        ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'nest' => [
                    'listOfNonNullContainsNull' => null
                ]
            ],
            'errors' => [
                FormattedError::create(
                    'Cannot return null for non-nullable type.',
                    [new SourceLocation(4, 11)]
                )
            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, $this->data(), [], 'Q')->toArray());
    }

    public function testHandlesNonNullListsOfWhenTheyReturnNullAsAValue()
    {
        $doc = '
      query Q {
        nest {
          nonNullListContainsNull,
        }
      }
        ';

        $ast = Parser::parse($doc);
        $expected = [
            'data' => [
                'nest' => ['nonNullListContainsNull' => [1, null, 2]]
            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, $this->data(), [], 'Q')->toArray());
    }

    public function testHandlesNonNullListsOfNonNullsWhenTheyReturnNullAsAValue()
    {
        $doc = '
      query Q {
        nest {
          nonNullListOfNonNullContainsNull,
        }
      }
        ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'nest' => null
            ],
            'errors' => [
                FormattedError::create(
                    'Cannot return null for non-nullable type.',
                    [new SourceLocation(4, 11)]
                )
            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, $this->data(), [], 'Q')->toArray());
    }

    public function testHandlesListsWhenTheyReturnNull()
    {
        $doc = '
      query Q {
        nest {
          listReturnsNull,
        }
      }
        ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'nest' => [
                    'listReturnsNull' => null
                ]
            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, $this->data(), [], 'Q')->toArray());
    }

    public function testHandlesListsOfNonNullsWhenTheyReturnNull()
    {
        $doc = '
      query Q {
        nest {
          listOfNonNullReturnsNull,
        }
      }
        ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'nest' => [
                    'listOfNonNullReturnsNull' => null
                ]
            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, $this->data(), [], 'Q')->toArray());
    }

    public function testHandlesNonNullListsOfWhenTheyReturnNull()
    {
        $doc = '
      query Q {
        nest {
          nonNullListReturnsNull,
        }
      }
        ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'nest' => null,
            ],
            'errors' => [
                FormattedError::create(
                    'Cannot return null for non-nullable type.',
                    [new SourceLocation(4, 11)]
                )
            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, $this->data(), [], 'Q')->toArray());
    }

    public function testHandlesNonNullListsOfNonNullsWhenTheyReturnNull()
    {
        $doc = '
      query Q {
        nest {
          nonNullListOfNonNullReturnsNull,
        }
      }
        ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'nest' => null
            ],
            'errors' => [
                FormattedError::create(
                    'Cannot return null for non-nullable type.',
                    [new SourceLocation(4, 11)]
                )
            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema(), $ast, $this->data(), [], 'Q')->toArray());
    }



    private function schema()
    {
        $dataType = new ObjectType([
            'name' => 'DataType',
            'fields' => [
                'list' => [
                    'type' => Type::listOf(Type::int())
                ],
                'listOfNonNull' => [
                    'type' => Type::listOf(Type::nonNull(Type::int()))
                ],
                'nonNullList' => [
                    'type' => Type::nonNull(Type::listOf(Type::int()))
                ],
                'nonNullListOfNonNull' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull(Type::int())))
                ],
                'listContainsNull' => [
                    'type' => Type::listOf(Type::int())
                ],
                'listOfNonNullContainsNull' => [
                    'type' => Type::listOf(Type::nonNull(Type::int())),
                ],
                'nonNullListContainsNull' => [
                    'type' => Type::nonNull(Type::listOf(Type::int()))
                ],
                'nonNullListOfNonNullContainsNull' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull(Type::int())))
                ],
                'listReturnsNull' => [
                    'type' => Type::listOf(Type::int())
                ],
                'listOfNonNullReturnsNull' => [
                    'type' => Type::listOf(Type::nonNull(Type::int()))
                ],
                'nonNullListReturnsNull' => [
                    'type' => Type::nonNull(Type::listOf(Type::int()))
                ],
                'nonNullListOfNonNullReturnsNull' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull(Type::int())))
                ],
                'nest' => ['type' => function () use (&$dataType) {
                    return $dataType;
                }]
            ]
        ]);

        $schema = new Schema($dataType);
        return $schema;
    }

    private function data()
    {
        return [
            'list' => function () {
                return [1, 2];
            },
            'listOfNonNull' => function () {
                return [1, 2];
            },
            'nonNullList' => function () {
                return [1, 2];
            },
            'nonNullListOfNonNull' => function () {
                return [1, 2];
            },
            'listContainsNull' => function () {
                return [1, null, 2];
            },
            'listOfNonNullContainsNull' => function () {
                return [1, null, 2];
            },
            'nonNullListContainsNull' => function () {
                return [1, null, 2];
            },
            'nonNullListOfNonNullContainsNull' => function () {
                return [1, null, 2];
            },
            'listReturnsNull' => function () {
                return null;
            },
            'listOfNonNullReturnsNull' => function () {
                return null;
            },
            'nonNullListReturnsNull' => function () {
                return null;
            },
            'nonNullListOfNonNullReturnsNull' => function () {
                return null;
            },
            'nest' => function () {
                return self::data();
            }
        ];
    }
}