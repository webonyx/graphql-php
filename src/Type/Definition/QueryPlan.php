<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\Error;
use GraphQL\Executor\Values;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Type\Schema;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_merge_recursive;
use function array_unique;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function is_numeric;

class QueryPlan
{
    /** @var string[][] */
    private $types = [];

    /** @var Schema */
    private $schema;

    /** @var mixed[] */
    private $queryPlan = [];

    /** @var mixed[] */
    private $variableValues;

    /** @var FragmentDefinitionNode[] */
    private $fragments;

    /** @var bool */
    private $withTypeConditions;

    /**
     * @param FieldNode[]              $fieldNodes
     * @param mixed[]                  $variableValues
     * @param FragmentDefinitionNode[] $fragments
     * @param string[]                 $options
     */
    public function __construct(ObjectType $parentType, Schema $schema, iterable $fieldNodes, array $variableValues, array $fragments, array $options)
    {
        $this->schema             = $schema;
        $this->variableValues     = $variableValues;
        $this->fragments          = $fragments;
        $this->withTypeConditions = in_array('with-type-conditions', $options, true);
        $this->analyzeQueryPlan($parentType, $fieldNodes);
    }

    /**
     * @return mixed[]
     */
    public function queryPlan() : array
    {
        return $this->queryPlan;
    }

    /**
     * @return string[]
     */
    public function getReferencedTypes() : array
    {
        return array_keys($this->types);
    }

    public function hasType(string $type) : bool
    {
        return count(array_filter($this->getReferencedTypes(), static function (string $referencedType) use ($type) {
            return $type === $referencedType;
        })) > 0;
    }

    /**
     * @return string[]
     */
    public function getReferencedFields() : array
    {
        return array_values(array_unique(array_merge(...array_values($this->types))));
    }

    public function hasField(string $field) : bool
    {
        return count(array_filter($this->getReferencedFields(), static function (string $referencedField) use ($field) {
            return $field === $referencedField;
        })) > 0;
    }

    /**
     * @return string[]
     */
    public function subFields(string $typename) : array
    {
        if (! array_key_exists($typename, $this->types)) {
            return [];
        }

        return $this->types[$typename];
    }

    /**
     * @param FieldNode[] $fieldNodes
     */
    private function analyzeQueryPlan(ObjectType $parentType, iterable $fieldNodes) : void
    {
        $queryPlan = [];

        /** @var FieldNode $fieldNode */
        foreach ($fieldNodes as $fieldNode) {
            if (! $fieldNode->selectionSet) {
                continue;
            }

            $type = $parentType->getField($fieldNode->name->value)->getType();
            if ($type instanceof WrappingType) {
                $type = $type->getWrappedType();
            }

            $selectionSet = $fieldNode->selectionSet;
            $data         = $this->withTypeConditions ? $this->analyzeSelectionSetWithTypeConditions($selectionSet, $type) : $this->analyzeSelectionSet($selectionSet, $type);

            $this->types[$type->name] = array_unique(array_merge(
                array_key_exists($type->name, $this->types) ? $this->types[$type->name] : [],
                array_keys($this->withTypeConditions ? $data['fields'] : $data)
            ));

            $queryPlan = array_merge_recursive(
                $queryPlan,
                $data
            );
        }

        $this->queryPlan = $queryPlan;
    }

