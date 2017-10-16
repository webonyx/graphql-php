<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error\Error;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Visitor;
use GraphQL\Validator\ValidationContext;

class KnownTypeNames extends AbstractValidationRule
{
    static function unknownTypeMessage($type)
    {
        return "Unknown type \"$type\".";
    }

    public function getVisitor(ValidationContext $context)
    {
        $skip = function() {return Visitor::skipNode();};

        return [
            NodeKind::OBJECT_TYPE_DEFINITION => $skip,
            NodeKind::INTERFACE_TYPE_DEFINITION => $skip,
            NodeKind::UNION_TYPE_DEFINITION => $skip,
            NodeKind::INPUT_OBJECT_TYPE_DEFINITION => $skip,

            NodeKind::NAMED_TYPE => function(NamedTypeNode $node, $key) use ($context) {
                $typeName = $node->name->value;
                $type = $context->getSchema()->getType($typeName);
                if (!$type) {
                    $context->reportError(new Error(self::unknownTypeMessage($typeName), [$node]));
                }
            }
        ];
    }
}
