<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumTypeExtensionNode;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeExtensionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeExtensionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeExtensionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\SchemaExtensionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeExtensionNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Language\Visitor;
use GraphQL\Type\Definition\Directive;
use GraphQL\Validator\QueryValidationContext;
use GraphQL\Validator\SDLValidationContext;
use GraphQL\Validator\ValidationContext;

/**
 * @phpstan-import-type VisitorArray from Visitor
 */
class KnownDirectives extends ValidationRule
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
     * @throws InvariantViolation
     *
     * @phpstan-return VisitorArray
     */
    public function getASTVisitor(ValidationContext $context): array
    {
        $locationsMap = [];
        $schema = $context->getSchema();
        $definedDirectives = $schema === null
            ? Directive::getInternalDirectives()
            : $schema->getDirectives();

        foreach ($definedDirectives as $directive) {
            $locationsMap[$directive->name] = $directive->locations;
        }

        $astDefinition = $context->getDocument()->definitions;

        foreach ($astDefinition as $def) {
            if ($def instanceof DirectiveDefinitionNode) {
                $locationNames = [];
                foreach ($def->locations as $location) {
                    $locationNames[] = $location->value;
                }

                $locationsMap[$def->name->value] = $locationNames;
            }
        }

        return [
            NodeKind::DIRECTIVE => function (
                DirectiveNode $node,
                $key,
                $parent,
                $path,
                $ancestors
            ) use (
                $context,
                $locationsMap
            ): void {
                $name = $node->name->value;
                $locations = $locationsMap[$name] ?? null;

                if ($locations === null) {
                    $context->reportError(new Error(
                        static::unknownDirectiveMessage($name),
                        [$node]
                    ));

                    return;
                }

                $candidateLocation = $this->getDirectiveLocationForASTPath($ancestors);

                if ($candidateLocation === '' || in_array($candidateLocation, $locations, true)) {
                    return;
                }

                $context->reportError(
                    new Error(
                        static::misplacedDirectiveMessage($name, $candidateLocation),
                        [$node]
                    )
                );
            },
        ];
    }

    public static function unknownDirectiveMessage(string $directiveName): string
    {
        return "Unknown directive \"@{$directiveName}\".";
    }

    /**
     * @param array<Node|NodeList<Node>> $ancestors
     *
     * @throws \Exception
     */
    protected function getDirectiveLocationForASTPath(array $ancestors): string
    {
        $appliedTo = $ancestors[count($ancestors) - 1];

        switch (true) {
            case $appliedTo instanceof OperationDefinitionNode:
                switch ($appliedTo->operation) {
                    case 'query':
                        return DirectiveLocation::QUERY;
                    case 'mutation':
                        return DirectiveLocation::MUTATION;
                    case 'subscription':
                        return DirectiveLocation::SUBSCRIPTION;
                }
                // no break, since all possible cases were handled
            case $appliedTo instanceof FieldNode:
                return DirectiveLocation::FIELD;
            case $appliedTo instanceof FragmentSpreadNode:
                return DirectiveLocation::FRAGMENT_SPREAD;
            case $appliedTo instanceof InlineFragmentNode:
                return DirectiveLocation::INLINE_FRAGMENT;
            case $appliedTo instanceof FragmentDefinitionNode:
                return DirectiveLocation::FRAGMENT_DEFINITION;
            case $appliedTo instanceof VariableDefinitionNode:
                return DirectiveLocation::VARIABLE_DEFINITION;
            case $appliedTo instanceof SchemaDefinitionNode:
            case $appliedTo instanceof SchemaExtensionNode:
                return DirectiveLocation::SCHEMA;
            case $appliedTo instanceof ScalarTypeDefinitionNode:
            case $appliedTo instanceof ScalarTypeExtensionNode:
                return DirectiveLocation::SCALAR;
            case $appliedTo instanceof ObjectTypeDefinitionNode:
            case $appliedTo instanceof ObjectTypeExtensionNode:
                return DirectiveLocation::OBJECT;
            case $appliedTo instanceof FieldDefinitionNode:
                return DirectiveLocation::FIELD_DEFINITION;
            case $appliedTo instanceof InterfaceTypeDefinitionNode:
            case $appliedTo instanceof InterfaceTypeExtensionNode:
                return DirectiveLocation::IFACE;
            case $appliedTo instanceof UnionTypeDefinitionNode:
            case $appliedTo instanceof UnionTypeExtensionNode:
                return DirectiveLocation::UNION;
            case $appliedTo instanceof EnumTypeDefinitionNode:
            case $appliedTo instanceof EnumTypeExtensionNode:
                return DirectiveLocation::ENUM;
            case $appliedTo instanceof EnumValueDefinitionNode:
                return DirectiveLocation::ENUM_VALUE;
            case $appliedTo instanceof InputObjectTypeDefinitionNode:
            case $appliedTo instanceof InputObjectTypeExtensionNode:
                return DirectiveLocation::INPUT_OBJECT;
            case $appliedTo instanceof InputValueDefinitionNode:
                $parentNode = $ancestors[count($ancestors) - 3];

                return $parentNode instanceof InputObjectTypeDefinitionNode
                    ? DirectiveLocation::INPUT_FIELD_DEFINITION
                    : DirectiveLocation::ARGUMENT_DEFINITION;
            default:
                $unknownLocation = get_class($appliedTo);
                throw new \Exception("Unknown directive location: {$unknownLocation}.");
        }
    }

    public static function misplacedDirectiveMessage(string $directiveName, string $location): string
    {
        return "Directive \"{$directiveName}\" may not be used on \"{$location}\".";
    }
}