    /**
     * @param InterfaceType|ObjectType $parentType
     *
     * @return mixed[]
     *
     * @throws Error
     */
    private function analyzeSelectionSetWithTypeConditions(SelectionSetNode $selectionSet, Type $parentType) : array
    {
        $fields         = [];
        $typeConditions = [];

        foreach ($selectionSet->selections as $selectionNode) {
            if ($selectionNode instanceof FieldNode && ($parentType instanceof InterfaceType || $parentType instanceof ObjectType)) {
                $fieldName     = $selectionNode->name->value;
                $type          = $parentType->getField($fieldName);
                $selectionType = $type->getType();

                $fieldData         = & $fields[$fieldName];
                $fieldData['type'] = $selectionType;
                $fieldData['args'] = Values::getArgumentValues($type, $selectionNode, $this->variableValues);
                $fieldData        += $selectionNode->selectionSet ? $this->analyzeSubFields($selectionType, $selectionNode->selectionSet) : ['fields' => []];
            } elseif ($selectionNode instanceof FragmentSpreadNode) {
                $spreadName = $selectionNode->name->value;
                $fragment   = $this->fragments[$spreadName] ?? null;
                if ($fragment) {
                    $type = $this->schema->getType($fragment->typeCondition->name->value);

                    $typeConditions[$type->name] = $this->arrayMergeDeep(
                        $typeConditions[$type->name] ?? [],
                        $this->analyzeSubFields($type, $fragment->selectionSet)
                    );
                }
            } elseif ($selectionNode instanceof InlineFragmentNode) {
                $type = $this->schema->getType($selectionNode->typeCondition->name->value);

                $typeConditions[$type->name] = $this->arrayMergeDeep(
                    $typeConditions[$type->name] ?? [],
                    $this->analyzeSubFields($type, $selectionNode->selectionSet)
                );
            }
        }
        unset($fieldData);

        return ['fields' => $fields] + array_filter(['typeConditions' => $typeConditions]);
    }

    /**
     * @param InterfaceType|ObjectType $parentType
     *
     * @return mixed[]
     *
     * @throws Error
     */
    private function analyzeSelectionSet(SelectionSetNode $selectionSet, Type $parentType) : array
    {
        $fields = [];

        foreach ($selectionSet->selections as $selectionNode) {
            if ($selectionNode instanceof FieldNode && ($parentType instanceof InterfaceType || $parentType instanceof ObjectType)) {
                $fieldName     = $selectionNode->name->value;
                $type          = $parentType->getField($fieldName);
                $selectionType = $type->getType();

                $fieldData           = & $fields[$fieldName];
                $fieldData['type']   = $selectionType;
                $fieldData['args']   = Values::getArgumentValues($type, $selectionNode, $this->variableValues);
                $fieldData['fields'] = $selectionNode->selectionSet ? $this->analyzeSubFields($selectionType, $selectionNode->selectionSet) : [];
            } elseif ($selectionNode instanceof FragmentSpreadNode) {
                $spreadName = $selectionNode->name->value;
                $fragment   = $this->fragments[$spreadName] ?? null;
                if ($fragment) {
                    $type = $this->schema->getType($fragment->typeCondition->name->value);

                    $fields = $this->arrayMergeDeep(
                        $this->analyzeSubFields($type, $fragment->selectionSet),
                        $fields
                    );
                }
            } elseif ($selectionNode instanceof InlineFragmentNode) {
                $type = $this->schema->getType($selectionNode->typeCondition->name->value);

                $fields = $this->arrayMergeDeep(
                    $this->analyzeSubFields($type, $selectionNode->selectionSet),
                    $fields
                );
            }
        }
        unset($fieldData);

        return $fields;
    }

    /**
     * @return mixed[]
     */
    private function analyzeSubFields(Type $type, SelectionSetNode $selectionSet) : array
    {
        if ($type instanceof WrappingType) {
            $type = $type->getWrappedType();
        }

        if (! $this->withTypeConditions && ! $type instanceof ObjectType) {
            return [];
        }

        $data = $this->withTypeConditions ? $this->analyzeSelectionSetWithTypeConditions($selectionSet, $type) : $this->analyzeSelectionSet($selectionSet, $type);

        $this->types[$type->name] = array_unique(array_merge(
            array_key_exists($type->name, $this->types) ? $this->types[$type->name] : [],
            array_keys($this->withTypeConditions ? $data['fields'] : $data)
        ));

        return $data;
    }

    /**
     * similar to array_merge_recursive this merges nested arrays, but handles non array values differently
     * while array_merge_recursive tries to merge non array values, in this implementation they will be overwritten
     *
     * @see https://stackoverflow.com/a/25712428
     *
     * @param mixed[] $array1
     * @param mixed[] $array2
     *
     * @return mixed[]
     */
    private function arrayMergeDeep(array $array1, array $array2) : array
    {
        $merged = $array1;

        foreach ($array2 as $key => & $value) {
            if (is_numeric($key)) {
                if (! in_array($value, $merged, true)) {
                    $merged[] = $value;
                }
            } elseif (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->arrayMergeDeep($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}
