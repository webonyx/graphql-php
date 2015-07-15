<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error;
use GraphQL\Language\AST\Name;
use GraphQL\Language\AST\Node;
use GraphQL\Validator\Messages;
use GraphQL\Validator\ValidationContext;

class KnownTypeNames
{
    public function __invoke(ValidationContext $context)
    {
        return [
            Node::NAME => function(Name $node, $key) use ($context) {

                if ($key === 'type' || $key === 'typeCondition') {
                    $typeName = $node->value;
                    $type = $context->getSchema()->getType($typeName);
                    if (!$type) {
                        return new Error(Messages::unknownTypeMessage($typeName), [$node]);
                    }
                }
            }
        ];
    }
}
