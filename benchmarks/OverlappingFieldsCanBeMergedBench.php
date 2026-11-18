<?php declare(strict_types=1);

namespace GraphQL\Benchmarks;

use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Validator\DocumentValidator;
use Tally\Attribute\Expected;
use Tally\Attribute\Warmup;

#[Warmup(1)]
class OverlappingFieldsCanBeMergedBench
{
    private Schema $schema;

    public function __construct()
    {
        $this->schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'hello' => ['type' => Type::string()],
                ],
            ]),
        ]);
    }

    #[Expected(instructions: '24.15M', cycles: '28.13M', memory: '152.3KiB')]
    public function benchRepeatedFields100(): void
    {
        $this->validateRepeatedFields(100);
    }

    #[Expected(instructions: '118.6M', cycles: '137.9M', memory: '547.6KiB')]
    public function benchRepeatedFields500(): void
    {
        $this->validateRepeatedFields(500);
    }

    #[Expected(instructions: '239.1M', cycles: '277.9M', memory: '1.018MiB')]
    public function benchRepeatedFields1000(): void
    {
        $this->validateRepeatedFields(1000);
    }

    #[Expected(instructions: '478.8M', cycles: '557.1M', memory: '1.977MiB')]
    public function benchRepeatedFields2000(): void
    {
        $this->validateRepeatedFields(2000);
    }

    #[Expected(instructions: '714.4M', cycles: '831.9M', memory: '3.019MiB')]
    public function benchRepeatedFields3000(): void
    {
        $this->validateRepeatedFields(3000);
    }

    private function validateRepeatedFields(int $count): void
    {
        $query = '{ ' . str_repeat('hello ', $count) . '}';
        $document = Parser::parse($query);
        DocumentValidator::validate($this->schema, $document);
    }
}
