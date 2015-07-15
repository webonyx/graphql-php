<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error;
use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\Type;
use GraphQL\Validator\Messages;
use GraphQL\Validator\ValidationContext;

class ScalarLeafs
{
    public function __invoke(ValidationContext $context)
    {
        return [
            Node::FIELD => function(Field $node) use ($context) {
                $type = $context->getType();
                if ($type) {
                    if (Type::isLeafType($type)) {
                        if ($node->selectionSet) {
                            return new Error(
                                Messages::noSubselectionAllowedMessage($node->name->value, $type),
                                [$node->selectionSet]
                            );
                        }
                    } else if (!$node->selectionSet) {
                        return new Error(
                            Messages::requiredSubselectionMessage($node->name->value, $type),
                            [$node]
                        );
                    }
                }
            }
        ];
    }
}
