<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error;
use GraphQL\Language\AST\FragmentSpread;
use GraphQL\Language\AST\InlineFragment;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\SelectionSet;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\PairSet;
use GraphQL\Utils\TypeInfo;
use GraphQL\Validator\Messages;
use GraphQL\Validator\ValidationContext;

class OverlappingFieldsCanBeMerged
{
    public function __invoke(ValidationContext $context)
    {
        $comparedSet = new PairSet();

        return [
            Node::SELECTION_SET => [
                // Note: we validate on the reverse traversal so deeper conflicts will be
                // caught first, for clearer error messages.
                'leave' => function(SelectionSet $selectionSet) use ($context, $comparedSet) {
                    $fieldMap = $this->collectFieldASTsAndDefs(
                        $context,
                        $context->getType(),
                        $selectionSet
                    );

                    $conflicts = $this->findConflicts($fieldMap, $context, $comparedSet);

                    if (!empty($conflicts)) {
                        return array_map(function ($conflict) {
                            $responseName = $conflict[0][0];
                            $reason = $conflict[0][1];
                            $blameNodes = $conflict[1];

                            return new Error(
                                Messages::fieldsConflictMessage($responseName, $reason),
                                $blameNodes
                            );
                        }, $conflicts);

                    }
                }
            ]
        ];
    }

