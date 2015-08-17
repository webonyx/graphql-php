<?php
namespace GraphQL\Executor;

use GraphQL\Error;
use GraphQL\FormattedError;
use GraphQL\Language\Parser;
use GraphQL\Language\SourceLocation;
use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class NonNullTest extends \PHPUnit_Framework_TestCase
{
    /** @var Error */
    public $syncError;
    public $nonNullSyncError;
    public $throwingData;
    public $nullingData;
    public $schema;

    public function setUp()
    {
        $this->syncError = new Error('sync');
        $this->nonNullSyncError = new Error('nonNullSync');

        $this->throwingData = [
            'sync' => function () {
                throw $this->syncError;
            },
            'nonNullSync' => function () {
                throw $this->nonNullSyncError;
            },
            'nest' => function () {
                return $this->throwingData;
            },
            'nonNullNest' => function () {
                return $this->throwingData;
            },
        ];

        $this->nullingData = [
            'sync' => function () {
                return null;
            },
            'nonNullSync' => function () {
                return null;
            },
            'nest' => function () {
                return $this->nullingData;
            },
            'nonNullNest' => function () {
                return $this->nullingData;
            },
        ];

        $dataType = new ObjectType([
            'name' => 'DataType',
            'fields' => [
                'sync' => ['type' => Type::string()],
                'nonNullSync' => ['type' => Type::nonNull(Type::string())],
                'nest' => ['type' => function () use (&$dataType) {
                    return $dataType;
                }],
                'nonNullNest' => ['type' => function () use (&$dataType) {
                    return Type::nonNull($dataType);
                }]
            ]
        ]);

        $this->schema = new Schema($dataType);
    }

    // Execute: handles non-nullable types
    public function testNullsANullableFieldThatThrowsSynchronously()
    {
        $doc = '
      query Q {
        sync
      }
        ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'sync' => null,
            ],
            'errors' => [
                FormattedError::create(
                    $this->syncError->message,
                    [new SourceLocation(3, 9)]
                )
            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema, $ast, $this->throwingData, [], 'Q')->toArray());
    }

    public function testNullsASynchronouslyReturnedObjectThatContainsANonNullableFieldThatThrowsSynchronously()
    {
        // nulls a synchronously returned object that contains a non-nullable field that throws synchronously
        $doc = '
      query Q {
        nest {
          nonNullSync,
        }
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'nest' => null
            ],
            'errors' => [
                FormattedError::create($this->nonNullSyncError->message, [new SourceLocation(4, 11)])
            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema, $ast, $this->throwingData, [], 'Q')->toArray());
    }

    public function testNullsAComplexTreeOfNullableFieldsThatThrow()
    {
        $doc = '
      query Q {
        nest {
          sync
          nest {
            sync
          }
        }
      }
        ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'nest' => [
                    'sync' => null,
                    'nest' => [
                        'sync' => null,
                    ]
                ]
            ],
            'errors' => [
                FormattedError::create($this->syncError->message, [new SourceLocation(4, 11)]),
                FormattedError::create($this->syncError->message, [new SourceLocation(6, 13)]),
            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema, $ast, $this->throwingData, [], 'Q')->toArray());
    }

    public function testNullsANullableFieldThatSynchronouslyReturnsNull()
    {
        $doc = '
      query Q {
        sync
      }
        ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'sync' => null,
            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema, $ast, $this->nullingData, [], 'Q')->toArray());
    }

    public function test4()
    {
        // nulls a synchronously returned object that contains a non-nullable field that returns null synchronously
        $doc = '
      query Q {
        nest {
          nonNullSync,
        }
      }
        ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'nest' => null
            ],
            'errors' => [
                FormattedError::create('Cannot return null for non-nullable type.', [new SourceLocation(4, 11)])
            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema, $ast, $this->nullingData, [], 'Q')->toArray());
    }

    public function test5()
    {
        // nulls a complex tree of nullable fields that return null

        $doc = '
      query Q {
        nest {
          sync
          nest {
            sync
            nest {
              sync
            }
          }
        }
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'nest' => [
                    'sync' => null,
                    'nest' => [
                        'sync' => null,
                        'nest' => [
                            'sync' => null
                        ]
                    ],
                ],
            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema, $ast, $this->nullingData, [], 'Q')->toArray());
    }

    public function testNullsTheTopLevelIfSyncNonNullableFieldThrows()
    {
        $doc = '
      query Q { nonNullSync }
        ';

        $expected = [
            'data' => null,
            'errors' => [
                FormattedError::create($this->nonNullSyncError->message, [new SourceLocation(2, 17)])
            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema, Parser::parse($doc), $this->throwingData)->toArray());
    }

    public function testNullsTheTopLevelIfSyncNonNullableFieldReturnsNull()
    {
        // nulls the top level if sync non-nullable field returns null
        $doc = '
      query Q { nonNullSync }
        ';

        $expected = [
            'data' => null,
            'errors' => [
                FormattedError::create('Cannot return null for non-nullable type.', [new SourceLocation(2, 17)]),
            ]
        ];
        $this->assertEquals($expected, Executor::execute($this->schema, Parser::parse($doc), $this->nullingData)->toArray());
    }
}
