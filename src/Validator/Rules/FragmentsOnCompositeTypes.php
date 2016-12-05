<?php
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\Type;
use GraphQL\Validator\ValidationContext;

class FragmentsOnCompositeTypes
{
    static function inlineFragmentOnNonCompositeErrorMessage($type)
    {
        return "Fragment cannot condition on non composite type \"$type\".";
    }

    static function fragmentOnNonCompositeErrorMessage($fragName, $type)
    {
        return "Fragment \"$fragName\" cannot condition on non composite type \"$type\".";
    }

    public function __invoke(ValidationContext $context)
    {
        return [
            NodeKind::INLINE_FRAGMENT => function(InlineFragmentNode $node) use ($context) {
                $type = $context->getType();

                if ($node->typeCondition && $type && !Type::isCompositeType($type)) {
                    $context->reportError(new Error(
                        static::inlineFragmentOnNonCompositeErrorMessage($type),
                        [$node->typeCondition]
                    ));
                }
            },
            NodeKind::FRAGMENT_DEFINITION => function(FragmentDefinitionNode $node) use ($context) {
                $type = $context->getType();

                if ($type && !Type::isCompositeType($type)) {
                    $context->reportError(new Error(
                        static::fragmentOnNonCompositeErrorMessage($node->name->value, Printer::doPrint($node->typeCondition)),
                        [$node->typeCondition]
                    ));
                }
            }
        ];
    }
}
