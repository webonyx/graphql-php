<?php
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Utils\Utils;
use GraphQL\Validator\ValidationContext;

class KnownArgumentNames extends AbstractValidationRule
{
    public static function unknownArgMessage($argName, $fieldName, $type)
    {
        return "Unknown argument \"$argName\" on field \"$fieldName\" of type \"$type\".";
    }

    public static function unknownDirectiveArgMessage($argName, $directiveName)
    {
        return "Unknown argument \"$argName\" on directive \"@$directiveName\".";
    }

    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::ARGUMENT => function(ArgumentNode $node, $key, $parent, $path, $ancestors) use ($context) {
                $argumentOf = $ancestors[count($ancestors) - 1];
                if ($argumentOf->kind === NodeKind::FIELD) {
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
                            $context->reportError(new Error(
                                self::unknownArgMessage($node->name->value, $fieldDef->name, $parentType->name),
                                [$node]
                            ));
                        }
                    }
                } else if ($argumentOf->kind === NodeKind::DIRECTIVE) {
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
                            $context->reportError(new Error(
                                self::unknownDirectiveArgMessage($node->name->value, $directive->name),
                                [$node]
                            ));
                        }
                    }
                }
            }
        ];
    }
}
