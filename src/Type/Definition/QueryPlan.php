<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use function array_diff_key;
use function array_filter;
use function array_intersect_key;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_unique;
use function array_values;
use function count;
use GraphQL\Error\Error;
use GraphQL\Executor\Values;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;
use function in_array;
use function is_array;
use function is_numeric;

/**
 * @phpstan-type QueryPlanOptions array{
 *   groupImplementorFields?: bool,
 * }
 */
class QueryPlan
{
    /** @var array<string, array<int, string>> */
    private array $types = [];

    private Schema $schema;

    /** @var array<string, mixed> */
    private array $queryPlan = [];

    /** @var array<string, mixed> */
    private array $variableValues;

    /** @var array<string, FragmentDefinitionNode> */
    private array $fragments;

    private bool $groupImplementorFields;

    /**
     * @param iterable<FieldNode> $fieldNodes
     * @param array<string, mixed> $variableValues
     * @param array<string, FragmentDefinitionNode> $fragments
     * @param QueryPlanOptions $options
     */
    public function __construct(ObjectType $parentType, Schema $schema, iterable $fieldNodes, array $variableValues, array $fragments, array $options = [])
    {
        $this->schema = $schema;
        $this->variableValues = $variableValues;
        $this->fragments = $fragments;
        $this->groupImplementorFields = $options['groupImplementorFields'] ?? false;
        $this->analyzeQueryPlan($parentType, $fieldNodes);
    }

    /**
     * @return array<string, mixed>
     */
    public function queryPlan(): array
    {
        return $this->queryPlan;
    }

    /**
     * @return array<int, string>
     */
    public function getReferencedTypes(): array
    {
        return array_keys($this->types);
    }

    public function hasType(string $type): bool
    {
        return isset($this->types[$type]);
    }

    /**
     * @return array<int, string>
     */
    public function getReferencedFields(): array
    {
        return array_values(array_unique(array_merge(...array_values($this->types))));
    }

    public function hasField(string $field): bool
    {
        return count(
            array_filter(
                $this->getReferencedFields(),
                static fn (string $referencedField): bool => $field === $referencedField
            )
        ) > 0;
    }

    /**
     * @return array<int, string>
     */
    public function subFields(string $typename): array
    {
        return $this->types[$typename] ?? [];
    }

    /**
     * @param iterable<FieldNode> $fieldNodes
     */
    private function analyzeQueryPlan(ObjectType $parentType, iterable $fieldNodes): void
    {
        $queryPlan = [];
        $implementors = [];
        foreach ($fieldNodes as $fieldNode) {
            if (null === $fieldNode->selectionSet) {
                continue;
            }

            $type = Type::getNamedType(
                $parentType->getField($fieldNode->name->value)->getType()
            );
            assert($type instanceof ObjectType || $type instanceof InterfaceType, 'proven because it must be a type with fields and was unwrapped');

            $subfields = $this->analyzeSelectionSet($fieldNode->selectionSet, $type, $implementors);

            $this->types[$type->name] = array_unique(array_merge(
                array_key_exists($type->name, $this->types) ? $this->types[$type->name] : [],
                array_keys($subfields)
            ));

            $queryPlan = $this->arrayMergeDeep(
                $queryPlan,
                $subfields
            );
        }

        if ($this->groupImplementorFields) {
            $this->queryPlan = ['fields' => $queryPlan];

            if ($implementors) {
                $this->queryPlan['implementors'] = $implementors;
            }
        } else {
            $this->queryPlan = $queryPlan;
        }
    }

    /**
     * @param Type&NamedType $parentType
     * @param array<string, mixed> $implementors
     *
     * @throws Error
     *
     * @return array<mixed>
     */
    private function analyzeSelectionSet(SelectionSetNode $selectionSet, Type $parentType, array &$implementors): array
    {
        $fields = [];
        $implementors = [];
        foreach ($selectionSet->selections as $selectionNode) {
            if ($selectionNode instanceof FieldNode) {
                assert($parentType instanceof HasFieldsType, 'ensured by query validation');

                $fieldName = $selectionNode->name->value;

                if (Introspection::TYPE_NAME_FIELD_NAME === $fieldName) {
                    continue;
                }

                $type = $parentType->getField($fieldName);
                $selectionType = $type->getType();

                $subfields = [];
                $subImplementors = [];
                if (isset($selectionNode->selectionSet)) {
                    $subfields = $this->analyzeSubFields($selectionType, $selectionNode->selectionSet, $subImplementors);
                }

                $fields[$fieldName] = [
                    'type' => $selectionType,
                    'fields' => $subfields,
                    'args' => Values::getArgumentValues($type, $selectionNode, $this->variableValues),
                ];
                if ($this->groupImplementorFields && $subImplementors) {
                    $fields[$fieldName]['implementors'] = $subImplementors;
                }
            } elseif ($selectionNode instanceof FragmentSpreadNode) {
                $spreadName = $selectionNode->name->value;
                if (isset($this->fragments[$spreadName])) {
                    $fragment = $this->fragments[$spreadName];
                    $type = $this->schema->getType($fragment->typeCondition->name->value);
                    $subfields = $this->analyzeSubFields($type, $fragment->selectionSet);
                    $fields = $this->mergeFields($parentType, $type, $fields, $subfields, $implementors);
                }
            } elseif ($selectionNode instanceof InlineFragmentNode) {
                $type = $this->schema->getType($selectionNode->typeCondition->name->value);
                $subfields = $this->analyzeSubFields($type, $selectionNode->selectionSet);
                $fields = $this->mergeFields($parentType, $type, $fields, $subfields, $implementors);
            }
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $implementors
     *
     * @return array<mixed>
     */
    private function analyzeSubFields(Type $type, SelectionSetNode $selectionSet, array &$implementors = []): array
    {
        $type = Type::getNamedType($type);

        $subfields = [];
        if ($type instanceof ObjectType || $type instanceof AbstractType) {
            $subfields = $this->analyzeSelectionSet($selectionSet, $type, $implementors);
            $this->types[$type->name] = array_unique(array_merge(
                array_key_exists($type->name, $this->types) ? $this->types[$type->name] : [],
                array_keys($subfields)
            ));
        }

        return $subfields;
    }

    /**
     * @param Type&NamedType $parentType
     * @param Type&NamedType $type
     * @param array<mixed> $fields
     * @param array<mixed> $subfields
     * @param array<string, mixed> $implementors
     *
     * @return array<mixed>
     */
    private function mergeFields(Type $parentType, Type $type, array $fields, array $subfields, array &$implementors): array
    {
        if ($this->groupImplementorFields && $parentType instanceof AbstractType && ! $type instanceof AbstractType) {
            $implementors[$type->name] = [
                'type' => $type,
                'fields' => $this->arrayMergeDeep(
                    $implementors[$type->name]['fields'] ?? [],
                    array_diff_key($subfields, $fields)
                ),
            ];

            $fields = $this->arrayMergeDeep(
                $fields,
                array_intersect_key($subfields, $fields)
            );
        } else {
            $fields = $this->arrayMergeDeep(
                $subfields,
                $fields
            );
        }

        return $fields;
    }

    /**
     * similar to array_merge_recursive this merges nested arrays, but handles non array values differently
     * while array_merge_recursive tries to merge non array values, in this implementation they will be overwritten.
     *
     * @see https://stackoverflow.com/a/25712428
     *
     * @param array<mixed> $array1
     * @param array<mixed> $array2
     *
     * @return array<mixed>
     */
    private function arrayMergeDeep(array $array1, array $array2): array
    {
        $merged = $array1;

        foreach ($array2 as $key => &$value) {
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
