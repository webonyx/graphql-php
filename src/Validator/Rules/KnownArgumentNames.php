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
            Node::ARGUMENT => function(Argument $node, $key, $parent, $path, $ancestors) use ($context) {
                $argumentOf = $ancestors[count($ancestors) - 1];
                if ($argumentOf->kind === Node::FIELD) {
                    $fieldDef = $context->getFieldDef();

                    if ($fieldDef) {
                        $fieldArgDef = null;
                        foreach ($fieldDef->args as $arg) {
                            if ($arg->name === $node->name->value) {
                                $fieldArgDef = $arg;
                                break;
                            }
                        }
                        if (!$fieldArgDef) {
                            $parentType = $context->getParentType();
                            Utils::invariant($parentType);
                            return new Error(
                                self::unknownArgMessage($node->name->value, $fieldDef->name, $parentType->name),
                                [$node]
                            );
                        }
                    }
                } else if ($argumentOf->kind === Node::DIRECTIVE) {
                    $directive = $context->getDirective();
                    if ($directive) {
                        $directiveArgDef = null;
                        foreach ($directive->args as $arg) {
                            if ($arg->name === $node->name->value) {
                                $directiveArgDef = $arg;
                                break;
                            }
                        }
                        if (!$directiveArgDef) {
                            return new Error(
                                self::unknownDirectiveArgMessage($node->name->value, $directive->name),
                                [$node]
                            );
                        }
                    }
                }
            }
        ];
    }
}
