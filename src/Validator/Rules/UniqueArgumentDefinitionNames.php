<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeExtensionNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Validator\SDLValidationContext;

/**
 * Unique argument definition names.
 *
 * A GraphQL Object or Interface type is only valid if all its fields have uniquely named arguments.
 * A GraphQL Directive is only valid if all its arguments are uniquely named.
 */
class UniqueArgumentDefinitionNames extends ValidationRule
{
    public function getSDLVisitor(SDLValidationContext $context): array
    {
        $checkArgUniquenessPerField = static function ($node) use ($context): VisitorOperation {
            assert(
                $node instanceof InterfaceTypeDefinitionNode
                || $node instanceof InterfaceTypeExtensionNode
                || $node instanceof ObjectTypeDefinitionNode
                || $node instanceof ObjectTypeExtensionNode
            );

            foreach ($node->fields as $fieldDef) {
                self::checkArgUniqueness("{$node->name->value}.{$fieldDef->name->value}", $fieldDef->arguments, $context);
            }

            return Visitor::skipNode();
        };

        return [
            NodeKind::DIRECTIVE_DEFINITION => static fn (DirectiveDefinitionNode $node): VisitorOperation => self::checkArgUniqueness("@{$node->name->value}", $node->arguments, $context),
            NodeKind::INTERFACE_TYPE_DEFINITION => $checkArgUniquenessPerField,
            NodeKind::INTERFACE_TYPE_EXTENSION => $checkArgUniquenessPerField,
            NodeKind::OBJECT_TYPE_DEFINITION => $checkArgUniquenessPerField,
            NodeKind::OBJECT_TYPE_EXTENSION => $checkArgUniquenessPerField,
        ];
    }

    /** @param NodeList<InputValueDefinitionNode> $arguments */
    private static function checkArgUniqueness(string $parentName, NodeList $arguments, SDLValidationContext $context): VisitorOperation
    {
        $seenArgs = [];
        foreach ($arguments as $argument) {
            $seenArgs[$argument->name->value][] = $argument;
        }

        foreach ($seenArgs as $argName => $argNodes) {
            if (count($argNodes) > 1) {
                $context->reportError(
                    new Error(
                        "Argument \"{$parentName}({$argName}:)\" can only be defined once.",
                        $argNodes,
                    ),
                );
            }
        }

        return Visitor::skipNode();
    }
}
