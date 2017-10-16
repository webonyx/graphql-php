<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error\Error;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\OutputType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\Utils;
use GraphQL\Utils\PairSet;
use GraphQL\Utils\TypeInfo;
use GraphQL\Validator\ValidationContext;

class OverlappingFieldsCanBeMerged extends AbstractValidationRule
{
    static function fieldsConflictMessage($responseName, $reason)
    {
        $reasonMessage = self::reasonMessage($reason);
        return "Fields \"$responseName\" conflict because $reasonMessage.";
    }

    static function reasonMessage($reason)
    {
        if (is_array($reason)) {
            $tmp = array_map(function ($tmp) {
                list($responseName, $subReason) = $tmp;
                $reasonMessage = self::reasonMessage($subReason);
                return "subfields \"$responseName\" conflict because $reasonMessage";
            }, $reason);
            return implode(' and ', $tmp);
        }
        return $reason;
    }

    /**
     * @var PairSet
     */
    public $comparedSet;

    public function getVisitor(ValidationContext $context)
    {
        $this->comparedSet = new PairSet();

        return [
            NodeKind::SELECTION_SET => [
                // Note: we validate on the reverse traversal so deeper conflicts will be
                // caught first, for clearer error messages.
                'leave' => function(SelectionSetNode $selectionSet) use ($context) {
                    $fieldMap = $this->collectFieldNodesAndDefs(
                        $context,
                        $context->getParentType(),
                        $selectionSet
                    );

                    $conflicts = $this->findConflicts(false, $fieldMap, $context);

                    foreach ($conflicts as $conflict) {
                        $responseName = $conflict[0][0];
                        $reason = $conflict[0][1];
                        $fields1 = $conflict[1];
                        $fields2 = $conflict[2];

                        $context->reportError(new Error(
                            self::fieldsConflictMessage($responseName, $reason),
                            array_merge($fields1, $fields2)
                        ));
                    }
                }
            ]
        ];
    }

    private function findConflicts($parentFieldsAreMutuallyExclusive, $fieldMap, ValidationContext $context)
    {
        $conflicts = [];
        foreach ($fieldMap as $responseName => $fields) {
            $count = count($fields);
            if ($count > 1) {
                for ($i = 0; $i < $count; $i++) {
                    for ($j = $i; $j < $count; $j++) {
                        $conflict = $this->findConflict(
                            $parentFieldsAreMutuallyExclusive,
                            $responseName,
                            $fields[$i],
                            $fields[$j],
                            $context
                        );

                        if ($conflict) {
                            $conflicts[] = $conflict;
                        }
                    }
                }
            }
        }
        return $conflicts;
    }

