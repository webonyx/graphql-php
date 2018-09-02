<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use GraphQL\Deferred;
use GraphQL\Error\FormattedError;
use GraphQL\Error\UserError;
use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Language\SourceLocation;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

class NonNullTest extends TestCase
{
    /** @var \Exception */
    public $syncError;

    /** @var \Exception */
    public $syncNonNullError;

    /** @var  \Exception */
    public $promiseError;

    /** @var  \Exception */
    public $promiseNonNullError;

    /** @var callable[] */
    public $throwingData;

    /** @var callable[] */
    public $nullingData;

    /** @var Schema */
    public $schema;

    public function setUp()
    {
        $this->syncError           = new UserError('sync');
        $this->syncNonNullError    = new UserError('syncNonNull');
        $this->promiseError        = new UserError('promise');
        $this->promiseNonNullError = new UserError('promiseNonNull');

        $this->throwingData = [
            'sync'               => function () {
                throw $this->syncError;
            },
            'syncNonNull'        => function () {
                throw $this->syncNonNullError;
            },
            'promise'            => function () {
                return new Deferred(function () {
                    throw $this->promiseError;
                });
            },
            'promiseNonNull'     => function () {
                return new Deferred(function () {
                    throw $this->promiseNonNullError;
                });
            },
            'syncNest'           => function () {
                return $this->throwingData;
            },
            'syncNonNullNest'    => function () {
                return $this->throwingData;
            },
            'promiseNest'        => function () {
                return new Deferred(function () {
                    return $this->throwingData;
                });
            },
            'promiseNonNullNest' => function () {
                return new Deferred(function () {
                    return $this->throwingData;
                });
            },
        ];

        $this->nullingData = [
            'sync'               => function () {
                return null;
            },
            'syncNonNull'        => function () {
                return null;
            },
            'promise'            => function () {
                return new Deferred(function () {
                    return null;
                });
            },
            'promiseNonNull'     => function () {
                return new Deferred(function () {
                    return null;
                });
            },
            'syncNest'           => function () {
                return $this->nullingData;
            },
            'syncNonNullNest'    => function () {
                return $this->nullingData;
            },
            'promiseNest'        => function () {
                return new Deferred(function () {
                    return $this->nullingData;
                });
            },
            'promiseNonNullNest' => function () {
                return new Deferred(function () {
                    return $this->nullingData;
                });
            },
        ];

        $dataType = new ObjectType([
            'name'   => 'DataType',
            'fields' => function () use (&$dataType) {
                return [
                    'sync'               => ['type' => Type::string()],
                    'syncNonNull'        => ['type' => Type::nonNull(Type::string())],
                    'promise'            => Type::string(),
                    'promiseNonNull'     => Type::nonNull(Type::string()),
                    'syncNest'           => $dataType,
                    'syncNonNullNest'    => Type::nonNull($dataType),
                    'promiseNest'        => $dataType,
                    'promiseNonNullNest' => Type::nonNull($dataType),
                ];
            },
        ]);

        $this->schema = new Schema(['query' => $dataType]);
    }

    // Execute: handles non-nullable types

