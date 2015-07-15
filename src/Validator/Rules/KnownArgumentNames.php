<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error;
use GraphQL\Language\AST\Argument;
use GraphQL\Language\AST\Node;
use GraphQL\Utils;
use GraphQL\Validator\Messages;
use GraphQL\Validator\ValidationContext;

class KnownArgumentNames
{
    public function __invoke(ValidationContext $context)
    {
        return [
            Node::ARGUMENT => function(Argument $node) use ($context) {
                $fieldDef = $context->getFieldDef();
                if ($fieldDef) {
                    $argDef = null;
                    foreach ($fieldDef->args as $arg) {
                        if ($arg->name === $node->name->value) {
                            $argDef = $arg;
                            break;
                        }
                    }

                    if (!$argDef) {
                        $parentType = $context->getParentType();
                        Utils::invariant($parentType);
                        return new Error(
                            Messages::unknownArgMessage($node->name->value, $fieldDef->name, $parentType->name),
                            [$node]
                        );
                    }
                }
            }
        ];
    }
}
