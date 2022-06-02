<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GraphQL\Deferred;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\UserError;
use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\OutputType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

/**
 * @see describe('Execute: Handles list nullability', () => {
 */
class ListsTest extends TestCase
{
    use ArraySubsetAsserts;

    // Describe: Execute: Handles list nullability

    /**
     * [T].
     */
    public function testHandlesNullableListsWithArray(): void
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

    /**
     * @param mixed                $testData
     * @param array<string, mixed> $expected
     */
    private function checkHandlesNullableLists($testData, array $expected): void
    {
        $testType = Type::listOf(Type::int());
        $this->check($testType, $testData, $expected);
    }

    /**
     * @param Type                 &OutputType $testType
     * @param mixed                $testData
     * @param array<string, mixed> $expected
     */
    private function check(Type $testType, $testData, array $expected, int $debug = DebugFlag::NONE): void
    {
        $data = ['test' => $testData];
        $dataType = null;

        $dataType = new ObjectType([
            'name' => 'DataType',
            'fields' => static function () use (&$testType, &$dataType, $data): array {
                return [
                    'test' => ['type' => $testType],
                    'nest' => [
                        'type' => $dataType,
                        'resolve' => static function () use ($data): array {
                            return $data;
                        },
                    ],
                ];
            },
        ]);

        $schema = new Schema(['query' => $dataType]);

        $ast = Parser::parse('{ nest { test } }');

        $result = Executor::execute($schema, $ast, $data);
        self::assertArraySubset($expected, $result->toArray($debug));
    }

    /**
     * [T].
     */
    public function testHandlesNullableListsWithPromiseArray(): void
    {
        // Contains values
        $this->checkHandlesNullableLists(
            new Deferred(static function (): array {
                return [1, 2];
            }),
            ['data' => ['nest' => ['test' => [1, 2]]]]
        );

        // Contains null
        $this->checkHandlesNullableLists(
            new Deferred(static function (): array {
                return [1, null, 2];
            }),
            ['data' => ['nest' => ['test' => [1, null, 2]]]]
        );

        // Returns null
        $this->checkHandlesNullableLists(
            new Deferred(static function () {
                return null;
            }),
            ['data' => ['nest' => ['test' => null]]]
        );

        // Rejected
        $this->checkHandlesNullableLists(
            static function (): Deferred {
                return new Deferred(static function (): void {
                    throw new UserError('bad');
                });
            },
            [
                'data' => ['nest' => ['test' => null]],
                'errors' => [
                    [
                        'message' => 'bad',
                        'locations' => [['line' => 1, 'column' => 10]],
                        'path' => ['nest', 'test'],
                    ],
                ],
            ]
        );
    }

    /**
     * [T].
     */
    public function testHandlesNullableListsWithArrayPromise(): void
    {
        // Contains values
        $this->checkHandlesNullableLists(
            [
                new Deferred(static function (): int {
                    return 1;
                }),
                new Deferred(static function (): int {
                    return 2;
                }),
            ],
            ['data' => ['nest' => ['test' => [1, 2]]]]
        );

        // Contains null
        $this->checkHandlesNullableLists(
            [
                new Deferred(static function (): int {
                    return 1;
                }),
                new Deferred(static function () {
                    return null;
                }),
                new Deferred(static function (): int {
                    return 2;
                }),
            ],
            ['data' => ['nest' => ['test' => [1, null, 2]]]]
        );

        // Returns null
        $this->checkHandlesNullableLists(
            new Deferred(static function () {
                return null;
            }),
            ['data' => ['nest' => ['test' => null]]]
        );

        // Contains reject
        $this->checkHandlesNullableLists(
            static function (): array {
                return [
                    new Deferred(static function (): int {
                        return 1;
                    }),
                    new Deferred(static function (): void {
                        throw new UserError('bad');
                    }),
                    new Deferred(static function (): int {
                        return 2;
                    }),
                ];
            },
            [
                'data' => ['nest' => ['test' => [1, null, 2]]],
                'errors' => [
                    [
                        'message' => 'bad',
                        'locations' => [['line' => 1, 'column' => 10]],
                        'path' => ['nest', 'test', 1],
                    ],
                ],
            ]
        );
    }

