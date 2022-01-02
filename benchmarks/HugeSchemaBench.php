<?php
namespace GraphQL\Benchmarks;

use GraphQL\Benchmarks\Utils\QueryGenerator;
use GraphQL\Benchmarks\Utils\SchemaGenerator;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;

/**
 * @BeforeMethods({"setUp"})
 * @OutputTimeUnit("milliseconds", precision=3)
 * @Warmup(1)
 * @Revs(5)
 * @Iterations(1)
 */
class HugeSchemaBench
{
    /** @var SchemaGenerator */
    private $schemaGenerator;

    /** @var Schema */
    private $schema;

    /** @var string */
    private $smallQuery;

    public function setUp()
    {
        $this->schemaGenerator = new SchemaGenerator([
            'totalTypes' => 600,
            'fieldsPerType' => 8,
            'listFieldsPerType' => 2,
            'nestingLevel' => 10,
        ]);

        $this->schema = $this->schemaGenerator->buildSchema();

        $queryBuilder     = new QueryGenerator($this->schema, 0.05);
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
            SchemaConfig::create()
                ->setQuery($this->schemaGenerator->buildQueryType())
                ->setTypeLoader(function (string $name): Type {
                    return $this->schemaGenerator->loadType($name);
                })
        );
    }
}
