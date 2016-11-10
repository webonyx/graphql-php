<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error\Error;
use GraphQL\Language\AST\FragmentDefinition;
use GraphQL\Language\AST\InlineFragment;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeType;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\CompositeType;
use GraphQL\Type\Definition\Type;
use GraphQL\Validator\Messages;
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
            NodeType::INLINE_FRAGMENT => function(InlineFragment $node) use ($context) {
                $type = $context->getType();

                if ($node->typeCondition && $type && !Type::isCompositeType($type)) {
                    $context->reportError(new Error(
                        static::inlineFragmentOnNonCompositeErrorMessage($type),
                        [$node->typeCondition]
                    ));
                }
            },
            NodeType::FRAGMENT_DEFINITION => function(FragmentDefinition $node) use ($context) {
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
