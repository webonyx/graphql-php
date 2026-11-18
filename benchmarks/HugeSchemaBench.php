<?php declare(strict_types=1);

namespace GraphQL\Benchmarks;

use GraphQL\Benchmarks\Utils\QueryGenerator;
use GraphQL\Benchmarks\Utils\SchemaGenerator;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use Tally\Attribute\Before;
use Tally\Attribute\Warmup;

#[Warmup(1)]
class HugeSchemaBench
{
    private SchemaGenerator $schemaGenerator;

    private Schema $schema;

    private string $smallQuery;

    #[Before]
    public function setUp(): void
    {
        mt_srand(1);
        $this->schemaGenerator = new SchemaGenerator([
            'totalTypes' => 600,
            'fieldsPerType' => 8,
            'listFieldsPerType' => 2,
            'nestingLevel' => 10,
        ]);

        $this->schema = $this->schemaGenerator->buildSchema();

        $queryBuilder = new QueryGenerator($this->schema, 0.05);
        $this->smallQuery = $queryBuilder->buildQuery();
    }

    public function benchSchema(): void
    {
        $this->schemaGenerator
            ->buildSchema()
            ->getTypeMap();
    }

    public function benchSchemaLazy(): void
    {
        $this->createLazySchema();
    }

    public function benchSmallQuery(): void
    {
        GraphQL::executeQuery($this->schema, $this->smallQuery);
    }

    public function benchSmallQueryLazy(): void
    {
        $schema = $this->createLazySchema();
        GraphQL::executeQuery($schema, $this->smallQuery);
    }

    private function createLazySchema(): Schema
    {
        return new Schema(
            (new SchemaConfig())
                ->setQuery($this->schemaGenerator->buildQueryType())
                ->setTypeLoader(fn (string $name): Type => $this->schemaGenerator->loadType($name))
        );
    }
}
