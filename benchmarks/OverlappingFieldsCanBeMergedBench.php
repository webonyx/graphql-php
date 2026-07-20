<?php declare(strict_types=1);

namespace GraphQL\Benchmarks;

use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Validator\DocumentValidator;

/**
 * @OutputTimeUnit("milliseconds", precision=3)
 *
 * @Warmup(2)
 *
 * @Revs(1)
 *
 * @Iterations(5)
 */
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

    public function benchRepeatedFields100(): void
    {
        $this->validateRepeatedFields(100);
    }

    public function benchRepeatedFields500(): void
    {
        $this->validateRepeatedFields(500);
    }

    public function benchRepeatedFields1000(): void
    {
        $this->validateRepeatedFields(1000);
    }

    public function benchRepeatedFields2000(): void
    {
        $this->validateRepeatedFields(2000);
    }

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
