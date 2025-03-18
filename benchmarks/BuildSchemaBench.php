<?php declare(strict_types=1);

namespace GraphQL\Benchmarks;

use GraphQL\Utils\BuildSchema;

/**
 * @BeforeMethods({"makeSchemaString"})
 *
 * @OutputTimeUnit("milliseconds", precision=3)
 *
 * @Warmup(2)
 *
 * @Revs(10)
 *
 * @Iterations(2)
 */
class BuildSchemaBench
{
    private string $schema = /** @lang GraphQL */ <<<GRAPHQL
type Query {
    foo: Foo
}

interface F {
    foo: ID
}

type Foo implements F {
    foo: ID
}

type Bar {
    bar: ID
}
GRAPHQL;

    public function makeSchemaString(): void
    {
        foreach (range(1, 100) as $i) {
            $this->schema .= /** @lang GraphQL */ <<<GRAPHQL
union U{$i} = Foo | Bar

directive @d{$i} on
| QUERY
| MUTATION
| SUBSCRIPTION
| FIELD
| FRAGMENT_DEFINITION
| FRAGMENT_SPREAD
| INLINE_FRAGMENT
| VARIABLE_DEFINITION
| SCHEMA
| SCALAR
| OBJECT
| FIELD_DEFINITION
| ARGUMENT_DEFINITION
| INTERFACE
| UNION
| ENUM
| ENUM_VALUE
| INPUT_OBJECT
| INPUT_FIELD_DEFINITION


GRAPHQL;
        }
    }

    public function benchBuildSchema(): void
    {
        BuildSchema::build($this->schema);
    }
}
