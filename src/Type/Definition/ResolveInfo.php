<?php
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\FragmentDefinition;
use GraphQL\Language\AST\FragmentSpread;
use GraphQL\Language\AST\OperationDefinition;
use GraphQL\Language\AST\Selection;
use GraphQL\Language\AST\SelectionSet;
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
     * @var Field[]
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
     * @var OperationDefinition
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

        /** @var Field $fieldAST */
        foreach ($this->fieldASTs as $fieldAST) {
            $fields = array_merge_recursive($fields, $this->foldSelectionSet($fieldAST->getSelectionSet(), $depth));
        }

        return $fields;
    }

    private function foldSelectionSet(SelectionSet $selectionSet, $descend)
    {
        $fields = [];

        foreach ($selectionSet->getSelections() as $selectionAST) {
            if ($selectionAST instanceof Field) {
                $fields[$selectionAST->getName()->getValue()] = $descend > 0 && !empty($selectionAST->getSelectionSet())
                    ? $this->foldSelectionSet($selectionAST->getSelectionSet(), $descend - 1)
                    : true;
            } else if ($selectionAST instanceof FragmentSpread) {
                $spreadName = $selectionAST->getName()->getValue();
                if (isset($this->fragments[$spreadName])) {
                    /** @var FragmentDefinition $fragment */
                    $fragment = $this->fragments[$spreadName];
                    $fields += $this->foldSelectionSet($fragment->getSelectionSet(), $descend);
                }
            }
        }

        return $fields;
    }
}