    /**
     * @see it('nulls a nullable field that throws synchronously')
     */
    public function testNullsANullableFieldThatThrowsSynchronously() : void
    {
        $doc = '
      query Q {
        sync
      }
        ';

        $ast = Parser::parse($doc);

        $expected = [
            'data'   => ['sync' => null],
            'errors' => [
                FormattedError::create(
                    $this->syncError->getMessage(),
                    [new SourceLocation(3, 9)]
                ),
            ],
        ];
        $this->assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray()
        );
    }

    public function testNullsANullableFieldThatThrowsInAPromise() : void
    {
        $doc = '
      query Q {
        promise
      }
        ';

        $ast = Parser::parse($doc);

        $expected = [
            'data'   => ['promise' => null],
            'errors' => [
                FormattedError::create(
                    $this->promiseError->getMessage(),
                    [new SourceLocation(3, 9)]
                ),
            ],
        ];

        $this->assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray()
        );
    }

    public function testNullsASynchronouslyReturnedObjectThatContainsANonNullableFieldThatThrowsSynchronously() : void
    {
        // nulls a synchronously returned object that contains a non-nullable field that throws synchronously
        $doc = '
      query Q {
        syncNest {
          syncNonNull,
        }
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data'   => ['syncNest' => null],
            'errors' => [
                FormattedError::create($this->syncNonNullError->getMessage(), [new SourceLocation(4, 11)]),
            ],
        ];
        $this->assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray()
        );
    }

    public function testNullsAsynchronouslyReturnedObjectThatContainsANonNullableFieldThatThrowsInAPromise() : void
    {
        $doc = '
      query Q {
        syncNest {
          promiseNonNull,
        }
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data'   => ['syncNest' => null],
            'errors' => [
                FormattedError::create($this->promiseNonNullError->getMessage(), [new SourceLocation(4, 11)]),
            ],
        ];

        $this->assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray()
        );
    }

    public function testNullsAnObjectReturnedInAPromiseThatContainsANonNullableFieldThatThrowsSynchronously() : void
    {
        $doc = '
      query Q {
        promiseNest {
          syncNonNull,
        }
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data'   => ['promiseNest' => null],
            'errors' => [
                FormattedError::create($this->syncNonNullError->getMessage(), [new SourceLocation(4, 11)]),
            ],
        ];

        $this->assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray()
        );
    }

    public function testNullsAnObjectReturnedInAPromiseThatContainsANonNullableFieldThatThrowsInAPromise() : void
    {
        $doc = '
      query Q {
        promiseNest {
          promiseNonNull,
        }
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data'   => ['promiseNest' => null],
            'errors' => [
                FormattedError::create($this->promiseNonNullError->getMessage(), [new SourceLocation(4, 11)]),
            ],
        ];

        $this->assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray()
        );
    }

    /**
     * @see it('nulls a complex tree of nullable fields that throw')
     */
    public function testNullsAComplexTreeOfNullableFieldsThatThrow() : void
    {
        $doc = '
      query Q {
        syncNest {
          sync
          promise
          syncNest {
            sync
            promise
          }
          promiseNest {
            sync
            promise
          }
        }
        promiseNest {
          sync
          promise
          syncNest {
            sync
            promise
          }
          promiseNest {
            sync
            promise
          }
        }
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data'   => [
                'syncNest'    => [
                    'sync'        => null,
                    'promise'     => null,
                    'syncNest'    => [
                        'sync'    => null,
                        'promise' => null,
                    ],
                    'promiseNest' => [
                        'sync'    => null,
                        'promise' => null,
                    ],
                ],
                'promiseNest' => [
                    'sync'        => null,
                    'promise'     => null,
                    'syncNest'    => [
                        'sync'    => null,
                        'promise' => null,
                    ],
                    'promiseNest' => [
                        'sync'    => null,
                        'promise' => null,
                    ],
                ],
            ],
            'errors' => [
                FormattedError::create($this->syncError->getMessage(), [new SourceLocation(4, 11)]),
                FormattedError::create($this->syncError->getMessage(), [new SourceLocation(7, 13)]),
                FormattedError::create($this->syncError->getMessage(), [new SourceLocation(11, 13)]),
                FormattedError::create($this->syncError->getMessage(), [new SourceLocation(16, 11)]),
                FormattedError::create($this->syncError->getMessage(), [new SourceLocation(19, 13)]),
                FormattedError::create($this->promiseError->getMessage(), [new SourceLocation(5, 11)]),
                FormattedError::create($this->promiseError->getMessage(), [new SourceLocation(8, 13)]),
                FormattedError::create($this->syncError->getMessage(), [new SourceLocation(23, 13)]),
                FormattedError::create($this->promiseError->getMessage(), [new SourceLocation(12, 13)]),
                FormattedError::create($this->promiseError->getMessage(), [new SourceLocation(17, 11)]),
                FormattedError::create($this->promiseError->getMessage(), [new SourceLocation(20, 13)]),
                FormattedError::create($this->promiseError->getMessage(), [new SourceLocation(24, 13)]),
            ],
        ];

        $this->assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray()
        );
    }

    public function testNullsTheFirstNullableObjectAfterAFieldThrowsInALongChainOfFieldsThatAreNonNull() : void
    {
        $doc = '
      query Q {
        syncNest {
          syncNonNullNest {
            promiseNonNullNest {
              syncNonNullNest {
                promiseNonNullNest {
                  syncNonNull
                }
              }
            }
          }
        }
        promiseNest {
          syncNonNullNest {
            promiseNonNullNest {
              syncNonNullNest {
                promiseNonNullNest {
                  syncNonNull
                }
              }
            }
          }
        }
        anotherNest: syncNest {
          syncNonNullNest {
            promiseNonNullNest {
              syncNonNullNest {
                promiseNonNullNest {
                  promiseNonNull
                }
              }
            }
          }
        }
        anotherPromiseNest: promiseNest {
          syncNonNullNest {
            promiseNonNullNest {
              syncNonNullNest {
                promiseNonNullNest {
                  promiseNonNull
                }
              }
            }
          }
        }
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data'   => [
                'syncNest'           => null,
                'promiseNest'        => null,
                'anotherNest'        => null,
                'anotherPromiseNest' => null,
            ],
            'errors' => [
                FormattedError::create($this->syncNonNullError->getMessage(), [new SourceLocation(8, 19)]),
                FormattedError::create($this->syncNonNullError->getMessage(), [new SourceLocation(19, 19)]),
                FormattedError::create($this->promiseNonNullError->getMessage(), [new SourceLocation(30, 19)]),
                FormattedError::create($this->promiseNonNullError->getMessage(), [new SourceLocation(41, 19)]),
            ],
        ];

        $this->assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray()
        );
    }

    public function testNullsANullableFieldThatSynchronouslyReturnsNull() : void
    {
        $doc = '
      query Q {
        sync
      }
        ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => ['sync' => null],
        ];
        $this->assertEquals(
            $expected,
            Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray()
        );
    }

    public function testNullsANullableFieldThatReturnsNullInAPromise() : void
    {
        $doc = '
      query Q {
        promise
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => ['promise' => null],
        ];

        $this->assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray()
        );
    }

    public function testNullsASynchronouslyReturnedObjectThatContainsANonNullableFieldThatReturnsNullSynchronously() : void
    {
        $doc = '
      query Q {
        syncNest {
          syncNonNull,
        }
      }
        ';

        $ast = Parser::parse($doc);

        $expected = [
            'data'   => ['syncNest' => null],
            'errors' => [
                [
                    'debugMessage' => 'Cannot return null for non-nullable field DataType.syncNonNull.',
                    'locations'    => [['line' => 4, 'column' => 11]],
                ],
            ],
        ];
        $this->assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray(true)
        );
    }

    public function testNullsASynchronouslyReturnedObjectThatContainsANonNullableFieldThatReturnsNullInAPromise() : void
    {
        $doc = '
      query Q {
        syncNest {
          promiseNonNull,
        }
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data'   => ['syncNest' => null],
            'errors' => [
                [
                    'debugMessage' => 'Cannot return null for non-nullable field DataType.promiseNonNull.',
                    'locations'    => [['line' => 4, 'column' => 11]],
                ],
            ],
        ];

        $this->assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray(true)
        );
    }

    public function testNullsAnObjectReturnedInAPromiseThatContainsANonNullableFieldThatReturnsNullSynchronously() : void
    {
        $doc = '
      query Q {
        promiseNest {
          syncNonNull,
        }
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data'   => ['promiseNest' => null],
            'errors' => [
                [
                    'debugMessage' => 'Cannot return null for non-nullable field DataType.syncNonNull.',
                    'locations'    => [['line' => 4, 'column' => 11]],
                ],
            ],
        ];

        $this->assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray(true)
        );
    }

    public function testNullsAnObjectReturnedInAPromiseThatContainsANonNullableFieldThatReturnsNullInaAPromise() : void
    {
        $doc = '
      query Q {
        promiseNest {
          promiseNonNull,
        }
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data'   => ['promiseNest' => null],
            'errors' => [
                [
                    'debugMessage' => 'Cannot return null for non-nullable field DataType.promiseNonNull.',
                    'locations'    => [['line' => 4, 'column' => 11]],
                ],
            ],
        ];

        $this->assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray(true)
        );
    }

    public function testNullsAComplexTreeOfNullableFieldsThatReturnNull() : void
    {
        $doc = '
      query Q {
        syncNest {
          sync
          promise
          syncNest {
            sync
            promise
          }
          promiseNest {
            sync
            promise
          }
        }
        promiseNest {
          sync
          promise
          syncNest {
            sync
            promise
          }
          promiseNest {
            sync
            promise
          }
        }
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => [
                'syncNest'    => [
                    'sync'        => null,
                    'promise'     => null,
                    'syncNest'    => [
                        'sync'    => null,
                        'promise' => null,
                    ],
                    'promiseNest' => [
                        'sync'    => null,
                        'promise' => null,
                    ],
                ],
                'promiseNest' => [
                    'sync'        => null,
                    'promise'     => null,
                    'syncNest'    => [
                        'sync'    => null,
                        'promise' => null,
                    ],
                    'promiseNest' => [
                        'sync'    => null,
                        'promise' => null,
                    ],
                ],
            ],
        ];

        $actual = Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray();
        $this->assertEquals($expected, $actual);
    }

    public function testNullsTheFirstNullableObjectAfterAFieldReturnsNullInALongChainOfFieldsThatAreNonNull() : void
    {
        $doc = '
      query Q {
        syncNest {
          syncNonNullNest {
            promiseNonNullNest {
              syncNonNullNest {
                promiseNonNullNest {
                  syncNonNull
                }
              }
            }
          }
        }
        promiseNest {
          syncNonNullNest {
            promiseNonNullNest {
              syncNonNullNest {
                promiseNonNullNest {
                  syncNonNull
                }
              }
            }
          }
        }
        anotherNest: syncNest {
          syncNonNullNest {
            promiseNonNullNest {
              syncNonNullNest {
                promiseNonNullNest {
                  promiseNonNull
                }
              }
            }
          }
        }
        anotherPromiseNest: promiseNest {
          syncNonNullNest {
            promiseNonNullNest {
              syncNonNullNest {
                promiseNonNullNest {
                  promiseNonNull
                }
              }
            }
          }
        }
      }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'data'   => [
                'syncNest'           => null,
                'promiseNest'        => null,
                'anotherNest'        => null,
                'anotherPromiseNest' => null,
            ],
            'errors' => [
                ['debugMessage' => 'Cannot return null for non-nullable field DataType.syncNonNull.', 'locations' => [['line' => 8, 'column' => 19]]],
                ['debugMessage' => 'Cannot return null for non-nullable field DataType.syncNonNull.', 'locations' => [['line' => 19, 'column' => 19]]],
                ['debugMessage' => 'Cannot return null for non-nullable field DataType.promiseNonNull.', 'locations' => [['line' => 30, 'column' => 19]]],
                ['debugMessage' => 'Cannot return null for non-nullable field DataType.promiseNonNull.', 'locations' => [['line' => 41, 'column' => 19]]],
            ],
        ];

        $this->assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray(true)
        );
    }

    /**
     * @see it('nulls the top level if sync non-nullable field throws')
     */
    public function testNullsTheTopLevelIfSyncNonNullableFieldThrows() : void
    {
        $doc = '
      query Q { syncNonNull }
        ';

        $expected = [
            'errors' => [
                FormattedError::create($this->syncNonNullError->getMessage(), [new SourceLocation(2, 17)]),
            ],
        ];
        $actual   = Executor::execute($this->schema, Parser::parse($doc), $this->throwingData)->toArray();
        $this->assertArraySubset($expected, $actual);
    }

    public function testNullsTheTopLevelIfAsyncNonNullableFieldErrors() : void
    {
        $doc = '
      query Q { promiseNonNull }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'errors' => [
                FormattedError::create($this->promiseNonNullError->getMessage(), [new SourceLocation(2, 17)]),
            ],
        ];

        $this->assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray()
        );
    }

    public function testNullsTheTopLevelIfSyncNonNullableFieldReturnsNull() : void
    {
        // nulls the top level if sync non-nullable field returns null
        $doc = '
      query Q { syncNonNull }
        ';

        $expected = [
            'errors' => [
                [
                    'debugMessage' => 'Cannot return null for non-nullable field DataType.syncNonNull.',
                    'locations'    => [['line' => 2, 'column' => 17]],
                ],
            ],
        ];
        $this->assertArraySubset(
            $expected,
            Executor::execute($this->schema, Parser::parse($doc), $this->nullingData)->toArray(true)
        );
    }

    public function testNullsTheTopLevelIfAsyncNonNullableFieldResolvesNull() : void
    {
        $doc = '
      query Q { promiseNonNull }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'errors' => [
                [
                    'debugMessage' => 'Cannot return null for non-nullable field DataType.promiseNonNull.',
                    'locations'    => [['line' => 2, 'column' => 17]],
                ],
            ],
        ];

        $this->assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray(true)
        );
    }
}
