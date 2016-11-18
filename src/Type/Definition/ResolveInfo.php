<?php
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Schema;
use GraphQL\Utils;

/**
 * Class ResolveInfo
 * @package GraphQL\Type\Definition
 */
class ResolveInfo
{
    /**
     * @var string
     */
    public $fieldName;

    /**
     * @var FieldNode[]
     */
    public $fieldASTs;

    /**
     * @var OutputType
     */
    public $returnType;

    /**
     * @var Type|CompositeType
     */
    public $parentType;

    /**
     * @var array
     */
    public $path;

    /**
     * @var Schema
     */
    public $schema;

    /**
     * @var array<fragmentName, FragmentDefinition>
     */
    public $fragments;

    /**
     * @var mixed
     */
    public $rootValue;

    /**
     * @var OperationDefinitionNode
     */
    public $operation;

    /**
     * @var array<variableName, mixed>
     */
    public $variableValues;

    public function __construct(array $values)
    {
        Utils::assign($this, $values);
    }

    /**
     * Helper method that returns names of all fields selected in query for $this->fieldName up to $depth levels
     *
     *
     * query AppHomeRoute{viewer{id,..._0c28183ce}} fragment _0c28183ce on Viewer{id,profile{firstName,id,locations{id}}}
     * Example:
     * query MyQuery{
     * {
     *   root {
     *     id,
     *     nested {
     *      nested1
     *      nested2 {
     *        nested3
     *      }
     *     }
     *   }
     * }
     *
     * Given this ResolveInfo instance is a part of "root" field resolution, and $depth === 1, method will return:
     * [
     *     'id' => true,
     *     'nested' => [
     *         nested1 => true,
     *         nested2 => true
     *     ]
     * ]
     *
     * @param int $depth How many levels to include in output
     * @return array
     */
    public function getFieldSelection($depth = 0)
    {
        $fields = [];

        /** @var FieldNode $fieldAST */
        foreach ($this->fieldASTs as $fieldAST) {
            $fields = array_merge_recursive($fields, $this->foldSelectionSet($fieldAST->selectionSet, $depth));
        }

        return $fields;
    }

    private function foldSelectionSet(SelectionSetNode $selectionSet, $descend)
    {
        $fields = [];

        foreach ($selectionSet->selections as $selectionAST) {
            if ($selectionAST instanceof FieldNode) {
                $fields[$selectionAST->name->value] = $descend > 0 && !empty($selectionAST->selectionSet)
                    ? $this->foldSelectionSet($selectionAST->selectionSet, $descend - 1)
                    : true;
            } else if ($selectionAST instanceof FragmentSpreadNode) {
                $spreadName = $selectionAST->name->value;
                if (isset($this->fragments[$spreadName])) {
                    /** @var FragmentDefinitionNode $fragment */
                    $fragment = $this->fragments[$spreadName];
                    $fields += $this->foldSelectionSet($fragment->selectionSet, $descend);
                }
            }
        }

        return $fields;
    }
}
