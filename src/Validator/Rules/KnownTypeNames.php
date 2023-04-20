<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeSystemDefinitionNode;
use GraphQL\Language\AST\TypeSystemExtensionNode;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\Utils;
use GraphQL\Validator\QueryValidationContext;
use GraphQL\Validator\SDLValidationContext;
use GraphQL\Validator\ValidationContext;

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
    /** @throws InvariantViolation */
    public function getVisitor(QueryValidationContext $context): array
    {
        return $this->getASTVisitor($context);
    }

    /** @throws InvariantViolation */
    public function getSDLVisitor(SDLValidationContext $context): array
    {
        return $this->getASTVisitor($context);
    }

    /**
     * @phpstan-return VisitorArray
     *
     * @throws InvariantViolation
     */
    public function getASTVisitor(ValidationContext $context): array
    {
        /** @var array<int, string> $definedTypes */
        $definedTypes = [];
        foreach ($context->getDocument()->definitions as $def) {
            if ($def instanceof TypeDefinitionNode) {
                $definedTypes[] = $def->getName()->value;
            }
        }

        $standardTypeNames = \array_keys(Type::builtInTypes());

        return [
            NodeKind::NAMED_TYPE => static function (NamedTypeNode $node, $_1, $parent, $_2, $ancestors) use ($context, $definedTypes, $standardTypeNames): void {
                $typeName = $node->name->value;
                $schema = $context->getSchema();

                if (\in_array($typeName, $definedTypes, true)) {
                    return;
                }

                if ($schema !== null && $schema->hasType($typeName)) {
                    return;
                }

                $definitionNode = $ancestors[2] ?? $parent;
                $isSDL = $definitionNode instanceof TypeSystemDefinitionNode || $definitionNode instanceof TypeSystemExtensionNode;
                if ($isSDL && \in_array($typeName, $standardTypeNames, true)) {
                    return;
                }

                $existingTypesMap = $schema !== null
                    ? $schema->getTypeMap()
                    : [];
                $typeNames = [
                    ...\array_keys($existingTypesMap),
                    ...$definedTypes,
                ];
                $context->reportError(new Error(
                    static::unknownTypeMessage(
                        $typeName,
                        Utils::suggestionList(
                            $typeName,
                            $isSDL
                                ? [...$standardTypeNames, ...$typeNames]
                                : $typeNames
                        )
                    ),
                    [$node]
                ));
            },
        ];
    }

    /** @param array<string> $suggestedTypes */
    public static function unknownTypeMessage(string $type, array $suggestedTypes): string
    {
        $message = "Unknown type \"{$type}\".";

        if ($suggestedTypes !== []) {
            $suggestionList = Utils::quotedOrList($suggestedTypes);
            $message .= " Did you mean {$suggestionList}?";
        }

        return $message;
    }
}
