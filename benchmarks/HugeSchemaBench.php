<?php
namespace GraphQL\Benchmarks;

use GraphQL\GraphQL;
use GraphQL\Schema;
use GraphQL\Benchmarks\Utils\QueryGenerator;
use GraphQL\Benchmarks\Utils\SchemaGenerator;
use GraphQL\Type\LazyResolution;

/**
 * @BeforeMethods({"setUp"})
 * @OutputTimeUnit("milliseconds", precision=3)
 * @Warmup(1)
 * @Revs(5)
 * @Iterations(1)
 */
class HugeSchemaBench
{
    /**
     * @var SchemaGenerator
     */
    private $schemaBuilder;

    /**
     * @var array
     */
    private $descriptor;

    private $schema;

    private $lazySchema;

    /**
     * @var string
     */
    private $smallQuery;

    public function setUp()
    {
        $this->schemaBuilder = new SchemaGenerator([
            'totalTypes' => 600,
            'fieldsPerType' => 8,
            'listFieldsPerType' => 2,
            'nestingLevel' => 10
        ]);

        $this->schema = $this->schemaBuilder->buildSchema();

        $queryBuilder = new QueryGenerator($this->schema, 0.05);
        $this->descriptor = $this->schema->getDescriptor();
        $this->smallQuery = $queryBuilder->buildQuery();
    }

    public function benchSchema()
    {
        $this->schemaBuilder
            ->buildSchema();
    }

    public function benchSchemaLazy()
    {
        $this->createLazySchema();
    }

    public function benchSmallQuery()
    {
        $result = GraphQL::execute($this->schema, $this->smallQuery);
    }

    public function benchSmallQueryLazy()
    {
        $schema = $this->createLazySchema();
        $result = GraphQL::execute($schema, $this->smallQuery);
    }

    private function createLazySchema()
    {
        $strategy = new LazyResolution(
            $this->descriptor,
            function($name) {
                return $this->schemaBuilder->loadType($name);
            }
        );

        return new Schema([
            'query' => $this->schemaBuilder->buildQueryType(),
            'typeResolution' => $strategy,
        ]);
    }
}
