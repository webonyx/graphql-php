<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GraphQL\Deferred;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\UserError;
use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Language\SourceLocation;
use GraphQL\Tests\ErrorHelper;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

final class NonNullTest extends TestCase
{
    use ArraySubsetAsserts;

    public \Exception $syncError;

    public \Exception $syncNonNullError;

    public \Exception $promiseError;

    public \Exception $promiseNonNullError;

    /** @var array<string, callable(): mixed> */
    public array $throwingData;

    /** @var array<string, callable(): mixed> */
    public array $nullingData;

    public Schema $schema;

    public Schema $schemaWithNonNullArg;

    public function setUp(): void
    {
        $this->syncError = new UserError('sync');
        $this->syncNonNullError = new UserError('syncNonNull');
        $this->promiseError = new UserError('promise');
        $this->promiseNonNullError = new UserError('promiseNonNull');

        $this->throwingData = [
            'sync' => function (): void {
                throw $this->syncError;
            },
            'syncNonNull' => function (): void {
                throw $this->syncNonNullError;
            },
            'promise' => fn (): Deferred => new Deferred(function (): void {
                throw $this->promiseError;
            }),
            'promiseNonNull' => fn (): Deferred => new Deferred(function (): void {
                throw $this->promiseNonNullError;
            }),
            'syncNest' => fn (): array => $this->throwingData,
            'syncNonNullNest' => fn (): array => $this->throwingData,
            'promiseNest' => fn (): Deferred => new Deferred(
                fn (): array => $this->throwingData
            ),
            'promiseNonNullNest' => fn (): Deferred => new Deferred(
                fn (): array => $this->throwingData
            ),
        ];

        $this->nullingData = [
            'sync' => static fn () => null,
            'syncNonNull' => static fn () => null,
            'promise' => static fn (): Deferred => new Deferred(
                static fn () => null
            ),
            'promiseNonNull' => static fn (): Deferred => new Deferred(
                static fn () => null
            ),
            'syncNest' => fn (): array => $this->nullingData,
            'syncNonNullNest' => fn (): array => $this->nullingData,
            'promiseNest' => fn (): Deferred => new Deferred(
                fn (): array => $this->nullingData
            ),
            'promiseNonNullNest' => fn (): Deferred => new Deferred(
                fn (): array => $this->nullingData
            ),
        ];

        $dataType = new ObjectType([
            'name' => 'DataType',
            'fields' => static function () use (&$dataType): array {
                assert($dataType instanceof ObjectType);

                return [
                    'sync' => ['type' => Type::string()],
                    'syncNonNull' => ['type' => Type::nonNull(Type::string())],
                    'promise' => Type::string(),
                    'promiseNonNull' => Type::nonNull(Type::string()),
                    'syncNest' => $dataType,
                    'syncNonNullNest' => Type::nonNull($dataType),
                    'promiseNest' => $dataType,
                    'promiseNonNullNest' => Type::nonNull($dataType),
                ];
            },
        ]);

        $this->schema = new Schema(['query' => $dataType]);

        $this->schemaWithNonNullArg = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'withNonNullArg' => [
                        'type' => Type::string(),
                        'args' => [
                            'cannotBeNull' => [
                                'type' => Type::nonNull(Type::string()),
                            ],
                        ],
                        'resolve' => static fn ($value, array $args): ?string => is_string($args['cannotBeNull'])
                            ? "Passed: {$args['cannotBeNull']}"
                            : null,
                    ],
                ],
            ]),
        ]);
    }

    // Execute: handles non-nullable types

    /** @see it('nulls a nullable field that throws synchronously') */
    public function testNullsANullableFieldThatThrowsSynchronously(): void
    {
        $doc = '
      query Q {
        sync
      }
        ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => ['sync' => null],
            'errors' => [
                ErrorHelper::create(
                    $this->syncError->getMessage(),
                    [new SourceLocation(3, 9)]
                ),
            ],
        ];
        self::assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray()
        );
    }

    public function testNullsANullableFieldThatThrowsInAPromise(): void
    {
        $doc = '
      query Q {
        promise
      }
        ';

        $ast = Parser::parse($doc);

        $expected = [
            'data' => ['promise' => null],
            'errors' => [
                ErrorHelper::create(
                    $this->promiseError->getMessage(),
                    [new SourceLocation(3, 9)]
                ),
            ],
        ];

        self::assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray()
        );
    }

    public function testNullsASynchronouslyReturnedObjectThatContainsANonNullableFieldThatThrowsSynchronously(): void
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
            'data' => ['syncNest' => null],
            'errors' => [
                ErrorHelper::create($this->syncNonNullError->getMessage(), [new SourceLocation(4, 11)]),
            ],
        ];
        self::assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray()
        );
    }

    public function testNullsAsynchronouslyReturnedObjectThatContainsANonNullableFieldThatThrowsInAPromise(): void
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
            'data' => ['syncNest' => null],
            'errors' => [
                ErrorHelper::create($this->promiseNonNullError->getMessage(), [new SourceLocation(4, 11)]),
            ],
        ];

        self::assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray()
        );
    }

    public function testNullsAnObjectReturnedInAPromiseThatContainsANonNullableFieldThatThrowsSynchronously(): void
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
            'data' => ['promiseNest' => null],
            'errors' => [
                ErrorHelper::create($this->syncNonNullError->getMessage(), [new SourceLocation(4, 11)]),
            ],
        ];

        self::assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray()
        );
    }

    public function testNullsAnObjectReturnedInAPromiseThatContainsANonNullableFieldThatThrowsInAPromise(): void
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
            'data' => ['promiseNest' => null],
            'errors' => [
                ErrorHelper::create($this->promiseNonNullError->getMessage(), [new SourceLocation(4, 11)]),
            ],
        ];

        self::assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray()
        );
    }

    /** @see it('nulls a complex tree of nullable fields that throw') */
    public function testNullsAComplexTreeOfNullableFieldsThatThrow(): void
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

        $expectedData = [
            'syncNest' => [
                'sync' => null,
                'promise' => null,
                'syncNest' => [
                    'sync' => null,
                    'promise' => null,
                ],
                'promiseNest' => [
                    'sync' => null,
                    'promise' => null,
                ],
            ],
            'promiseNest' => [
                'sync' => null,
                'promise' => null,
                'syncNest' => [
                    'sync' => null,
                    'promise' => null,
                ],
                'promiseNest' => [
                    'sync' => null,
                    'promise' => null,
                ],
            ],
        ];
        $expectedErrors = [
            ErrorHelper::create($this->syncError->getMessage(), [new SourceLocation(4, 11)]),
            ErrorHelper::create($this->syncError->getMessage(), [new SourceLocation(7, 13)]),
            ErrorHelper::create($this->syncError->getMessage(), [new SourceLocation(11, 13)]),
            ErrorHelper::create($this->syncError->getMessage(), [new SourceLocation(16, 11)]),
            ErrorHelper::create($this->syncError->getMessage(), [new SourceLocation(19, 13)]),
            ErrorHelper::create($this->promiseError->getMessage(), [new SourceLocation(5, 11)]),
            ErrorHelper::create($this->promiseError->getMessage(), [new SourceLocation(8, 13)]),
            ErrorHelper::create($this->syncError->getMessage(), [new SourceLocation(23, 13)]),
            ErrorHelper::create($this->promiseError->getMessage(), [new SourceLocation(12, 13)]),
            ErrorHelper::create($this->promiseError->getMessage(), [new SourceLocation(17, 11)]),
            ErrorHelper::create($this->promiseError->getMessage(), [new SourceLocation(20, 13)]),
            ErrorHelper::create($this->promiseError->getMessage(), [new SourceLocation(24, 13)]),
        ];

        $result = Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray();

        self::assertArrayHasKey('data', $result);
        self::assertEquals($expectedData, $result['data']);

        self::assertArrayHasKey('errors', $result);
        self::assertCount(count($expectedErrors), $result['errors']);
        foreach ($expectedErrors as $expectedError) {
            $found = false;
            foreach ($result['errors'] as $error) {
                try {
                    self::assertArraySubset($expectedError, $error);
                    $found = true;
                    break;
                } catch (ExpectationFailedException $e) {
                }
            }

            $safeError = Utils::printSafeJson($expectedError);
            self::assertTrue($found, "Did not find error: {$safeError}");
        }
    }

    public function testNullsTheFirstNullableObjectAfterAFieldThrowsInALongChainOfFieldsThatAreNonNull(): void
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
            'data' => [
                'syncNest' => null,
                'promiseNest' => null,
                'anotherNest' => null,
                'anotherPromiseNest' => null,
            ],
            'errors' => [
                ErrorHelper::create($this->syncNonNullError->getMessage(), [new SourceLocation(8, 19)]),
                ErrorHelper::create($this->syncNonNullError->getMessage(), [new SourceLocation(19, 19)]),
                ErrorHelper::create($this->promiseNonNullError->getMessage(), [new SourceLocation(30, 19)]),
                ErrorHelper::create($this->promiseNonNullError->getMessage(), [new SourceLocation(41, 19)]),
            ],
        ];

        self::assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray()
        );
    }

    public function testNullsANullableFieldThatSynchronouslyReturnsNull(): void
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
        self::assertEquals(
            $expected,
            Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray()
        );
    }

    public function testNullsANullableFieldThatReturnsNullInAPromise(): void
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

        self::assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray()
        );
    }

    public function testNullsASynchronouslyReturnedObjectThatContainsANonNullableFieldThatReturnsNullSynchronously(): void
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
            'data' => ['syncNest' => null],
            'errors' => [
                [
                    'locations' => [['line' => 4, 'column' => 11]],
                    'extensions' => ['debugMessage' => 'Cannot return null for non-nullable field "DataType.syncNonNull".'],
                ],
            ],
        ];
        self::assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE)
        );
    }

    public function testNullsASynchronouslyReturnedObjectThatContainsANonNullableFieldThatReturnsNullInAPromise(): void
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
            'data' => ['syncNest' => null],
            'errors' => [
                [
                    'locations' => [['line' => 4, 'column' => 11]],
                    'extensions' => ['debugMessage' => 'Cannot return null for non-nullable field "DataType.promiseNonNull".'],
                ],
            ],
        ];

        self::assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE)
        );
    }

    public function testNullsAnObjectReturnedInAPromiseThatContainsANonNullableFieldThatReturnsNullSynchronously(): void
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
            'data' => ['promiseNest' => null],
            'errors' => [
                [
                    'locations' => [['line' => 4, 'column' => 11]],
                    'extensions' => ['debugMessage' => 'Cannot return null for non-nullable field "DataType.syncNonNull".'],
                ],
            ],
        ];

        self::assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE)
        );
    }

    public function testNullsAnObjectReturnedInAPromiseThatContainsANonNullableFieldThatReturnsNullInaAPromise(): void
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
            'data' => ['promiseNest' => null],
            'errors' => [
                [
                    'locations' => [['line' => 4, 'column' => 11]],
                    'extensions' => ['debugMessage' => 'Cannot return null for non-nullable field "DataType.promiseNonNull".'],
                ],
            ],
        ];

        self::assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE)
        );
    }

    public function testNullsAComplexTreeOfNullableFieldsThatReturnNull(): void
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
                'syncNest' => [
                    'sync' => null,
                    'promise' => null,
                    'syncNest' => [
                        'sync' => null,
                        'promise' => null,
                    ],
                    'promiseNest' => [
                        'sync' => null,
                        'promise' => null,
                    ],
                ],
                'promiseNest' => [
                    'sync' => null,
                    'promise' => null,
                    'syncNest' => [
                        'sync' => null,
                        'promise' => null,
                    ],
                    'promiseNest' => [
                        'sync' => null,
                        'promise' => null,
                    ],
                ],
            ],
        ];

        $actual = Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray();
        self::assertEquals($expected, $actual);
    }

    /** @see it('nulls the first nullable object after a field in a long chain of non-null fields') */
    public function testNullsTheFirstNullableObjectAfterAFieldReturnsNullInALongChainOfFieldsThatAreNonNull(): void
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
            'data' => [
                'syncNest' => null,
                'promiseNest' => null,
                'anotherNest' => null,
                'anotherPromiseNest' => null,
            ],
            'errors' => [
                [
                    'locations' => [['line' => 8, 'column' => 19]],
                    'extensions' => ['debugMessage' => 'Cannot return null for non-nullable field "DataType.syncNonNull".'],
                ],
                [
                    'locations' => [['line' => 19, 'column' => 19]],
                    'extensions' => ['debugMessage' => 'Cannot return null for non-nullable field "DataType.syncNonNull".'],
                ],
                [
                    'locations' => [['line' => 30, 'column' => 19]],
                    'extensions' => ['debugMessage' => 'Cannot return null for non-nullable field "DataType.promiseNonNull".'],
                ],
                [
                    'locations' => [['line' => 41, 'column' => 19]],
                    'extensions' => ['debugMessage' => 'Cannot return null for non-nullable field "DataType.promiseNonNull".'],
                ],
            ],
        ];

        self::assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE)
        );
    }

    /** @see it('nulls the top level if non-nullable field') */
    public function testNullsTheTopLevelIfSyncNonNullableFieldThrows(): void
    {
        $doc = '
      query Q { syncNonNull }
        ';

        $expected = [
            'errors' => [
                ErrorHelper::create($this->syncNonNullError->getMessage(), [new SourceLocation(2, 17)]),
            ],
        ];
        $actual = Executor::execute($this->schema, Parser::parse($doc), $this->throwingData)->toArray();
        self::assertArraySubset($expected, $actual);
    }

    /**
     * @see describe('Handles non-null argument')
     * @see it('succeeds when passed non-null literal value')
     */
    public function testSucceedsWhenPassedNonNullLiteralValue(): void
    {
        $result = Executor::execute(
            $this->schemaWithNonNullArg,
            Parser::parse('
          query {
            withNonNullArg (cannotBeNull: "literal value")
          }
        ')
        );

        $expected = ['data' => ['withNonNullArg' => 'Passed: literal value']];
        self::assertSame($expected, $result->toArray());
    }

    /** @see it('succeeds when passed non-null variable value') */
    public function testSucceedsWhenPassedNonNullVariableValue(): void
    {
        $result = Executor::execute(
            $this->schemaWithNonNullArg,
            Parser::parse('
          query ($testVar: String!) {
            withNonNullArg (cannotBeNull: $testVar)
          }
        '),
            null,
            null,
            ['testVar' => 'variable value']
        );

        $expected = ['data' => ['withNonNullArg' => 'Passed: variable value']];
        self::assertSame($expected, $result->toArray());
    }

    /** @see it('succeeds when missing variable has default value') */
    public function testSucceedsWhenMissingVariableHasDefaultValue(): void
    {
        $result = Executor::execute(
            $this->schemaWithNonNullArg,
            Parser::parse('
          query ($testVar: String = "default value") {
            withNonNullArg (cannotBeNull: $testVar)
          }
        '),
            null,
            null,
            [] // Intentionally missing variable
        );

        $expected = ['data' => ['withNonNullArg' => 'Passed: default value']];
        self::assertSame($expected, $result->toArray());
    }

    /** @see it('field error when missing non-null arg') */
    public function testFieldErrorWhenMissingNonNullArg(): void
    {
        // Note: validation should identify this issue first (missing args rule)
        // however execution should still protect against this.
        $result = Executor::execute(
            $this->schemaWithNonNullArg,
            Parser::parse('
          query {
            withNonNullArg
          }
        ')
        );

        $expected = [
            'data' => ['withNonNullArg' => null],
            'errors' => [
                [
                    'message' => 'Argument "cannotBeNull" of required type "String!" was not provided.',
                    'locations' => [['line' => 3, 'column' => 13]],
                    'path' => ['withNonNullArg'],
                ],
            ],
        ];
        self::assertEquals($expected, $result->toArray());
    }

    /** @see it('field error when non-null arg provided null') */
    public function testFieldErrorWhenNonNullArgProvidedNull(): void
    {
        // Note: validation should identify this issue first (values of correct
        // type rule) however execution should still protect against this.
        $result = Executor::execute(
            $this->schemaWithNonNullArg,
            Parser::parse('
          query {
            withNonNullArg(cannotBeNull: null)
          }
        ')
        );

        $expected = [
            'data' => ['withNonNullArg' => null],
            'errors' => [
                [
                    'message' => 'Argument "cannotBeNull" of non-null type "String!" must not be null.',
                    'locations' => [['line' => 3, 'column' => 13]],
                    'path' => ['withNonNullArg'],
                ],
            ],
        ];
        self::assertEquals($expected, $result->toArray());
    }

    /** @see it('field error when non-null arg not provided variable value') */
    public function testFieldErrorWhenNonNullArgNotProvidedVariableValue(): void
    {
        // Note: validation should identify this issue first (variables in allowed
        // position rule) however execution should still protect against this.
        $result = Executor::execute(
            $this->schemaWithNonNullArg,
            Parser::parse('
          query ($testVar: String) {
            withNonNullArg(cannotBeNull: $testVar)
          }
        '),
            null,
            null,
            [] // Intentionally missing variable
        );

        $expected = [
            'data' => ['withNonNullArg' => null],
            'errors' => [
                [
                    'message' => 'Argument "cannotBeNull" of required type "String!" was '
                      . 'provided the variable "$testVar" which was not provided a '
                      . 'runtime value.',
                    'locations' => [['line' => 3, 'column' => 42]],
                    'path' => ['withNonNullArg'],
                ],
            ],
        ];
        self::assertEquals($expected, $result->toArray());
    }

    /** @see it('field error when non-null arg provided variable with explicit null value') */
    public function testFieldErrorWhenNonNullArgProvidedVariableWithExplicitNullValue(): void
    {
        $result = Executor::execute(
            $this->schemaWithNonNullArg,
            Parser::parse('
          query ($testVar: String = "default value") {
            withNonNullArg (cannotBeNull: $testVar)
          }
        '),
            null,
            null,
            ['testVar' => null]
        );

        $expected = [
            'data' => ['withNonNullArg' => null],
            'errors' => [
                [
                    'message' => 'Argument "cannotBeNull" of non-null type "String!" must not be null.',
                    'locations' => [['line' => 3, 'column' => 13]],
                    'path' => ['withNonNullArg'],
                ],
            ],
        ];

        self::assertEquals($expected, $result->toArray());
    }

    public function testNullsTheTopLevelIfAsyncNonNullableFieldErrors(): void
    {
        $doc = '
      query Q { promiseNonNull }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'errors' => [
                ErrorHelper::create($this->promiseNonNullError->getMessage(), [new SourceLocation(2, 17)]),
            ],
        ];

        self::assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->throwingData, null, [], 'Q')->toArray()
        );
    }

    public function testNullsTheTopLevelIfSyncNonNullableFieldReturnsNull(): void
    {
        // nulls the top level if sync non-nullable field returns null
        $doc = '
      query Q { syncNonNull }
        ';

        $expected = [
            'errors' => [
                [
                    'locations' => [['line' => 2, 'column' => 17]],
                    'extensions' => ['debugMessage' => 'Cannot return null for non-nullable field "DataType.syncNonNull".'],
                ],
            ],
        ];
        self::assertArraySubset(
            $expected,
            Executor::execute($this->schema, Parser::parse($doc), $this->nullingData)->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE)
        );
    }

    public function testNullsTheTopLevelIfAsyncNonNullableFieldResolvesNull(): void
    {
        $doc = '
      query Q { promiseNonNull }
    ';

        $ast = Parser::parse($doc);

        $expected = [
            'errors' => [
                [
                    'locations' => [['line' => 2, 'column' => 17]],
                    'extensions' => ['debugMessage' => 'Cannot return null for non-nullable field "DataType.promiseNonNull".'],
                ],
            ],
        ];

        self::assertArraySubset(
            $expected,
            Executor::execute($this->schema, $ast, $this->nullingData, null, [], 'Q')->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE)
        );
    }
}
