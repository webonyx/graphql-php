<?php
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Visitor;
use GraphQL\Utils\Utils;
use GraphQL\Validator\ValidationContext;

/**
 * Known type names
 *
 * A GraphQL document is only valid if referenced types (specifically
 * variable definitions and fragment conditions) are defined by the type schema.
 */
class KnownTypeNames extends AbstractValidationRule
{
    static function unknownTypeMessage($type, array $suggestedTypes)
    {
        $message = "Unknown type \"$type\".";
        if ($suggestedTypes) {
            $suggestions = Utils::quotedOrList($suggestedTypes);
            $message .= " Did you mean $suggestions?";
        }
        return $message;
    }

    public function getVisitor(ValidationContext $context)
    {
        $skip = function() { return Visitor::skipNode(); };

        return [
            // TODO: when validating IDL, re-enable these. Experimental version does not
            // add unreferenced types, resulting in false-positive errors. Squelched
            // errors for now.
            NodeKind::OBJECT_TYPE_DEFINITION => $skip,
            NodeKind::INTERFACE_TYPE_DEFINITION => $skip,
            NodeKind::UNION_TYPE_DEFINITION => $skip,
            NodeKind::INPUT_OBJECT_TYPE_DEFINITION => $skip,
            NodeKind::NAMED_TYPE => function(NamedTypeNode $node) use ($context) {
                $schema = $context->getSchema();
                $typeName = $node->name->value;
                $type = $schema->getType($typeName);
                if (!$type) {
                    $context->reportError(new Error(
                        self::unknownTypeMessage(
                            $typeName,
                            Utils::suggestionList($typeName, array_keys($schema->getTypeMap()))
                        ), [$node])
                    );
                }
            }
        ];
    }
}
