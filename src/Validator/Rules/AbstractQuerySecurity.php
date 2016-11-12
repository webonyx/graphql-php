<?php

namespace GraphQL\Validator\Rules;

use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\FragmentDefinition;
use GraphQL\Language\AST\FragmentSpread;
use GraphQL\Language\AST\InlineFragment;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeType;
use GraphQL\Language\AST\SelectionSet;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Introspection;
use GraphQL\Utils\TypeInfo;
use GraphQL\Validator\ValidationContext;

abstract class AbstractQuerySecurity
{
    const DISABLED = 0;

    /**
     * @var FragmentDefinition[]
     */
    private $fragments = [];

    /**
     * @return \GraphQL\Language\AST\FragmentDefinition[]
     */
    protected function getFragments()
    {
        return $this->fragments;
    }

    /**
     * check if equal to 0 no check is done. Must be greater or equal to 0.
     *
     * @param $value
     */
    protected function checkIfGreaterOrEqualToZero($name, $value)
    {
        if ($value < 0) {
            throw new \InvalidArgumentException(sprintf('$%s argument must be greater or equal to 0.', $name));
        }
    }

    protected function gatherFragmentDefinition(ValidationContext $context)
    {
        // Gather all the fragment definition.
        // Importantly this does not include inline fragments.
        $definitions = $context->getDocument()->getDefinitions();
        foreach ($definitions as $node) {
            if ($node instanceof FragmentDefinition) {
                $this->fragments[$node->getName()->getValue()] = $node;
            }
        }
    }

    protected function getFragment(FragmentSpread $fragmentSpread)
    {
        $spreadName = $fragmentSpread->getName()->getValue();
        $fragments = $this->getFragments();

        return isset($fragments[$spreadName]) ? $fragments[$spreadName] : null;
    }

    protected function invokeIfNeeded(ValidationContext $context, array $validators)
    {
        // is disabled?
        if (!$this->isEnabled()) {
            return [];
        }

        $this->gatherFragmentDefinition($context);

        return $validators;
    }

    /**
     * Given a selectionSet, adds all of the fields in that selection to
     * the passed in map of fields, and returns it at the end.
     *
     * Note: This is not the same as execution's collectFields because at static
     * time we do not know what object type will be used, so we unconditionally
     * spread in all fragments.
     *
     * @see GraphQL\Validator\Rules\OverlappingFieldsCanBeMerged
     *
     * @param ValidationContext $context
     * @param Type|null         $parentType
     * @param SelectionSet      $selectionSet
     * @param \ArrayObject      $visitedFragmentNames
     * @param \ArrayObject      $astAndDefs
     *
     * @return \ArrayObject
     */
    protected function collectFieldASTsAndDefs(ValidationContext $context, $parentType, SelectionSet $selectionSet, \ArrayObject $visitedFragmentNames = null, \ArrayObject $astAndDefs = null)
    {
        $_visitedFragmentNames = $visitedFragmentNames ?: new \ArrayObject();
        $_astAndDefs = $astAndDefs ?: new \ArrayObject();

        foreach ($selectionSet->getSelections() as $selection) {
            switch ($selection->getKind()) {
                case NodeType::FIELD:
                    /* @var Field $selection */
                    $fieldName = $selection->getName()->getValue();
                    $fieldDef = null;
                    if ($parentType && method_exists($parentType, 'getFields')) {
                        $tmp = $parentType->getFields();
                        $schemaMetaFieldDef = Introspection::schemaMetaFieldDef();
                        $typeMetaFieldDef = Introspection::typeMetaFieldDef();
                        $typeNameMetaFieldDef = Introspection::typeNameMetaFieldDef();

                        if ($fieldName === $schemaMetaFieldDef->name && $context->getSchema()->getQueryType() === $parentType) {
                            $fieldDef = $schemaMetaFieldDef;
                        } elseif ($fieldName === $typeMetaFieldDef->name && $context->getSchema()->getQueryType() === $parentType) {
                            $fieldDef = $typeMetaFieldDef;
                        } elseif ($fieldName === $typeNameMetaFieldDef->name) {
                            $fieldDef = $typeNameMetaFieldDef;
                        } elseif (isset($tmp[$fieldName])) {
                            $fieldDef = $tmp[$fieldName];
                        }
                    }
                    $responseName = $this->getFieldName($selection);
                    if (!isset($_astAndDefs[$responseName])) {
                        $_astAndDefs[$responseName] = new \ArrayObject();
                    }
                    // create field context
                    $_astAndDefs[$responseName][] = [$selection, $fieldDef];
                    break;
                case NodeType::INLINE_FRAGMENT:
                    /* @var InlineFragment $selection */
                    $_astAndDefs = $this->collectFieldASTsAndDefs(
                        $context,
                        TypeInfo::typeFromAST($context->getSchema(), $selection->getTypeCondition()),
                        $selection->getSelectionSet(),
                        $_visitedFragmentNames,
                        $_astAndDefs
                    );
                    break;
                case NodeType::FRAGMENT_SPREAD:
                    /* @var FragmentSpread $selection */
                    $fragName = $selection->getName()->getValue();

                    if (empty($_visitedFragmentNames[$fragName])) {
                        $_visitedFragmentNames[$fragName] = true;
                        $fragment = $context->getFragment($fragName);

                        if ($fragment) {
                            $_astAndDefs = $this->collectFieldASTsAndDefs(
                                $context,
                                TypeInfo::typeFromAST($context->getSchema(), $fragment->getTypeCondition()),
                                $fragment->getSelectionSet(),
                                $_visitedFragmentNames,
                                $_astAndDefs
                            );
                        }
                    }
                    break;
            }
        }

        return $_astAndDefs;
    }

    protected function getFieldName(Field $node)
    {
        $fieldName = $node->getName()->getValue();
        $responseName = $node->getAlias() ? $node->getAlias()->getValue() : $fieldName;

        return $responseName;
    }

    abstract protected function isEnabled();
}
