<?php declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\Values;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;

/**
 * Structure containing information useful for field resolution process.
 *
 * Passed as 4th argument to every field resolver. See [docs on field resolving (data fetching)](data-fetching.md).
 *
 * @phpstan-import-type QueryPlanOptions from QueryPlan
 *
 * @phpstan-type Path list<string|int>
 */
class ResolveInfo
{
    /**
     * The definition of the field being resolved.
     *
     * @api
     */
    public FieldDefinition $fieldDefinition;

    /**
     * The name of the field being resolved.
     *
     * @api
     */
    public string $fieldName;

    /**
     * Expected return type of the field being resolved.
     *
     * @api
     */
    public Type $returnType;

    /**
     * AST of all nodes referencing this field in the query.
     *
     * @api
     *
     * @var \ArrayObject<int, FieldNode>
     */
    public \ArrayObject $fieldNodes;

    /**
     * Parent type of the field being resolved.
     *
     * @api
     */
    public ObjectType $parentType;

    /**
     * Path to this field from the very root value. When fields are aliased, the path includes aliases.
     *
     * @api
     *
     * @var list<string|int>
     *
     * @phpstan-var Path
     */
    public array $path;

    /**
     * Path to this field from the very root value. This will never include aliases.
     *
     * @api
     *
     * @var list<string|int>
     *
     * @phpstan-var Path
     */
    public array $unaliasedPath;

    /**
     * Instance of a schema used for execution.
     *
     * @api
     */
    public Schema $schema;

    /**
     * AST of all fragments defined in query.
     *
     * @api
     *
     * @var array<string, FragmentDefinitionNode>
     */
    public array $fragments = [];

    /**
     * Root value passed to query execution.
     *
     * @api
     *
     * @var mixed
     */
    public $rootValue;

    /**
     * AST of operation definition node (query, mutation).
     *
     * @api
     */
    public OperationDefinitionNode $operation;

    /**
     * Array of variables passed to query execution.
     *
     * @api
     *
     * @var array<string, mixed>
     */
    public array $variableValues = [];

    /**
     * @param \ArrayObject<int, FieldNode> $fieldNodes
     * @param list<string|int> $path
     * @param list<string|int> $unaliasedPath
     *
     * @phpstan-param Path $path
     * @phpstan-param Path $unaliasedPath
     *
     * @param array<string, FragmentDefinitionNode> $fragments
     * @param mixed|null $rootValue
     * @param array<string, mixed> $variableValues
     */
    public function __construct(
        FieldDefinition $fieldDefinition,
        \ArrayObject $fieldNodes,
        ObjectType $parentType,
        array $path,
        Schema $schema,
        array $fragments,
        $rootValue,
        OperationDefinitionNode $operation,
        array $variableValues,
        array $unaliasedPath = []
    ) {
        $this->fieldDefinition = $fieldDefinition;
        $this->fieldName = $fieldDefinition->name;
        $this->returnType = $fieldDefinition->getType();
        $this->fieldNodes = $fieldNodes;
        $this->parentType = $parentType;
        $this->path = $path;
        $this->unaliasedPath = $unaliasedPath;
        $this->schema = $schema;
        $this->fragments = $fragments;
        $this->rootValue = $rootValue;
        $this->operation = $operation;
        $this->variableValues = $variableValues;
    }

    /**
     * Helper method that returns names of all fields selected in query for
     * $this->fieldName up to $depth levels.
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
     * @return array<string, mixed>
     *
     * @api
     */
    public function getFieldSelection(int $depth = 0): array
    {
        $fields = [];

        foreach ($this->fieldNodes as $fieldNode) {
            if (isset($fieldNode->selectionSet)) {
                $fields = \array_merge_recursive(
                    $fields,
                    $this->foldSelectionSet($fieldNode->selectionSet, $depth)
                );
            }
        }

        return $fields;
    }

    /**
     * Helper method that returns names of all fields selected in query for
     * $this->fieldName up to $depth levels.
     * For each field there is an "aliases" key regrouping all aliases of this field
     * (if a field is not aliased, its name is present here as a key)
     * For each of those "alias" you can find "args" key containing the arguments of the alias and the "fields" key
     * containing the subfield of this field/alias. Each of those field have the same structure as described above.
     *
     * Example:
     * query MyQuery{
     * {
     *   root {
     *     id,
     *     nested {
     *      nested1(myArg:1)
     *      nested1Bis:nested1
     *     }
     *     alias1:nested {
     *       nested1(myArg:2, mySecondAg:"test")
     *     }
     *   }
     * }
     *
     * Given this ResolveInfo instance is a part of "root" field resolution, and $depth === 1,
     * method will return:
     * [
     *     'id' => [
     *          'aliases' => [
     *              'id' => [
     *                  [args] => []
     *              ]
     *          ]
     *      ],
     *      'nested' => [
     *           'aliases' => [
     *               'nested' => [
     *                  ['args'] => [],
     *                  ['fields'] => [
     *                      'nested1' => [
     *                          'aliases' => [
     *                              'nested1' => [
     *                                  ['args'] => [
     *                                      'myArg' => 1
     *                                  ]
     *                              ],
     *                              'nested1Bis' => [
     *                                  ['args'] => []
     *                              ]
     *                          ]
     *                      ]
     *                  ]
     *               ],
     *               'alias1' => [
     *                  ['args'] => [],
     *                  ['fields'] => [
     *                       'nested1' => [
     *                           'aliases' => [
     *                               'nested1' => [
     *                                   ['args'] => [
     *                                       'myArg' => 2,
     *                                       'mySecondAg' => "test"
     *                                   ]
     *                               ]
     *                           ]
     *                       ]
     *                   ]
     *               ]
     *           ]
     *       ]
     * ]
     *
     * Warning: this method it is a naive implementation which does not take into account
     * conditional typed fragments. So use it with care for fields of interface and union types.
     * You still can alias the union type fields with the same name in order to extract their corresponding args.
     *
     * Example:
     *  query MyQuery{
     *  {
     *    root {
     *      id,
     *      unionPerson {
     *        ...on Child {
     *          name
     *          birthdate(format:"d/m/Y")
     *        }
     *        ...on Adult {
     *          adultName:name
     *          adultBirthDate:birthdate(format:"Y-m-d")
     *          job
     *        }
     *      }
     *    }
     *  }
     *
     * @param int $depth How many levels to include in output
     *
     * @throws \Exception
     * @throws Error
     * @throws InvariantViolation
     *
     * @return array<string, mixed>
     *
     * @api
     */
    public function getFieldSelectionWithAlias(int $depth = 0): array
    {
        $fields = [];

        foreach ($this->fieldNodes as $fieldNode) {
            if (isset($fieldNode->selectionSet)) {
                $fields = \array_merge_recursive(
                    $fields,
                    $this->foldSelectionWithAlias($fieldNode->selectionSet, $depth, $this->parentType->getField($fieldNode->name->value)->getType())
                );
            }
        }

        return $fields;
    }

