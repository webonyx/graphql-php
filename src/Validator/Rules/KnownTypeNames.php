<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error\Error;
use GraphQL\Language\AST\Name;
use GraphQL\Language\AST\NamedType;
use GraphQL\Language\AST\Node;
use GraphQL\Language\Visitor;
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
        $skip = function() {return Visitor::skipNode();};

        return [
            Node::OBJECT_TYPE_DEFINITION => $skip,
            Node::INTERFACE_TYPE_DEFINITION => $skip,
            Node::UNION_TYPE_DEFINITION => $skip,
            Node::INPUT_OBJECT_TYPE_DEFINITION => $skip,

            Node::NAMED_TYPE => function(NamedType $node, $key) use ($context) {
                $typeName = $node->name->value;
                $type = $context->getSchema()->getType($typeName);
                if (!$type) {
                    $context->reportError(new Error(self::unknownTypeMessage($typeName), [$node]));
                }
            }
        ];
    }
}
