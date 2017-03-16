<?php
namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\OperationDefinitionNode;
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
     * @deprecated as of 8.0 (Renamed to $fieldNodes)
     */
    public $fieldASTs;

    /**
     * @var FieldNode[]
     */
    public $fieldNodes;

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
     * @var FragmentDefinitionNode[]
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
        $this->fieldASTs = $this->fieldNodes;
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

        /** @var FieldNode $fieldNode */
        foreach ($this->fieldNodes as $fieldNode) {
            $fields = array_merge_recursive($fields, $this->foldSelectionSet($fieldNode->selectionSet, $depth));
        }

        return $fields;
    }

    private function foldSelectionSet(SelectionSetNode $selectionSet, $descend)
    {
        $fields = [];

        foreach ($selectionSet->selections as $selectionNode) {
            if ($selectionNode instanceof FieldNode) {
                $fields[$selectionNode->name->value] = $descend > 0 && !empty($selectionNode->selectionSet)
                    ? $this->foldSelectionSet($selectionNode->selectionSet, $descend - 1)
                    : true;
            } else if ($selectionNode instanceof FragmentSpreadNode) {
                $spreadName = $selectionNode->name->value;
                if (isset($this->fragments[$spreadName])) {
                    /** @var FragmentDefinitionNode $fragment */
                    $fragment = $this->fragments[$spreadName];
                    $fields = array_merge_recursive($this->foldSelectionSet($fragment->selectionSet, $descend), $fields);
                }
            } else if ($selectionNode instanceof InlineFragmentNode) {
                $fields = array_merge_recursive($this->foldSelectionSet($selectionNode->selectionSet, $descend), $fields);
            }
        }

        return $fields;
    }

    public function __get($name)
    {
        if ('fieldASTs' === $name) {
            trigger_error('Property ' . __CLASS__ . '->fieldASTs was renamed to ' . __CLASS__ . '->fieldNodes', E_USER_DEPRECATED);
            return $this->fieldNodes;
        }
        throw new InvariantViolation("Undefined property '$name' in class " . __CLASS__);
    }
}