    private function findConflicts($fieldMap, ValidationContext $context, PairSet $comparedSet)
    {
        $conflicts = [];
        foreach ($fieldMap as $responseName => $fields) {
            $count = count($fields);
            if ($count > 1) {
                for ($i = 0; $i < $count; $i++) {
                    for ($j = $i; $j < $count; $j++) {
                        $conflict = $this->findConflict($responseName, $fields[$i], $fields[$j], $context, $comparedSet);
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
     * @param ValidationContext $context
     * @param PairSet $comparedSet
     * @param $responseName
     * @param [Field, GraphQLFieldDefinition] $pair1
     * @param [Field, GraphQLFieldDefinition] $pair2
     * @return array|null
     */
    private function findConflict($responseName, array $pair1, array $pair2, ValidationContext $context, PairSet $comparedSet)
    {
        list($ast1, $def1) = $pair1;
        list($ast2, $def2) = $pair2;

        if ($ast1 === $ast2 || $comparedSet->has($ast1, $ast2)) {
            return null;
        }
        $comparedSet->add($ast1, $ast2);

        $name1 = $ast1->name->value;
        $name2 = $ast2->name->value;

        if ($name1 !== $name2) {
            return [
                [$responseName, "$name1 and $name2 are different fields"],
                [$ast1, $ast2]
            ];
        }

        $type1 = isset($def1) ? $def1->getType() : null;
        $type2 = isset($def2) ? $def2->getType() : null;

        if (!$this->sameType($type1, $type2)) {
            return [
                [$responseName, "they return differing types $type1 and $type2"],
                [$ast1, $ast2]
            ];
        }

        $args1 = isset($ast1->arguments) ? $ast1->arguments : [];
        $args2 = isset($ast2->arguments) ? $ast2->arguments : [];

        if (!$this->sameNameValuePairs($args1, $args2)) {
            return [
                [$responseName, 'they have differing arguments'],
                [$ast1, $ast2]
            ];
        }

        $directives1 = isset($ast1->directives) ? $ast1->directives : [];
        $directives2 = isset($ast2->directives) ? $ast2->directives : [];

        if (!$this->sameNameValuePairs($directives1, $directives2)) {
            return [
                [$responseName, 'they have differing directives'],
                [$ast1, $ast2]
            ];
        }

        $selectionSet1 = isset($ast1->selectionSet) ? $ast1->selectionSet : null;
        $selectionSet2 = isset($ast2->selectionSet) ? $ast2->selectionSet : null;

        if ($selectionSet1 && $selectionSet2) {
            $visitedFragmentNames = new \ArrayObject();

            $subfieldMap = $this->collectFieldASTsAndDefs(
                $context,
                $type1,
                $selectionSet1,
                $visitedFragmentNames
            );
            $subfieldMap = $this->collectFieldASTsAndDefs(
                $context,
                $type2,
                $selectionSet2,
                $visitedFragmentNames,
                $subfieldMap
            );
            $conflicts = $this->findConflicts($subfieldMap, $context, $comparedSet);

            if (!empty($conflicts)) {
                return [
                    [$responseName, array_map(function ($conflict) { return $conflict[0]; }, $conflicts)],
                    array_reduce($conflicts, function ($list, $conflict) { return array_merge($list, $conflict[1]); }, [$ast1, $ast2])
                ];
            }
        }
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
     * @param Type|null $parentType
     * @param SelectionSet $selectionSet
     * @param \ArrayObject $visitedFragmentNames
     * @param \ArrayObject $astAndDefs
     * @return mixed
     */
    private function collectFieldASTsAndDefs(ValidationContext $context, $parentType, SelectionSet $selectionSet, \ArrayObject $visitedFragmentNames = null, \ArrayObject $astAndDefs = null)
    {
        $_visitedFragmentNames = $visitedFragmentNames ?: new \ArrayObject();
        $_astAndDefs = $astAndDefs ?: new \ArrayObject();

        for ($i = 0; $i < count($selectionSet->selections); $i++) {
            $selection = $selectionSet->selections[$i];

            switch ($selection->kind) {
                case Node::FIELD:
                    $fieldAST = $selection;
                    $fieldName = $fieldAST->name->value;
                    $fieldDef = null;
                    if ($parentType && method_exists($parentType, 'getFields')) {
                        $tmp = $parentType->getFields();
                        if (isset($tmp[$fieldName])) {
                            $fieldDef = $tmp[$fieldName];
                        }
                    }
                    $responseName = $fieldAST->alias ? $fieldAST->alias->value : $fieldName;

                    if (!isset($_astAndDefs[$responseName])) {
                        $_astAndDefs[$responseName] = new \ArrayObject();
                    }
                    $_astAndDefs[$responseName][] = [$fieldAST, $fieldDef];
                    break;
                case Node::INLINE_FRAGMENT:
                    /** @var InlineFragment $inlineFragment */
                    $inlineFragment = $selection;
                    $_astAndDefs = $this->collectFieldASTsAndDefs(
                        $context,
                        TypeInfo::typeFromAST($context->getSchema(), $inlineFragment->typeCondition),
                        $inlineFragment->selectionSet,
                        $_visitedFragmentNames,
                        $_astAndDefs
                    );
                    break;
                case Node::FRAGMENT_SPREAD:
                    /** @var FragmentSpread $fragmentSpread */
                    $fragmentSpread = $selection;
                    $fragName = $fragmentSpread->name->value;
                    if (!empty($_visitedFragmentNames[$fragName])) {
                        continue;
                    }
                    $_visitedFragmentNames[$fragName] = true;
                    $fragment = $context->getFragment($fragName);
                    if (!$fragment) {
                        continue;
                    }
                    $_astAndDefs = $this->collectFieldASTsAndDefs(
                        $context,
                        TypeInfo::typeFromAST($context->getSchema(), $fragment->typeCondition),
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
     * @param Array<Argument | Directive> $pairs1
     * @param Array<Argument | Directive> $pairs2
     * @return bool|string
     */
    private function sameNameValuePairs(array $pairs1, array $pairs2)
    {
        if (count($pairs1) !== count($pairs2)) {
            return false;
        }
        foreach ($pairs1 as $pair1) {
            $matchedPair2 = null;
            foreach ($pairs2 as $pair2) {
                if ($pair2->name->value === $pair1->name->value) {
                    $matchedPair2 = $pair2;
                    break;
                }
            }
            if (!$matchedPair2) {
                return false;
            }
            if (!$this->sameValue($pair1->value, $matchedPair2->value)) {
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
        return (!$type1 && !$type2) || (string) $type1 === (string) $type2;
    }
}
