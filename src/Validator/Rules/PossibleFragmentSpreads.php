<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error;
use GraphQL\Language\AST\FragmentSpread;
use GraphQL\Language\AST\InlineFragment;
use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Utils;
use GraphQL\Validator\Messages;
use GraphQL\Validator\ValidationContext;

class PossibleFragmentSpreads
{
    public function __invoke(ValidationContext $context)
    {
        return [
            Node::INLINE_FRAGMENT => function(InlineFragment $node) use ($context) {
                $fragType = Type::getUnmodifiedType($context->getType());
                $parentType = $context->getParentType();
                if ($fragType && $parentType && !$this->doTypesOverlap($fragType, $parentType)) {
                    return new Error(
                        Messages::typeIncompatibleAnonSpreadMessage($parentType, $fragType),
                        [$node]
                    );
                }
            },
            Node::FRAGMENT_SPREAD => function(FragmentSpread $node) use ($context) {
                $fragName = $node->name->value;
                $fragType = Type::getUnmodifiedType($this->getFragmentType($context, $fragName));
                $parentType = $context->getParentType();

                if ($fragType && $parentType && !$this->doTypesOverlap($fragType, $parentType)) {
                    return new Error(
                        Messages::typeIncompatibleSpreadMessage($fragName, $parentType, $fragType),
                        [$node]
                    );
                }
            }
        ];
    }

    private function getFragmentType(ValidationContext $context, $name)
    {
        $frag = $context->getFragment($name);
        return $frag ? $context->getSchema()->getType($frag->typeCondition->value) : null;
    }

    private function doTypesOverlap($t1, $t2)
    {
        if ($t1 === $t2) {
            return true;
        }
        if ($t1 instanceof ObjectType) {
            if ($t2 instanceof ObjectType) {
                return false;
            }
            return in_array($t1, $t2->getPossibleTypes());
        }
        if ($t1 instanceof InterfaceType || $t1 instanceof UnionType) {
            if ($t2 instanceof ObjectType) {
                return in_array($t2, $t1->getPossibleTypes());
            }
            $t1TypeNames = Utils::keyMap($t1->getPossibleTypes(), function ($type) {
                return $type->name;
            });
            foreach ($t2->getPossibleTypes() as $type) {
                if (!empty($t1TypeNames[$type->name])) {
                    return true;
                }
            }
        }
        return false;
    }
}
