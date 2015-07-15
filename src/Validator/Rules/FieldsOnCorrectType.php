<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error;
use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\Node;
use GraphQL\Validator\Messages;
use GraphQL\Validator\ValidationContext;

class FieldsOnCorrectType
{
    public function __invoke(ValidationContext $context)
    {
        return [
            Node::FIELD => function(Field $node) use ($context) {
                $type = $context->getParentType();
                if ($type) {
                    $fieldDef = $context->getFieldDef();
                    if (!$fieldDef) {
                        return new Error(
                            Messages::undefinedFieldMessage($node->name->value, $type->name),
                            [$node]
                        );
                    }
                }
            }
        ];
    }
}
