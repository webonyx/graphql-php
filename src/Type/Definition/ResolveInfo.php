<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;
use function array_merge_recursive;

/**
 * Structure containing information useful for field resolution process.
 * Passed as 3rd argument to every field resolver. See [docs on field resolving (data fetching)](data-fetching.md).
 */
class ResolveInfo
{
    /**
     * The name of the field being resolved
     *
     * @api
     * @var string|null
     */
    public $fieldName;

    /**
     * AST of all nodes referencing this field in the query.
     *
     * @api
     * @var FieldNode[]|null
     */
    public $fieldNodes;

    /**
     * Expected return type of the field being resolved
     *
     * @api
     * @var ScalarType|ObjectType|InterfaceType|UnionType|EnumType|ListOfType|NonNull
     */
    public $returnType;

    /**
     * Parent type of the field being resolved
     *
     * @api
     * @var ObjectType|null
     */
    public $parentType;

    /**
     * Path to this field from the very root value
     *
     * @api
     * @var mixed[]|null
     */
    public $path;

    /**
     * Instance of a schema used for execution
     *
     * @api
     * @var Schema|null
     */
    public $schema;

    /**
     * AST of all fragments defined in query
     *
     * @api
     * @var FragmentDefinitionNode[]|null
     */
    public $fragments;

    /**
     * Root value passed to query execution
     *
     * @api
     * @var mixed|null
     */
    public $rootValue;

    /**
     * AST of operation definition node (query, mutation)
     *
     * @api
     * @var OperationDefinitionNode|null
     */
    public $operation;

    /**
     * Array of variables passed to query execution
     *
     * @api
     * @var mixed[]|null
     */
    public $variableValues;

    /**
     * @param mixed[] $values
     */
    public function __construct(array $values)
    {
        Utils::assign($this, $values);
    }

    /**
     * Helper method that returns names of all fields selected in query for
     * $this->fieldName up to $depth levels
     *
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
     * Given this ResolveInfo instance is a part of "root" field resolution, and $depth === 1,
     * method will return:
     * [
     *     'id' => true,
     *     'nested' => [
     *         nested1 => true,
     *         nested2 => true
     *     ]
     * ]
     *
     * Warning: this method it is a naive implementation which does not take into account
     * conditional typed fragments. So use it with care for fields of interface and union types.
     *
     * @param int $depth How many levels to include in output
     *
     * @return bool[]
     *
     * @api
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

    /**
     * @return bool[]
     */
    private function foldSelectionSet(SelectionSetNode $selectionSet, int $descend) : array
    {
        $fields = [];

        foreach ($selectionSet->selections as $selectionNode) {
            if ($selectionNode instanceof FieldNode) {
                $fields[$selectionNode->name->value] = $descend > 0 && ! empty($selectionNode->selectionSet)
                    ? $this->foldSelectionSet($selectionNode->selectionSet, $descend - 1)
                    : true;
            } elseif ($selectionNode instanceof FragmentSpreadNode) {
                $spreadName = $selectionNode->name->value;
                if (isset($this->fragments[$spreadName])) {
                    /** @var FragmentDefinitionNode $fragment */
                    $fragment = $this->fragments[$spreadName];
                    $fields   = array_merge_recursive(
                        $this->foldSelectionSet($fragment->selectionSet, $descend),
                        $fields
                    );
                }
            } elseif ($selectionNode instanceof InlineFragmentNode) {
                $fields = array_merge_recursive(
                    $this->foldSelectionSet($selectionNode->selectionSet, $descend),
                    $fields
                );
            }
        }

        return $fields;
    }
}
