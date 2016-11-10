<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error\Error;
use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeType;
use GraphQL\Type\Definition\Type;
use GraphQL\Validator\Messages;
use GraphQL\Validator\ValidationContext;

class ScalarLeafs
{
    static function noSubselectionAllowedMessage($field, $type)
    {
        return "Field \"$field\" of type \"$type\" must not have a sub selection.";
    }

    static function requiredSubselectionMessage($field, $type)
    {
        return "Field \"$field\" of type \"$type\" must have a sub selection.";
    }

    public function __invoke(ValidationContext $context)
    {
        return [
            NodeType::FIELD => function(Field $node) use ($context) {
                $type = $context->getType();
                if ($type) {
                    if (Type::isLeafType($type)) {
                        if ($node->getSelectionSet()) {
                            $context->reportError(new Error(
                                self::noSubselectionAllowedMessage($node->getName()->getValue(), $type),
                                [$node->getSelectionSet()]
                            ));
                        }
                    } else if (!$node->getSelectionSet()) {
                        $context->reportError(new Error(
                            self::requiredSubselectionMessage($node->getName()->getValue(), $type),
                            [$node]
                        ));
                    }
                }
            }
        ];
    }
}
