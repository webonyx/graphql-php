<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error;
use GraphQL\Language\AST\Name;
use GraphQL\Language\AST\NamedType;
use GraphQL\Language\AST\Node;
use GraphQL\Validator\Messages;
use GraphQL\Validator\ValidationContext;

class KnownTypeNames
{
    static function unknownTypeMessage($type)
    {
        return "Unknown type \"$type\".";
    }

    public function __invoke(ValidationContext $context)
    {
        return [
            Node::NAMED_TYPE => function(NamedType $node, $key) use ($context) {
                if ($key === 'type' || $key === 'typeCondition') {
                    $typeName = $node->name->value;
                    $type = $context->getSchema()->getType($typeName);
                    if (!$type) {
                        return new Error(self::unknownTypeMessage($typeName), [$node]);
                    }
                }
            }
        ];
    }
}
