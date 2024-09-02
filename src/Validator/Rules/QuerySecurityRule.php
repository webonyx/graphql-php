<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\Visitor;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\HasFieldsType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Introspection;
use GraphQL\Utils\AST;
use GraphQL\Validator\QueryValidationContext;

/**
 * @see Visitor, FieldDefinition
 *
 * @phpstan-import-type VisitorArray from Visitor
 *
 * @phpstan-type ASTAndDefs \ArrayObject<string, \ArrayObject<int, array{FieldNode, FieldDefinition|null}>>
 */
abstract class QuerySecurityRule extends ValidationRule
{
    public const DISABLED = 0;

    /** @var array<string, FragmentDefinitionNode> */
    protected array $fragments = [];

    /** @throws \InvalidArgumentException */
    protected function checkIfGreaterOrEqualToZero(string $name, int $value): void
    {
        if ($value < 0) {
            throw new \InvalidArgumentException("\${$name} argument must be greater or equal to 0.");
        }
    }

    protected function getFragment(FragmentSpreadNode $fragmentSpread): ?FragmentDefinitionNode
    {
        return $this->fragments[$fragmentSpread->name->value] ?? null;
    }

    /** @return array<string, FragmentDefinitionNode> */
    protected function getFragments(): array
    {
        return $this->fragments;
    }

    /**
     * @phpstan-param VisitorArray $validators
     *
     * @phpstan-return VisitorArray
     */
    protected function invokeIfNeeded(QueryValidationContext $context, array $validators): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $this->gatherFragmentDefinition($context);

        return $validators;
    }

    abstract protected function isEnabled(): bool;

    protected function gatherFragmentDefinition(QueryValidationContext $context): void
    {
        // Gather all the fragment definition.
        // Importantly this does not include inline fragments.
        $definitions = $context->getDocument()->definitions;
        foreach ($definitions as $node) {
            if ($node instanceof FragmentDefinitionNode) {
                $this->fragments[$node->name->value] = $node;
            }
        }
    }

    /**
     * Given a selectionSet, adds all fields in that selection to
     * the passed in map of fields, and returns it at the end.
     *
     * Note: This is not the same as execution's collectFields because at static
     * time we do not know what object type will be used, so we unconditionally
     * spread in all fragments.
     *
     * @see \GraphQL\Validator\Rules\OverlappingFieldsCanBeMerged
     *
     * @param \ArrayObject<string, true>|null $visitedFragmentNames
     *
     * @phpstan-param ASTAndDefs|null $astAndDefs
     *
     * @phpstan-return ASTAndDefs
     *
     * @throws \Exception
     * @throws \ReflectionException
     * @throws InvariantViolation
     */
    protected function collectFieldASTsAndDefs(
        QueryValidationContext $context,
        ?Type $parentType,
        SelectionSetNode $selectionSet,
        ?\ArrayObject $visitedFragmentNames = null,
        ?\ArrayObject $astAndDefs = null
    ): \ArrayObject {
        $visitedFragmentNames ??= new \ArrayObject();
        $astAndDefs ??= new \ArrayObject();

        foreach ($selectionSet->selections as $selection) {
            switch (true) {
                case $selection instanceof FieldNode:
                    $fieldName = $selection->name->value;

                    $fieldDef = null;
                    if ($parentType instanceof HasFieldsType) {
                        $schemaMetaFieldDef = Introspection::schemaMetaFieldDef();
                        $typeMetaFieldDef = Introspection::typeMetaFieldDef();
                        $typeNameMetaFieldDef = Introspection::typeNameMetaFieldDef();

                        $queryType = $context->getSchema()->getQueryType();

                        if ($fieldName === $schemaMetaFieldDef->name && $queryType === $parentType) {
                            $fieldDef = $schemaMetaFieldDef;
                        } elseif ($fieldName === $typeMetaFieldDef->name && $queryType === $parentType) {
                            $fieldDef = $typeMetaFieldDef;
                        } elseif ($fieldName === $typeNameMetaFieldDef->name) {
                            $fieldDef = $typeNameMetaFieldDef;
                        } elseif ($parentType->hasField($fieldName)) {
                            $fieldDef = $parentType->getField($fieldName);
                        }
                    }

                    $responseName = $this->getFieldName($selection);
                    $responseContext = $astAndDefs[$responseName] ??= new \ArrayObject();
                    $responseContext[] = [$selection, $fieldDef];
                    break;
                case $selection instanceof InlineFragmentNode:
                    $typeCondition = $selection->typeCondition;
                    $fragmentParentType = $typeCondition === null
                        ? $parentType
                        : AST::typeFromAST([$context->getSchema(), 'getType'], $typeCondition);
                    $astAndDefs = $this->collectFieldASTsAndDefs(
                        $context,
                        $fragmentParentType,
                        $selection->selectionSet,
                        $visitedFragmentNames,
                        $astAndDefs
                    );
                    break;
                case $selection instanceof FragmentSpreadNode:
                    $fragName = $selection->name->value;

                    if (! isset($visitedFragmentNames[$fragName])) {
                        $visitedFragmentNames[$fragName] = true;

                        $fragment = $context->getFragment($fragName);

                        if ($fragment !== null) {
                            $astAndDefs = $this->collectFieldASTsAndDefs(
                                $context,
                                AST::typeFromAST([$context->getSchema(), 'getType'], $fragment->typeCondition),
                                $fragment->selectionSet,
                                $visitedFragmentNames,
                                $astAndDefs
                            );
                        }
                    }

                    break;
            }
        }

        return $astAndDefs;
    }

    protected function getFieldName(FieldNode $node): string
    {
        $fieldName = $node->name->value;

        return $node->alias === null
            ? $fieldName
            : $node->alias->value;
    }
}
