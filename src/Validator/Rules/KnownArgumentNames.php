<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error\Error;
use GraphQL\Language\AST\Argument;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeType;
use GraphQL\Utils;
use GraphQL\Validator\Messages;
use GraphQL\Validator\ValidationContext;

class KnownArgumentNames
{
    public static function unknownArgMessage($argName, $fieldName, $type)
    {
        return "Unknown argument \"$argName\" on field \"$fieldName\" of type \"$type\".";
    }

    public static function unknownDirectiveArgMessage($argName, $directiveName)
    {
        return "Unknown argument \"$argName\" on directive \"@$directiveName\".";
    }

    public function __invoke(ValidationContext $context)
    {
        return [
            NodeType::ARGUMENT => function(Argument $node, $key, $parent, $path, $ancestors) use ($context) {
                $argumentOf = $ancestors[count($ancestors) - 1];
                if ($argumentOf->getKind() === NodeType::FIELD) {
                    $fieldDef = $context->getFieldDef();

                    if ($fieldDef) {
                        $fieldArgDef = null;
                        foreach ($fieldDef->args as $arg) {
                            if ($arg->name === $node->getName()->getValue()) {
                                $fieldArgDef = $arg;
                                break;
                            }
                        }
                        if (!$fieldArgDef) {
                            $parentType = $context->getParentType();
                            Utils::invariant($parentType);
                            $context->reportError(new Error(
                                self::unknownArgMessage($node->getName()->getValue(), $fieldDef->name, $parentType->name),
                                [$node]
                            ));
                        }
                    }
                } else if ($argumentOf->getKind() === NodeType::DIRECTIVE) {
                    $directive = $context->getDirective();
                    if ($directive) {
                        $directiveArgDef = null;
                        foreach ($directive->args as $arg) {
                            if ($arg->name === $node->getName()->getValue()) {
                                $directiveArgDef = $arg;
                                break;
                            }
                        }
                        if (!$directiveArgDef) {
                            $context->reportError(new Error(
                                self::unknownDirectiveArgMessage($node->getName()->getValue(), $directive->name),
                                [$node]
                            ));
                        }
                    }
                }
            }
        ];
    }
}