    /**
     * @param QueryPlanOptions $options
     *
     * @throws \Exception
     * @throws Error
     * @throws InvariantViolation
     */
    public function lookAhead(array $options = []): QueryPlan
    {
        return new QueryPlan(
            $this->parentType,
            $this->schema,
            $this->fieldNodes,
            $this->variableValues,
            $this->fragments,
            $options
        );
    }

    /** @return array<string, bool> */
    private function foldSelectionSet(SelectionSetNode $selectionSet, int $descend): array
    {
        /** @var array<string, bool> $fields */
        $fields = [];

        foreach ($selectionSet->selections as $selectionNode) {
            if ($selectionNode instanceof FieldNode) {
                $fields[$selectionNode->name->value] = $descend > 0 && $selectionNode->selectionSet !== null
                    ? \array_merge_recursive(
                        $fields[$selectionNode->name->value] ?? [],
                        $this->foldSelectionSet($selectionNode->selectionSet, $descend - 1)
                    )
                    : true;
            } elseif ($selectionNode instanceof FragmentSpreadNode) {
                $spreadName = $selectionNode->name->value;
                if (isset($this->fragments[$spreadName])) {
                    $fragment = $this->fragments[$spreadName];
                    $fields = \array_merge_recursive(
                        $this->foldSelectionSet($fragment->selectionSet, $descend),
                        $fields
                    );
                }
            } elseif ($selectionNode instanceof InlineFragmentNode) {
                $fields = \array_merge_recursive(
                    $this->foldSelectionSet($selectionNode->selectionSet, $descend),
                    $fields
                );
            }
        }

        return $fields;
    }

    /**
     * @throws InvariantViolation|Error|\Exception
     *
     * @return array<string>
     */
    private function foldSelectionWithAlias(SelectionSetNode $selectionSet, int $descend, Type $parentType): array
    {
        /** @var array<string, bool> $fields */
        $fields = [];

        foreach ($selectionSet->selections as $selectionNode) {
            if ($selectionNode instanceof FieldNode) {
                $fieldName = $selectionNode->name->value;
                $aliasName = $selectionNode->alias->value ?? $fieldName;

                if ($fieldName === Introspection::TYPE_NAME_FIELD_NAME) {
                    continue;
                }

                assert($parentType instanceof HasFieldsType, 'ensured by query validation and the check above which excludes union types');

                $fieldDef = $parentType->getField($fieldName);
                $fieldType = Type::getNamedType($fieldDef->getType());
                $fields[$fieldName]['aliases'][$aliasName]['args'] = Values::getArgumentValues($fieldDef, $selectionNode, $this->variableValues);

                if ($descend > 0 && $selectionNode->selectionSet !== null) {
                    $fields[$fieldName]['aliases'][$aliasName]['fields'] = $this->foldSelectionWithAlias($selectionNode->selectionSet, $descend - 1, $fieldType);
                }
            } elseif ($selectionNode instanceof FragmentSpreadNode) {
                $spreadName = $selectionNode->name->value;
                if (isset($this->fragments[$spreadName])) {
                    $fragment = $this->fragments[$spreadName];
                    $fieldType = $this->schema->getType($fragment->typeCondition->name->value);
                    assert($fieldType instanceof Type, 'ensured by query validation');

                    $fields = \array_merge_recursive(
                        $this->foldSelectionWithAlias($fragment->selectionSet, $descend, $fieldType),
                        $fields
                    );
                }
            } elseif ($selectionNode instanceof InlineFragmentNode) {
                $typeCondition = $selectionNode->typeCondition;
                $fieldType = $typeCondition === null
                    ? $parentType
                    : $this->schema->getType($typeCondition->name->value);
                assert($fieldType instanceof Type, 'ensured by query validation');

                $fields = \array_merge_recursive(
                    $this->foldSelectionWithAlias($selectionNode->selectionSet, $descend, $fieldType),
                    $fields
                );
            }
        }

        return $fields;
    }
}
