<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use function array_keys;
use function count;
use GraphQL\Error\Error;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeSystemDefinitionNode;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\Utils;
use GraphQL\Validator\ASTValidationContext;
use GraphQL\Validator\SDLValidationContext;
use GraphQL\Validator\QueryValidationContext;
use function in_array;

/**
 * Known type names.
 *
 * A GraphQL document is only valid if referenced types (specifically
 * variable definitions and fragment conditions) are defined by the type schema.
 *
 * @phpstan-import-type VisitorArray from \GraphQL\Language\Visitor
 */
class KnownTypeNames extends ValidationRule
{
    public function getVisitor(QueryValidationContext $context): array
    {
        return $this->getASTVisitor($context);
    }

    public function getSDLVisitor(SDLValidationContext $context): array
    {
        return $this->getASTVisitor($context);
    }

    /**
     * @phpstan-return VisitorArray
     */
    public function getASTVisitor(ASTValidationContext $context): array
    {
        /** @var array<int, string> $definedTypes */
        $definedTypes = [];
        foreach ($context->getDocument()->definitions as $def) {
            if ($def instanceof TypeDefinitionNode) {
                $definedTypes[] = $def->name->value;
            }
        }

        $standardTypeNames = array_keys(Type::getAllBuiltInTypes());

        return [
            NodeKind::NAMED_TYPE => static function (NamedTypeNode $node, $_1, $parent, $_2, $ancestors) use ($context, $definedTypes, $standardTypeNames): void {
                $typeName = $node->name->value;
                $schema = $context->getSchema();

                if (in_array($typeName, $definedTypes, true)) {
                    return;
                }

                if (null !== $schema && $schema->hasType($typeName)) {
                    return;
                }

                $definitionNode = $ancestors[2] ?? $parent;
                $isSDL = null !== $definitionNode && self::isSDLNode($definitionNode);
                if ($isSDL && in_array($typeName, $standardTypeNames, true)) {
                    return;
                }

                $existingTypesMap = null !== $schema
                    ? $schema->getTypeMap()
                    : [];
                $typeNames = [
                    ...array_keys($existingTypesMap),
                    ...$definedTypes,
                ];
                $context->reportError(new Error(
                    static::unknownTypeMessage(
                        $typeName,
                        Utils::suggestionList(
                            $typeName,
                            $isSDL
                                // TODO: order
                                ? [...$typeNames, ...$standardTypeNames]
                                : $typeNames
                        )
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

    /**
     * @param Node|array<int, Node> $value
     */
    public static function isSDLNode($value): bool
    {
        return $value instanceof Node
        // TODO: should be (TypeSystemDefinitionNode || TypeSystemExtensionNode)
        && $value instanceof TypeSystemDefinitionNode;
    }
}
