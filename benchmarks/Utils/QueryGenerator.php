<?php

declare(strict_types=1);

namespace GraphQL\Benchmarks\Utils;

use function count;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;
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

        $this->maxLeafFields = max(1, (int) round($totalFields * $percentOfLeafFields));
        $this->currentLeafFields = 0;
    }

    public function buildQuery(): string
    {
        $queryType = $this->schema->getQueryType();

        $ast = new DocumentNode([
            'definitions' => new NodeList([
                new OperationDefinitionNode([
                    'name' => new NameNode(['value' => 'TestQuery']),
                    'operation' => 'query',
                    'variableDefinitions' => new NodeList([]),
                    'directives' => new NodeList([]),
                    'selectionSet' => $this->buildSelectionSet($queryType->getFields()),
                ]),
            ]),
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
                'arguments' => new NodeList([]),
                'directives' => new NodeList([]),
            ]),
        ];
        ++$this->currentLeafFields;

        foreach ($fields as $field) {
            if ($this->currentLeafFields >= $this->maxLeafFields) {
                break;
            }

            $type = Type::getNamedType($field->getType());

            if ($type instanceof ObjectType || $type instanceof InterfaceType) {
                $selectionSet = $this->buildSelectionSet($type->getFields());
            } else {
                $selectionSet = null;
                ++$this->currentLeafFields;
            }

            $selections[] = new FieldNode([
                'name' => new NameNode(['value' => $field->name]),
                'selectionSet' => $selectionSet,
                'arguments' => new NodeList([]),
                'directives' => new NodeList([]),
            ]);
        }

        return new SelectionSetNode([
            'selections' => new NodeList($selections),
        ]);
    }
}