    /**
     * @param $parentFieldsAreMutuallyExclusive
     * @param $responseName
     * @param [FieldNode, GraphQLFieldDefinition] $pair1
     * @param [FieldNode, GraphQLFieldDefinition] $pair2
     * @param ValidationContext $context
     * @return array|null
     */
    private function findConflict(
        $parentFieldsAreMutuallyExclusive,
        $responseName,
        array $pair1,
        array $pair2,
        ValidationContext $context
    )
    {
        list($parentType1, $ast1, $def1) = $pair1;
        list($parentType2, $ast2, $def2) = $pair2;

        // Not a pair.
        if ($ast1 === $ast2) {
            return null;
        }

        // Memoize, do not report the same issue twice.
        // Note: Two overlapping ASTs could be encountered both when
        // `parentFieldsAreMutuallyExclusive` is true and is false, which could
        // produce different results (when `true` being a subset of `false`).
        // However we do not need to include this piece of information when
        // memoizing since this rule visits leaf fields before their parent fields,
        // ensuring that `parentFieldsAreMutuallyExclusive` is `false` the first
        // time two overlapping fields are encountered, ensuring that the full
        // set of validation rules are always checked when necessary.
        if ($this->comparedSet->has($ast1, $ast2)) {
            return null;
        }
        $this->comparedSet->add($ast1, $ast2);

        // The return type for each field.
        $type1 = isset($def1) ? $def1->getType() : null;
        $type2 = isset($def2) ? $def2->getType() : null;

        // If it is known that two fields could not possibly apply at the same
        // time, due to the parent types, then it is safe to permit them to diverge
        // in aliased field or arguments used as they will not present any ambiguity
        // by differing.
        // It is known that two parent types could never overlap if they are
        // different Object types. Interface or Union types might overlap - if not
        // in the current state of the schema, then perhaps in some future version,
        // thus may not safely diverge.
        $fieldsAreMutuallyExclusive =
            $parentFieldsAreMutuallyExclusive ||
            $parentType1 !== $parentType2 &&
            $parentType1 instanceof ObjectType &&
            $parentType2 instanceof ObjectType;

        if (!$fieldsAreMutuallyExclusive) {
            $name1 = $ast1->name->value;
            $name2 = $ast2->name->value;

            if ($name1 !== $name2) {
                return [
                    [$responseName, "$name1 and $name2 are different fields"],
                    [$ast1],
                    [$ast2]
                ];
            }

            $args1 = isset($ast1->arguments) ? $ast1->arguments : [];
            $args2 = isset($ast2->arguments) ? $ast2->arguments : [];

            if (!$this->sameArguments($args1, $args2)) {
                return [
                    [$responseName, 'they have differing arguments'],
                    [$ast1],
                    [$ast2]
                ];
            }
        }


        if ($type1 && $type2 && $this->doTypesConflict($type1, $type2)) {
            return [
                [$responseName, "they return conflicting types $type1 and $type2"],
                [$ast1],
                [$ast2]
            ];
        }

        $subfieldMap = $this->getSubfieldMap($ast1, $type1, $ast2, $type2, $context);

        if ($subfieldMap) {
            $conflicts = $this->findConflicts($fieldsAreMutuallyExclusive, $subfieldMap, $context);
            return $this->subfieldConflicts($conflicts, $responseName, $ast1, $ast2);
        }
        return null;
    }

    private function getSubfieldMap(
        FieldNode $ast1,
        $type1,
        FieldNode $ast2,
        $type2,
        ValidationContext $context
    ) {
        $selectionSet1 = $ast1->selectionSet;
        $selectionSet2 = $ast2->selectionSet;
        if ($selectionSet1 && $selectionSet2) {
            $visitedFragmentNames = new \ArrayObject();
            $subfieldMap = $this->collectFieldNodesAndDefs(
                $context,
                Type::getNamedType($type1),
                $selectionSet1,
                $visitedFragmentNames
            );
            $subfieldMap = $this->collectFieldNodesAndDefs(
              $context,
              Type::getNamedType($type2),
              $selectionSet2,
              $visitedFragmentNames,
              $subfieldMap
            );
            return $subfieldMap;
        }
    }

    private function subfieldConflicts(
        array $conflicts,
        $responseName,
        FieldNode $ast1,
        FieldNode $ast2
    )
    {
        if (!empty($conflicts)) {
            return [
                [
                    $responseName,
                    Utils::map($conflicts, function($conflict) {return $conflict[0];})
                ],
                array_reduce(
                    $conflicts,
                    function($allFields, $conflict) { return array_merge($allFields, $conflict[1]);},
                    [ $ast1 ]
                ),
                array_reduce(
                    $conflicts,
                    function($allFields, $conflict) {return array_merge($allFields, $conflict[2]);},
                    [ $ast2 ]
                )
            ];
        }
    }

    /**
     * @param OutputType $type1
     * @param OutputType $type2
     * @return bool
     */
    private function doTypesConflict(OutputType $type1, OutputType $type2)
    {
        if ($type1 instanceof ListOfType) {
            return $type2 instanceof ListOfType ?
                $this->doTypesConflict($type1->getWrappedType(), $type2->getWrappedType()) :
                true;
        }
        if ($type2 instanceof ListOfType) {
            return $type1 instanceof ListOfType ?
                $this->doTypesConflict($type1->getWrappedType(), $type2->getWrappedType()) :
                true;
        }
        if ($type1 instanceof NonNull) {
            return $type2 instanceof NonNull ?
                $this->doTypesConflict($type1->getWrappedType(), $type2->getWrappedType()) :
                true;
        }
        if ($type2 instanceof NonNull) {
            return $type1 instanceof NonNull ?
                $this->doTypesConflict($type1->getWrappedType(), $type2->getWrappedType()) :
                true;
        }
        if (Type::isLeafType($type1) || Type::isLeafType($type2)) {
            return $type1 !== $type2;
        }
        return false;
    }

