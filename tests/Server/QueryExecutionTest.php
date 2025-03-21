<?php declare(strict_types=1);

namespace GraphQL\Tests\Server;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Server\Exception\MissingQueryOrQueryIdParameter;
use GraphQL\Server\Helper;
use GraphQL\Server\OperationParams;
use GraphQL\Server\ServerConfig;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\CustomValidationRule;
use GraphQL\Validator\ValidationContext;

final class QueryExecutionTest extends ServerTestCase
{
    use ArraySubsetAsserts;

    private ServerConfig $config;

    public function setUp(): void
    {
        $schema = $this->buildSchema();
        $this->config = ServerConfig::create()
            ->setSchema($schema);
    }

    public function testSimpleQueryExecution(): void
    {
        $query = /** @lang GraphQL */ '{ f1 }';

        $expected = [
            'data' => ['f1' => 'f1'],
        ];

        $this->assertQueryResultEquals($expected, $query);
    }

    /**
     * @param array<string, mixed> $expected
     * @param array<string, mixed>|null $variables
     *
     * @throws \Exception
     */
    private function assertQueryResultEquals(array $expected, string $query, ?array $variables = null, ?string $queryId = null): ExecutionResult
    {
        $result = $this->executeQuery($query, $variables, false, $queryId);
        self::assertArraySubset($expected, $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE));

