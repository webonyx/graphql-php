<?php

declare(strict_types=1);

namespace GraphQL\Benchmarks\Utils;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\WrappingType;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;

use function count;
use function max;
use function round;

class QueryGenerator
{
    private Schema $schema;

    private int $maxLeafFields;

    private int $currentLeafFields;

    public function __construct(Schema $schema, float $percentOfLeafFields)
    {
        $this->schema = $schema;

        Utils::invariant(0 < $percentOfLeafFields && $percentOfLeafFields <= 1);

        $totalFields = 0;
        foreach ($schema->getTypeMap() as $type) {
            if (! ($type instanceof ObjectType)) {
                continue;
            }

            $totalFields += count($type->getFieldNames());
        }

        $this->maxLeafFields     = max(1, (int) round($totalFields * $percentOfLeafFields));
        $this->currentLeafFields = 0;
    }

    public function buildQuery(): string
    {
        $qtype = $this->schema->getQueryType();

        $ast = new DocumentNode([
            'definitions' => [
                new OperationDefinitionNode([
                    'name' => new NameNode(['value' => 'TestQuery']),
                    'operation' => 'query',
                    'selectionSet' => $this->buildSelectionSet($qtype->getFields()),
                ]),
            ],
        ]);

        return Printer::doPrint($ast);
    }

    /**
     * @param array<FieldDefinition> $fields
     */
    public function buildSelectionSet(array $fields): SelectionSetNode
    {
        $selections = [
            new FieldNode([
                'name' => new NameNode(['value' => '__typename']),
            ]),
        ];
        $this->currentLeafFields++;

        foreach ($fields as $field) {
            if ($this->currentLeafFields >= $this->maxLeafFields) {
                break;
            }

            $type = $field->getType();

            if ($type instanceof WrappingType) {
                $type = $type->getWrappedType(true);
            }

            if ($type instanceof ObjectType || $type instanceof InterfaceType) {
                $selectionSet = $this->buildSelectionSet($type->getFields());
            } else {
                $selectionSet = null;
                $this->currentLeafFields++;
            }

            $selections[] = new FieldNode([
                'name' => new NameNode(['value' => $field->name]),
                'selectionSet' => $selectionSet,
            ]);
        }

        return new SelectionSetNode(['selections' => $selections]);
    }
}
