<?php declare(strict_types=1);

namespace GraphQL\Benchmarks;

use GraphQL\GraphQL;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use Tally\Attribute\Before;
use Tally\Attribute\Expected;
use Tally\Attribute\Warmup;

#[Warmup(1)]
class ScalarOverrideBench
{
    private Schema $schemaBaseline;

    private Schema $schemaTypes;

    #[Before]
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

    #[Expected(instructions: '666', cycles: '1.166K', memory: '0B')]
    public function benchGetTypeWithoutOverride(): void
    {
        $this->schemaBaseline->getType('String');
    }

    #[Expected(instructions: '666', cycles: '1.166K', memory: '0B')]
    public function benchGetTypeWithTypesOverride(): void
    {
        $this->schemaTypes->getType('String');
    }

    #[Expected(instructions: '839.3K', cycles: '1.047M', memory: '64.84KiB')]
    public function benchExecuteWithoutOverride(): void
    {
        GraphQL::executeQuery($this->schemaBaseline, '{ greeting }');
    }

    #[Expected(instructions: '839.9K', cycles: '1.054M', memory: '64.84KiB')]
    public function benchExecuteWithTypesOverride(): void
    {
        GraphQL::executeQuery($this->schemaTypes, '{ greeting }');
    }
}
