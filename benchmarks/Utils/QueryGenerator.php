<?php
namespace GraphQL\Benchmarks\Utils;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\Printer;
use GraphQL\Schema;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\WrappingType;
use GraphQL\Utils\Utils;

class QueryGenerator
{
    private $schema;

    private $maxLeafFields;

    private $currentLeafFields;

    public function __construct(Schema $schema, $percentOfLeafFields)
    {
        $this->schema = $schema;

        Utils::invariant(0 < $percentOfLeafFields && $percentOfLeafFields <= 1);

        $totalFields = 0;
        foreach ($schema->getTypeMap() as $type) {
            if ($type instanceof ObjectType) {
                $totalFields += count($type->getFields());
            }
        }

        $this->maxLeafFields = max(1, round($totalFields * $percentOfLeafFields));
        $this->currentLeafFields = 0;
    }

    public function buildQuery()
    {
        $qtype = $this->schema->getQueryType();

        $ast = new DocumentNode([
            'definitions' => [
                new OperationDefinitionNode([
                    'name' => new NameNode(['value' => 'TestQuery']),
                    'operation' => 'query',
                    'selectionSet' => $this->buildSelectionSet($qtype->getFields())
                ])
            ]
        ]);

        return Printer::doPrint($ast);
    }

    /**
     * @param FieldDefinition[] $fields
     * @return SelectionSetNode
     */
    public function buildSelectionSet($fields)
    {
        $selections[] = new FieldNode([
            'name' => new NameNode(['value' => '__typename'])
        ]);
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
                'selectionSet' => $selectionSet
            ]);
        }

        $selectionSet = new SelectionSetNode([
            'selections' => $selections
        ]);

        return $selectionSet;
    }
}
