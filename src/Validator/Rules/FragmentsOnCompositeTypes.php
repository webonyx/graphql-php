<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error;
use GraphQL\Language\AST\FragmentDefinition;
use GraphQL\Language\AST\InlineFragment;
use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\CompositeType;
use GraphQL\Validator\Messages;
use GraphQL\Validator\ValidationContext;

class FragmentsOnCompositeTypes
{
    public function __invoke(ValidationContext $context)
    {
        return [
            Node::INLINE_FRAGMENT => function(InlineFragment $node) use ($context) {
                $typeName = $node->typeCondition->value;
                $type = $context->getSchema()->getType($typeName);
                $isCompositeType = $type instanceof CompositeType;

                if (!$isCompositeType) {
                    return new Error(
                        "Fragment cannot condition on non composite type \"$typeName\".",
                        [$node->typeCondition]
                    );
                }
            },
            Node::FRAGMENT_DEFINITION => function(FragmentDefinition $node) use ($context) {
                $typeName = $node->typeCondition->value;
                $type = $context->getSchema()->getType($typeName);
                $isCompositeType = $type instanceof CompositeType;

                if (!$isCompositeType) {
                    return new Error(
                        Messages::fragmentOnNonCompositeErrorMessage($node->name->value, $typeName),
                        [$node->typeCondition]
                    );
                }
            }
        ];
    }
}
