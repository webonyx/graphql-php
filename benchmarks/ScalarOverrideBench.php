<?php declare(strict_types=1);

namespace GraphQL\Benchmarks;

use GraphQL\GraphQL;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

/**
 * @BeforeMethods({"setUp"})
 *
 * @OutputTimeUnit("milliseconds", precision=3)
 *
 * @Warmup(2)
 *
 * @Revs(100)
 *
 * @Iterations(5)
 */
class ScalarOverrideBench
{
    private Schema $schemaBaseline;

    private Schema $schemaTypeLoader;

    private Schema $schemaTypes;

    public function setUp(): void
    {
        $uppercaseString = new CustomScalarType([
            'name' => Type::STRING,
            'serialize' => static fn ($value): string => strtoupper((string) $value),
        ]);

        $queryTypeBaseline = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'greeting' => [
                    'type' => Type::string(),
                    'resolve' => static fn (): string => 'hello world',
                ],
            ],
        ]);
        $this->schemaBaseline = new Schema([
            'query' => $queryTypeBaseline,
        ]);

        $queryTypeLoader = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'greeting' => [
                    'type' => Type::string(),
                    'resolve' => static fn (): string => 'hello world',
                ],
            ],
        ]);
        $typesForLoader = ['Query' => $queryTypeLoader, 'String' => $uppercaseString];
        $this->schemaTypeLoader = new Schema([
            'query' => $queryTypeLoader,
            'typeLoader' => static fn (string $name): ?Type => $typesForLoader[$name] ?? null,
        ]);

        $queryTypeTypes = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'greeting' => [
                    'type' => Type::string(),
                    'resolve' => static fn (): string => 'hello world',
                ],
            ],
        ]);
        $this->schemaTypes = new Schema([
            'query' => $queryTypeTypes,
            'types' => [$uppercaseString],
        ]);
    }

    public function benchGetTypeWithoutOverride(): void
    {
        $this->schemaBaseline->getType('String');
    }

    public function benchGetTypeWithTypeLoaderOverride(): void
    {
        $this->schemaTypeLoader->getType('String');
    }

    public function benchGetTypeWithTypesOverride(): void
    {
        $this->schemaTypes->getType('String');
    }

    public function benchExecuteWithoutOverride(): void
    {
        GraphQL::executeQuery($this->schemaBaseline, '{ greeting }');
    }

    public function benchExecuteWithTypeLoaderOverride(): void
    {
        GraphQL::executeQuery($this->schemaTypeLoader, '{ greeting }');
    }

    public function benchExecuteWithTypesOverride(): void
    {
        GraphQL::executeQuery($this->schemaTypes, '{ greeting }');
    }
}