    /**
     * [T]!
     */
    public function testHandlesNonNullableListsWithArray(): void
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
                'data' => ['nest' => null],
                'errors' => [
                    [
                        'locations' => [['line' => 1, 'column' => 10]],
                        'extensions' => ['debugMessage' => 'Cannot return null for non-nullable field "DataType.test".'],
                    ],
                ],
            ],
            DebugFlag::INCLUDE_DEBUG_MESSAGE
        );
    }

    /**
     * @param mixed                $testData
     * @param array<string, mixed> $expected
     */
    private function checkHandlesNonNullableLists($testData, array $expected, int $debug = DebugFlag::NONE): void
    {
        $testType = Type::nonNull(Type::listOf(Type::int()));
        $this->check($testType, $testData, $expected, $debug);
    }

    /**
     * [T]!
     */
    public function testHandlesNonNullableListsWithPromiseArray(): void
    {
        // Contains values
        $this->checkHandlesNonNullableLists(
            new Deferred(static function (): array {
                return [1, 2];
            }),
            ['data' => ['nest' => ['test' => [1, 2]]]]
        );

        // Contains null
        $this->checkHandlesNonNullableLists(
            new Deferred(static function (): array {
                return [1, null, 2];
            }),
            ['data' => ['nest' => ['test' => [1, null, 2]]]]
        );

        // Returns null
        $this->checkHandlesNonNullableLists(
            null,
            [
                'data' => ['nest' => null],
                'errors' => [
                    [
                        'locations' => [['line' => 1, 'column' => 10]],
                        'extensions' => ['debugMessage' => 'Cannot return null for non-nullable field "DataType.test".'],
                    ],
                ],
            ],
            DebugFlag::INCLUDE_DEBUG_MESSAGE
        );

        // Rejected
        $this->checkHandlesNonNullableLists(
            static function (): Deferred {
                return new Deferred(static function (): void {
                    throw new UserError('bad');
                });
            },
            [
                'data' => ['nest' => null],
                'errors' => [
                    [
                        'message' => 'bad',
                        'locations' => [['line' => 1, 'column' => 10]],
                        'path' => ['nest', 'test'],
                    ],
                ],
            ]
        );
    }

    /**
     * [T]!
     */
    public function testHandlesNonNullableListsWithArrayPromise(): void
    {
        // Contains values
        $this->checkHandlesNonNullableLists(
            [
                new Deferred(static function (): int {
                    return 1;
                }),
                new Deferred(static function (): int {
                    return 2;
                }),
            ],
            ['data' => ['nest' => ['test' => [1, 2]]]]
        );

        // Contains null
        $this->checkHandlesNonNullableLists(
            [
                new Deferred(static function (): int {
                    return 1;
                }),
                new Deferred(static function () {
                    return null;
                }),
                new Deferred(static function (): int {
                    return 2;
                }),
            ],
            ['data' => ['nest' => ['test' => [1, null, 2]]]]
        );

        // Contains reject
        $this->checkHandlesNonNullableLists(
            static function (): array {
                return [
                    new Deferred(static function (): int {
                        return 1;
                    }),
                    new Deferred(static function (): void {
                        throw new UserError('bad');
                    }),
                    new Deferred(static function (): int {
                        return 2;
                    }),
                ];
            },
            [
                'data' => ['nest' => ['test' => [1, null, 2]]],
                'errors' => [
                    [
                        'message' => 'bad',
                        'locations' => [['line' => 1, 'column' => 10]],
                        'path' => ['nest', 'test', 1],
                    ],
                ],
            ]
        );
    }

    /**
     * [T!].
     */
    public function testHandlesListOfNonNullsWithArray(): void
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
                'data' => ['nest' => ['test' => null]],
                'errors' => [
                    [
                        'locations' => [['line' => 1, 'column' => 10]],
                        'extensions' => ['debugMessage' => 'Cannot return null for non-nullable field "DataType.test".'],
                    ],
                ],
            ],
            DebugFlag::INCLUDE_DEBUG_MESSAGE
        );

        // Returns null
        $this->checkHandlesListOfNonNulls(
            null,
            ['data' => ['nest' => ['test' => null]]]
        );
    }

    /**
     * @param mixed                $testData
     * @param array<string, mixed> $expected
     */
    private function checkHandlesListOfNonNulls($testData, array $expected, int $debug = DebugFlag::NONE): void
    {
        $testType = Type::listOf(Type::nonNull(Type::int()));
        $this->check($testType, $testData, $expected, $debug);
    }

    /**
     * [T!].
     */
    public function testHandlesListOfNonNullsWithPromiseArray(): void
    {
        // Contains values
        $this->checkHandlesListOfNonNulls(
            new Deferred(static function (): array {
                return [1, 2];
            }),
            ['data' => ['nest' => ['test' => [1, 2]]]]
        );

        // Contains null
        $this->checkHandlesListOfNonNulls(
            new Deferred(static function (): array {
                return [1, null, 2];
            }),
            [
                'data' => ['nest' => ['test' => null]],
                'errors' => [
                    [
                        'locations' => [['line' => 1, 'column' => 10]],
                        'extensions' => ['debugMessage' => 'Cannot return null for non-nullable field "DataType.test".'],
                    ],
                ],
            ],
            DebugFlag::INCLUDE_DEBUG_MESSAGE
        );

        // Returns null
        $this->checkHandlesListOfNonNulls(
            new Deferred(static function () {
                return null;
            }),
            ['data' => ['nest' => ['test' => null]]]
        );

        // Rejected
        $this->checkHandlesListOfNonNulls(
            static function (): Deferred {
                return new Deferred(static function (): void {
                    throw new UserError('bad');
                });
            },
            [
                'data' => ['nest' => ['test' => null]],
                'errors' => [
                    [
                        'message' => 'bad',
                        'locations' => [['line' => 1, 'column' => 10]],
                        'path' => ['nest', 'test'],
                    ],
                ],
            ]
        );
    }

    /**
     * [T]!
     */
    public function testHandlesListOfNonNullsWithArrayPromise(): void
    {
        // Contains values
        $this->checkHandlesListOfNonNulls(
            [
                new Deferred(static function (): int {
                    return 1;
                }),
                new Deferred(static function (): int {
                    return 2;
                }),
            ],
            ['data' => ['nest' => ['test' => [1, 2]]]]
        );

        // Contains null
        $this->checkHandlesListOfNonNulls(
            [
                new Deferred(static function (): int {
                    return 1;
                }),
                new Deferred(static function () {
                    return null;
                }),
                new Deferred(static function (): int {
                    return 2;
                }),
            ],
            ['data' => ['nest' => ['test' => null]]]
        );

        // Contains reject
        $this->checkHandlesListOfNonNulls(
            static function (): array {
                return [
                    new Deferred(static function (): int {
                        return 1;
                    }),
                    new Deferred(static function (): void {
                        throw new UserError('bad');
                    }),
                    new Deferred(static function (): int {
                        return 2;
                    }),
                ];
            },
            [
                'data' => ['nest' => ['test' => null]],
                'errors' => [
                    [
                        'message' => 'bad',
                        'locations' => [['line' => 1, 'column' => 10]],
                        'path' => ['nest', 'test', 1],
                    ],
                ],
            ]
        );
    }

    /**
     * [T!]!
     */
    public function testHandlesNonNullListOfNonNullsWithArray(): void
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
                'data' => ['nest' => null],
                'errors' => [
                    [
                        'locations' => [['line' => 1, 'column' => 10]],
                        'extensions' => ['debugMessage' => 'Cannot return null for non-nullable field "DataType.test".'],
                    ],
                ],
            ],
            DebugFlag::INCLUDE_DEBUG_MESSAGE
        );

        // Returns null
        $this->checkHandlesNonNullListOfNonNulls(
            null,
            [
                'data' => ['nest' => null],
                'errors' => [
                    [
                        'locations' => [['line' => 1, 'column' => 10]],
                        'extensions' => ['debugMessage' => 'Cannot return null for non-nullable field "DataType.test".'],
                    ],
                ],
            ],
            DebugFlag::INCLUDE_DEBUG_MESSAGE
        );
    }

    /**
     * @param mixed                $testData
     * @param array<string, mixed> $expected
     */
    public function checkHandlesNonNullListOfNonNulls($testData, array $expected, int $debug = DebugFlag::NONE): void
    {
        $testType = Type::nonNull(Type::listOf(Type::nonNull(Type::int())));
        $this->check($testType, $testData, $expected, $debug);
    }

    /**
     * [T!]!
     */
    public function testHandlesNonNullListOfNonNullsWithPromiseArray(): void
    {
        // Contains values
        $this->checkHandlesNonNullListOfNonNulls(
            new Deferred(static function (): array {
                return [1, 2];
            }),
            ['data' => ['nest' => ['test' => [1, 2]]]]
        );

        // Contains null
        $this->checkHandlesNonNullListOfNonNulls(
            new Deferred(static function (): array {
                return [1, null, 2];
            }),
            [
                'data' => ['nest' => null],
                'errors' => [
                    [
                        'locations' => [['line' => 1, 'column' => 10]],
                        'extensions' => ['debugMessage' => 'Cannot return null for non-nullable field "DataType.test".'],
                    ],
                ],
            ],
            DebugFlag::INCLUDE_DEBUG_MESSAGE
        );

        // Returns null
        $this->checkHandlesNonNullListOfNonNulls(
            new Deferred(static function () {
                return null;
            }),
            [
                'data' => ['nest' => null],
                'errors' => [
                    [
                        'locations' => [['line' => 1, 'column' => 10]],
                        'extensions' => ['debugMessage' => 'Cannot return null for non-nullable field "DataType.test".'],
                    ],
                ],
            ],
            DebugFlag::INCLUDE_DEBUG_MESSAGE
        );

        // Rejected
        $this->checkHandlesNonNullListOfNonNulls(
            static function (): Deferred {
                return new Deferred(static function (): void {
                    throw new UserError('bad');
                });
            },
            [
                'data' => ['nest' => null],
                'errors' => [
                    [
                        'message' => 'bad',
                        'locations' => [['line' => 1, 'column' => 10]],
                        'path' => ['nest', 'test'],
                    ],
                ],
            ]
        );
    }

    /**
     * [T!]!
     */
    public function testHandlesNonNullListOfNonNullsWithArrayPromise(): void
    {
        // Contains values
        $this->checkHandlesNonNullListOfNonNulls(
            [
                new Deferred(static function (): int {
                    return 1;
                }),
                new Deferred(static function (): int {
                    return 2;
                }),
            ],
            ['data' => ['nest' => ['test' => [1, 2]]]]
        );

        // Contains null
        $this->checkHandlesNonNullListOfNonNulls(
            [
                new Deferred(static function (): int {
                    return 1;
                }),
                new Deferred(static function () {
                    return null;
                }),
                new Deferred(static function (): int {
                    return 2;
                }),
            ],
            [
                'data' => ['nest' => null],
                'errors' => [
                    [
                        'locations' => [['line' => 1, 'column' => 10]],
                        'extensions' => ['debugMessage' => 'Cannot return null for non-nullable field "DataType.test".'],
                    ],
                ],
            ],
            DebugFlag::INCLUDE_DEBUG_MESSAGE
        );

        // Contains reject
        $this->checkHandlesNonNullListOfNonNulls(
            static function (): array {
                return [
                    new Deferred(static function (): int {
                        return 1;
                    }),
                    new Deferred(static function (): void {
                        throw new UserError('bad');
                    }),
                    new Deferred(static function (): int {
                        return 2;
                    }),
                ];
            },
            [
                'data' => ['nest' => null],
                'errors' => [
                    [
                        'message' => 'bad',
                        'locations' => [['line' => 1, 'column' => 10]],
                        'path' => ['nest', 'test'],
                    ],
                ],
            ]
        );
    }
}
