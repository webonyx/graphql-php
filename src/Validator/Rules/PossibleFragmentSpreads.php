<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error\Error;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Utils;
use GraphQL\Validator\ValidationContext;
use GraphQL\Utils\TypeInfo;

class PossibleFragmentSpreads
{
    static function typeIncompatibleSpreadMessage($fragName, $parentType, $fragType)
    {
        return "Fragment \"$fragName\" cannot be spread here as objects of type \"$parentType\" can never be of type \"$fragType\".";
    }

    static function typeIncompatibleAnonSpreadMessage($parentType, $fragType)
    {
        return "Fragment cannot be spread here as objects of type \"$parentType\" can never be of type \"$fragType\".";
    }

    public function __invoke(ValidationContext $context)
    {
        return [
            NodeKind::INLINE_FRAGMENT => function(InlineFragmentNode $node) use ($context) {
                $fragType = $context->getType();
                $parentType = $context->getParentType();

                if ($fragType && $parentType && !TypeInfo::doTypesOverlap($context->getSchema(), $fragType, $parentType)) {
                    $context->reportError(new Error(
                        self::typeIncompatibleAnonSpreadMessage($parentType, $fragType),
                        [$node]
                    ));
                }
            },
            NodeKind::FRAGMENT_SPREAD => function(FragmentSpreadNode $node) use ($context) {
                $fragName = $node->name->value;
                $fragType = $this->getFragmentType($context, $fragName);
                $parentType = $context->getParentType();

                if ($fragType && $parentType && !TypeInfo::doTypesOverlap($context->getSchema(), $fragType, $parentType)) {
                    $context->reportError(new Error(
                        self::typeIncompatibleSpreadMessage($fragName, $parentType, $fragType),
                        [$node]
                    ));
                }
            }
        ];
    }

    private function getFragmentType(ValidationContext $context, $name)
    {
        $frag = $context->getFragment($name);
        return $frag ? TypeInfo::typeFromAST($context->getSchema(), $frag->typeCondition) : null;
    }
}