    /**
     * Given a selectionSet, adds all of the fields in that selection to
     * the passed in map of fields, and returns it at the end.
     *
     * Note: This is not the same as execution's collectFields because at static
     * time we do not know what object type will be used, so we unconditionally
     * spread in all fragments.
     *
     * @param ValidationContext $context
     * @param mixed $parentType
     * @param SelectionSetNode $selectionSet
     * @param \ArrayObject $visitedFragmentNames
     * @param \ArrayObject $astAndDefs
     * @return mixed
     */
    private function collectFieldNodesAndDefs(ValidationContext $context, $parentType, SelectionSetNode $selectionSet, \ArrayObject $visitedFragmentNames = null, \ArrayObject $astAndDefs = null)
    {
        $_visitedFragmentNames = $visitedFragmentNames ?: new \ArrayObject();
        $_astAndDefs = $astAndDefs ?: new \ArrayObject();

        for ($i = 0; $i < count($selectionSet->selections); $i++) {
            $selection = $selectionSet->selections[$i];

            switch ($selection->kind) {
                case NodeKind::FIELD:
                    $fieldName = $selection->name->value;
                    $fieldDef = null;
                    if ($parentType && method_exists($parentType, 'getFields')) {
                        $tmp = $parentType->getFields();
                        if (isset($tmp[$fieldName])) {
                            $fieldDef = $tmp[$fieldName];
                        }
                    }
                    $responseName = $selection->alias ? $selection->alias->value : $fieldName;

                    if (!isset($_astAndDefs[$responseName])) {
                        $_astAndDefs[$responseName] = new \ArrayObject();
                    }
                    $_astAndDefs[$responseName][] = [$parentType, $selection, $fieldDef];
                    break;
                case NodeKind::INLINE_FRAGMENT:
                    $typeCondition = $selection->typeCondition;
                    $inlineFragmentType = $typeCondition
                        ? TypeInfo::typeFromAST($context->getSchema(), $typeCondition)
                        : $parentType;

                    $_astAndDefs = $this->collectFieldNodesAndDefs(
                        $context,
                        $inlineFragmentType,
                        $selection->selectionSet,
                        $_visitedFragmentNames,
                        $_astAndDefs
                    );
                    break;
                case NodeKind::FRAGMENT_SPREAD:
                    /** @var FragmentSpreadNode $selection */
                    $fragName = $selection->name->value;
                    if (!empty($_visitedFragmentNames[$fragName])) {
                        continue;
                    }
                    $_visitedFragmentNames[$fragName] = true;
                    $fragment = $context->getFragment($fragName);
                    if (!$fragment) {
                        continue;
                    }
                    $fragmentType = TypeInfo::typeFromAST($context->getSchema(), $fragment->typeCondition);
                    $_astAndDefs = $this->collectFieldNodesAndDefs(
                        $context,
                        $fragmentType,
                        $fragment->selectionSet,
                        $_visitedFragmentNames,
                        $_astAndDefs
                    );
                    break;
            }
        }
        return $_astAndDefs;
    }

    /**
     * @param ArgumentNode[]|DirectiveNode[] $arguments1
     * @param ArgumentNode[]|DirectiveNode[] $arguments2
     *
     * @return bool|string
     */
    private function sameArguments($arguments1, $arguments2)
    {
        if (count($arguments1) !== count($arguments2)) {
            return false;
        }
        foreach ($arguments1 as $arg1) {
            $arg2 = null;
            foreach ($arguments2 as $arg) {
                if ($arg->name->value === $arg1->name->value) {
                    $arg2 = $arg;
                    break;
                }
            }
            if (!$arg2) {
                return false;
            }
            if (!$this->sameValue($arg1->value, $arg2->value)) {
                return false;
            }
        }
        return true;
    }

    private function sameValue($value1, $value2)
    {
        return (!$value1 && !$value2) || (Printer::doPrint($value1) === Printer::doPrint($value2));
    }

    function sameType($type1, $type2)
    {
        return (string) $type1 === (string) $type2;
    }
}
