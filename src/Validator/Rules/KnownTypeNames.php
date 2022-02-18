<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use function array_keys;
use function count;
use GraphQL\Error\Error;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Utils\Utils;
use GraphQL\Validator\QueryValidationContext;

/**
 * Known type names.
 *
 * A GraphQL document is only valid if referenced types (specifically
 * variable definitions and fragment conditions) are defined by the type schema.
 */
class KnownTypeNames extends ValidationRule
{
    public function getVisitor(QueryValidationContext $context): array
    {
        $skip = static function (): VisitorOperation {
            return Visitor::skipNode();
        };

        return [
            // TODO: when validating IDL, re-enable these. Experimental version does not
            // add unreferenced types, resulting in false-positive errors. Squelched
            // errors for now.
            NodeKind::OBJECT_TYPE_DEFINITION => $skip,
            NodeKind::INTERFACE_TYPE_DEFINITION => $skip,
            NodeKind::UNION_TYPE_DEFINITION => $skip,
            NodeKind::INPUT_OBJECT_TYPE_DEFINITION => $skip,
            NodeKind::NAMED_TYPE => static function (NamedTypeNode $node) use ($context): void {
                $schema = $context->getSchema();
                $typeName = $node->name->value;
                $type = $schema->getType($typeName);
                if ($type !== null) {
                    return;
                }

                $context->reportError(new Error(
                    static::unknownTypeMessage(
                        $typeName,
                        Utils::suggestionList($typeName, array_keys($schema->getTypeMap()))
                    ),
                    [$node]
                ));
            },
        ];
    }

    /**
     * @param array<string> $suggestedTypes
     */
    public static function unknownTypeMessage(string $type, array $suggestedTypes): string
    {
        $message = 'Unknown type "' . $type . '".';
        if (count($suggestedTypes) > 0) {
            $message .= ' Did you mean ' . Utils::quotedOrList($suggestedTypes) . '?';
        }

        return $message;
    }
}