        return $result;
    }

    /**
     * @param array<string, mixed>|null $variables
     *
     * @throws \Exception
     */
    private function executeQuery(string $query, ?array $variables = null, bool $readonly = false, ?string $queryId = null): ExecutionResult
    {
        $op = OperationParams::create(
            [
                'query' => $query,
                'variables' => $variables,
                'queryId' => $queryId,
            ],
            $readonly
        );

        $result = (new Helper())->executeOperation($this->config, $op);
        self::assertInstanceOf(ExecutionResult::class, $result);

        return $result;
    }

    public function testReturnsSyntaxErrors(): void
    {
        $query = '{f1';

        $result = $this->executeQuery($query);
        self::assertNull($result->data);
        self::assertCount(1, $result->errors);
        self::assertStringContainsString(
            'Syntax Error: Expected Name, found <EOF>',
            $result->errors[0]->getMessage()
        );
    }

    public function testDebugExceptions(): void
    {
        $debugFlag = DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE;
        $this->config->setDebugFlag($debugFlag);

        $query = '
        {
            fieldWithSafeException
            f1
        }
        ';

        $expected = [
            'data' => [
                'fieldWithSafeException' => null,
                'f1' => 'f1',
            ],
            'errors' => [
                [
                    'message' => 'This is the exception we want',
                    'path' => ['fieldWithSafeException'],
                    'extensions' => [
                        'trace' => [],
                    ],
                ],
            ],
        ];

        $result = $this->executeQuery($query)->toArray();
        self::assertArraySubset($expected, $result);
        self::assertSame(40, $result['errors'][0]['extensions']['line'] ?? null);
        self::assertStringContainsString('tests/Server/ServerTestCase.php', $result['errors'][0]['extensions']['file'] ?? '');
    }

    public function testRethrowUnsafeExceptions(): void
    {
        $this->config->setDebugFlag(DebugFlag::RETHROW_UNSAFE_EXCEPTIONS);
        $executionResult = $this->executeQuery('
        {
            fieldWithUnsafeException
        }
        ');

        $this->expectException(Unsafe::class);
        $executionResult->toArray();
    }

    public function testPassesRootValueAndContext(): void
    {
        $rootValue = 'myRootValue';
        $context = new \stdClass();

        $this->config
            ->setContext($context)
            ->setRootValue($rootValue);

        $query = '
        {
            testContextAndRootValue
        }
        ';

        self::assertFalse(property_exists($context, 'testedRootValue'));
        $this->executeQuery($query);
        self::assertSame($rootValue, $context->testedRootValue);
    }

    public function testPassesVariables(): void
    {
        $variables = ['a' => 'a', 'b' => 'b'];
        $query = '
            query ($a: String!, $b: String!) {
                a: fieldWithArg(arg: $a)
                b: fieldWithArg(arg: $b)
            }
        ';
        $expected = [
            'data' => [
                'a' => 'a',
                'b' => 'b',
            ],
        ];
        $this->assertQueryResultEquals($expected, $query, $variables);
    }

    public function testPassesCustomValidationRules(): void
    {
        $query = '
            {nonExistentField}
        ';
        $expected = [
            'errors' => [
                ['message' => 'Cannot query field "nonExistentField" on type "Query".'],
            ],
        ];

        $this->assertQueryResultEquals($expected, $query);

        $called = false;

        $rules = [
            new CustomValidationRule('SomeRule', static function () use (&$called): array {
                $called = true;

                return [];
            }),
        ];

        $this->config->setValidationRules($rules);
        $expected = [
            'data' => [],
        ];
        $this->assertQueryResultEquals($expected, $query);
        self::assertTrue($called);
    }

    public function testAllowsValidationRulesAsClosure(): void
    {
        $called = false;
        $params = $doc = $operationType = null;

        $this->config->setValidationRules(static function ($p, $d, $o) use (&$called, &$params, &$doc, &$operationType): array {
            $called = true;
            $params = $p;
            $doc = $d;
            $operationType = $o;

            return [];
        });

        self::assertFalse($called);
        $this->executeQuery('{f1}');
        self::assertTrue($called); // @phpstan-ignore-line value is mutable
        self::assertInstanceOf(OperationParams::class, $params);
        self::assertInstanceOf(DocumentNode::class, $doc);
        self::assertSame('query', $operationType);
    }

    public function testAllowsDifferentValidationRulesDependingOnOperation(): void
    {
        $q1 = '{f1}';
        $q2 = '{invalid}';
        $called1 = false;
        $called2 = false;

        $this->config->setValidationRules(static function (OperationParams $params) use ($q1, &$called1, &$called2): array {
            if ($params->query === $q1) {
                $called1 = true;

                return DocumentValidator::allRules();
            }

            $called2 = true;

            return [
                new CustomValidationRule('MyRule', static function (ValidationContext $context): array {
                    $context->reportError(new Error('This is the error we are looking for!'));

                    return [];
                }),
            ];
        });

        $expected = ['data' => ['f1' => 'f1']];
        $this->assertQueryResultEquals($expected, $q1);
        self::assertTrue($called1);
        self::assertFalse($called2);

        $called1 = false;
        $called2 = false;
        $expected = ['errors' => [['message' => 'This is the error we are looking for!']]];
        $this->assertQueryResultEquals($expected, $q2);
        self::assertFalse($called1); // @phpstan-ignore-line value is mutable
        self::assertTrue($called2); // @phpstan-ignore-line value is mutable
    }

    public function testAllowsSkippingValidation(): void
    {
        $this->config->setValidationRules([]);
        $query = '{nonExistentField}';
        $expected = ['data' => []];
        $this->assertQueryResultEquals($expected, $query);
    }

    public function testPersistedQueriesAreDisabledByDefault(): void
    {
        $result = $this->executePersistedQuery('some-id');

        $expected = [
            'errors' => [
                ['message' => 'Persisted queries are not supported by this server'],
            ],
        ];
        self::assertSame($expected, $result->toArray());
    }

    /**
     * @param array<string, mixed>|null $variables
     *
     * @throws \Exception
     */
    private function executePersistedQuery(string $queryId, ?array $variables = null): ExecutionResult
    {
        $op = OperationParams::create([
            'queryId' => $queryId,
            'variables' => $variables,
        ]);

        $result = (new Helper())->executeOperation($this->config, $op);
        self::assertInstanceOf(ExecutionResult::class, $result);

        return $result;
    }

    public function testBatchedQueriesAreDisabledByDefault(): void
    {
        $batch = [
            ['query' => '{invalid}'],
            ['query' => '{f1,fieldWithSafeException}'],
        ];

        $result = $this->executeBatchedQuery($batch);

        $expected = [
            [
                'errors' => [
                    ['message' => 'Batched queries are not supported by this server'],
                ],
            ],
            [
                'errors' => [
                    ['message' => 'Batched queries are not supported by this server'],
                ],
            ],
        ];

        self::assertSame($expected[0], $result[0]->toArray());
        self::assertSame($expected[1], $result[1]->toArray());
    }

    /**
     * @param array<array<string, mixed>> $qs
     *
     * @throws \Exception
     *
     * @return array<int, ExecutionResult>
     */
    private function executeBatchedQuery(array $qs): array
    {
        $batch = [];
        foreach ($qs as $params) {
            $batch[] = OperationParams::create($params);
        }

        $result = (new Helper())->executeBatch($this->config, $batch);

        self::assertIsArray($result);
        self::assertCount(count($qs), $result);

        return $result;
    }

    public function testMutationsAreNotAllowedInReadonlyMode(): void
    {
        $mutation = 'mutation { a }';

        $expected = [
            'errors' => [
                ['message' => 'GET supports only query operation'],
            ],
        ];

        $result = $this->executeQuery($mutation, null, true);
        self::assertSame($expected, $result->toArray());
    }

    public function testAllowsPersistedQueries(): void
    {
        $called = false;
        $this->config->setPersistedQueryLoader(static function ($queryId, OperationParams $params) use (&$called): string {
            $called = true;
            self::assertSame('some-id', $queryId);

            return '{f1}';
        });

        $result = $this->executePersistedQuery('some-id');
        self::assertTrue($called);

        $expected = [
            'data' => ['f1' => 'f1'],
        ];
        self::assertSame($expected, $result->toArray());

        // Make sure it allows returning document node:
        $called = false;
        $this->config->setPersistedQueryLoader(static function ($queryId, OperationParams $params) use (&$called): DocumentNode {
            $called = true;
            self::assertSame('some-id', $queryId);

            return Parser::parse('{f1}');
        });
        $result = $this->executePersistedQuery('some-id');
        self::assertTrue($called);
        self::assertSame($expected, $result->toArray());
    }

    public function testProhibitsInvalidPersistedQueryLoader(): void
    {
        // @phpstan-ignore-next-line purposefully wrong
        $this->config->setPersistedQueryLoader(static fn (): array => ['err' => 'err']);

        $this->expectExceptionObject(new InvariantViolation(
            'Persisted query loader must return query string or instance of GraphQL\Language\AST\DocumentNode but got: {"err":"err"}'
        ));
        $this->executePersistedQuery('some-id');
    }

    public function testPersistedQueriesAreStillValidatedByDefault(): void
    {
        $this->config->setPersistedQueryLoader(static fn (): string => '{ invalid }');
        $result = $this->executePersistedQuery('some-id');
        $expected = [
            'errors' => [
                [
                    'message' => 'Cannot query field "invalid" on type "Query".',
                    'locations' => [['line' => 1, 'column' => 3]],
                ],
            ],
        ];
        self::assertSame($expected, $result->toArray());
    }

    public function testAllowSkippingValidationForPersistedQueries(): void
    {
        $this->config
            ->setPersistedQueryLoader(
                static fn (string $queryId): string => $queryId === 'some-id'
                ? '{invalid}'
                : '{invalid2}'
            )
            ->setValidationRules(
                static fn (OperationParams $params): array => $params->queryId === 'some-id'
                ? []
                : DocumentValidator::allRules()
            );

        $result = $this->executePersistedQuery('some-id');
        $expected = [
            'data' => [],
        ];
        self::assertSame($expected, $result->toArray());

        $result = $this->executePersistedQuery('some-other-id');
        $expected = [
            'errors' => [
                [
                    'message' => 'Cannot query field "invalid2" on type "Query".',
                    'locations' => [['line' => 1, 'column' => 2]],
                ],
            ],
        ];
        self::assertSame($expected, $result->toArray());
    }

    public function testLoadsPersistedQueryWhenQueryAndQueryIdArePassed(): void
    {
        $query = /** @lang GraphQL */ '{ f1 }';

        $expected = [
            'errors' => [
                [
                    'message' => 'Cannot query field "invalid" on type "Query".',
                    'locations' => [['line' => 1, 'column' => 3]],
                ],
            ],
        ];
        $this->config->setPersistedQueryLoader(static function (string $queryId, OperationParams $params) use ($query): string {
            self::assertSame($query, $params->query);

            return /** @lang GraphQL */ '{ invalid }';
        });

        $this->assertQueryResultEquals($expected, $query, [], 'some-id');
    }

    public function testProhibitsUnexpectedValidationRules(): void
    {
        // @phpstan-ignore-next-line purposefully wrong
        $this->config->setValidationRules(static fn (): \stdClass => new \stdClass());

        $this->expectExceptionObject(new InvariantViolation(
            'Expecting validation rules to be array or callable returning array, but got: instance of stdClass'
        ));
        $this->executeQuery('{ f1 }');
    }

    public function testExecutesBatchedQueries(): void
    {
        $this->config->setQueryBatching(true);

        $batch = [
            ['query' => '{invalid}'],
            ['query' => '{f1,fieldWithSafeException}'],
            [
                'query' => '
                    query ($a: String!, $b: String!) {
                        a: fieldWithArg(arg: $a)
                        b: fieldWithArg(arg: $b)
                    }
                ',
                'variables' => ['a' => 'a', 'b' => 'b'],
            ],
        ];

        $result = $this->executeBatchedQuery($batch);

        $expected = [
            [
                'errors' => [['message' => 'Cannot query field "invalid" on type "Query".']],
            ],
            [
                'data' => [
                    'f1' => 'f1',
                    'fieldWithSafeException' => null,
                ],
                'errors' => [
                    ['message' => 'This is the exception we want'],
                ],
            ],
            [
                'data' => [
                    'a' => 'a',
                    'b' => 'b',
                ],
            ],
        ];

        self::assertArraySubset($expected[0], $result[0]->toArray());
        self::assertArraySubset($expected[1], $result[1]->toArray());
        self::assertArraySubset($expected[2], $result[2]->toArray());
    }

    public function testDeferredsAreSharedAmongAllBatchedQueries(): void
    {
        $batch = [
            ['query' => '{dfd(num: 1)}'],
            ['query' => '{dfd(num: 2)}'],
            ['query' => '{dfd(num: 3)}'],
        ];

        $calls = [];

        $this->config
            ->setQueryBatching(true)
            ->setRootValue('1')
            ->setContext([
                'buffer' => static function ($num) use (&$calls): void {
                    $calls[] = "buffer: {$num}";
                },
                'load' => static function ($num) use (&$calls): string {
                    $calls[] = "load: {$num}";

                    return "loaded: {$num}";
                },
            ]);

        $result = $this->executeBatchedQuery($batch);

        $expectedCalls = [
            'buffer: 1',
            'buffer: 2',
            'buffer: 3',
            'load: 1',
            'load: 2',
            'load: 3',
        ];
        self::assertSame($expectedCalls, $calls);

        $expected = [
            [
                'data' => ['dfd' => 'loaded: 1'],
            ],
            [
                'data' => ['dfd' => 'loaded: 2'],
            ],
            [
                'data' => ['dfd' => 'loaded: 3'],
            ],
        ];

        self::assertSame($expected[0], $result[0]->toArray());
        self::assertSame($expected[1], $result[1]->toArray());
        self::assertSame($expected[2], $result[2]->toArray());
    }

    public function testValidatesParamsBeforeExecution(): void
    {
        $op = OperationParams::create(['queryBad' => '{f1}']);
        $helper = new Helper();
        $result = $helper->executeOperation($this->config, $op);
        self::assertInstanceOf(ExecutionResult::class, $result);

        self::assertEquals(null, $result->data);
        self::assertCount(1, $result->errors);

        self::assertSame(
            'GraphQL Request must include at least one of those two parameters: "query" or "queryId"',
            $result->errors[0]->getMessage()
        );

        self::assertInstanceOf(
            MissingQueryOrQueryIdParameter::class,
            $result->errors[0]->getPrevious()
        );
    }

    public function testAllowsContextAsClosure(): void
    {
        $called = false;
        $params = $doc = $operationType = null;

        $this->config->setContext(static function ($p, $d, $o) use (&$called, &$params, &$doc, &$operationType): void {
            $called = true;
            $params = $p;
            $doc = $d;
            $operationType = $o;
        });

        self::assertFalse($called);
        $this->executeQuery('{f1}');
        self::assertTrue($called); // @phpstan-ignore-line value is mutable
        self::assertInstanceOf(OperationParams::class, $params);
        self::assertInstanceOf(DocumentNode::class, $doc);
        self::assertSame('query', $operationType);
    }

    public function testAllowsRootValueAsClosure(): void
    {
        $called = false;
        $params = $doc = $operationType = null;

        $this->config->setRootValue(static function ($p, $d, $o) use (&$called, &$params, &$doc, &$operationType): void {
            $called = true;
            $params = $p;
            $doc = $d;
            $operationType = $o;
        });

        self::assertFalse($called);
        $this->executeQuery('{f1}');
        self::assertTrue($called); // @phpstan-ignore-line value is mutable
        self::assertInstanceOf(OperationParams::class, $params);
        self::assertInstanceOf(DocumentNode::class, $doc);
        self::assertSame('query', $operationType);
    }

    public function testAppliesErrorFormatter(): void
    {
        $called = false;
        $error = null;
        $formattedError = ['message' => 'formatted'];
        $this->config->setErrorFormatter(static function ($e) use (&$called, &$error, $formattedError): array {
            $called = true;
            $error = $e;

            return $formattedError;
        });

        $result = $this->executeQuery('{fieldWithSafeException}');
        self::assertFalse($called);
        $formatted = $result->toArray();
        $expected = [
            'errors' => [
                $formattedError,
            ],
        ];
        self::assertTrue($called); // @phpstan-ignore-line value is mutable
        self::assertArraySubset($expected, $formatted);
        self::assertInstanceOf(Error::class, $error);

        // Assert debugging still works even with custom formatter
        $formatted = $result->toArray(DebugFlag::INCLUDE_TRACE);
        $expected = [
            'errors' => [
                array_merge(
                    $formattedError,
                    [
                        'extensions' => [
                            'trace' => [],
                        ],
                    ],
                ),
            ],
        ];
        self::assertArraySubset($expected, $formatted);
    }

    public function testAppliesErrorsHandler(): void
    {
        $called = false;
        $errors = null;
        $formatter = null;
        $handledErrors = [
            ['message' => 'handled'],
        ];
        $this->config->setErrorsHandler(static function ($e, $f) use (&$called, &$errors, &$formatter, $handledErrors): array {
            $called = true;
            $errors = $e;
            $formatter = $f;

            return $handledErrors;
        });

        $result = $this->executeQuery('{fieldWithSafeException,test: fieldWithSafeException}');

        self::assertFalse($called);
        $formatted = $result->toArray();
        $expected = [
            'errors' => $handledErrors,
        ];
        self::assertTrue($called); // @phpstan-ignore-line value is mutable
        self::assertArraySubset($expected, $formatted);
        self::assertIsArray($errors);
        self::assertCount(2, $errors);
        self::assertIsCallable($formatter);
        self::assertArraySubset($expected, $formatted);
    }
}
