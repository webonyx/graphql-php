<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use Exception;
use GraphQL\Error\Error;
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
use GraphQL\Language\AST\SchemaTypeExtensionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeExtensionNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Type\Definition\Directive;
use GraphQL\Utils\Utils;
use GraphQL\Validator\ASTValidationContext;
use GraphQL\Validator\SDLValidationContext;
use GraphQL\Validator\ValidationContext;
use function array_map;
use function count;
use function get_class;
use function in_array;
use function sprintf;

class KnownDirectives extends ValidationRule
{
    public function getVisitor(ValidationContext $context)
    {
        return $this->getASTVisitor($context);
    }

    public function getSDLVisitor(SDLValidationContext $context)
    {
        return $this->getASTVisitor($context);
    }

    public function getASTVisitor(ASTValidationContext $context)
    {
        $locationsMap      = [];
        $schema            = $context->getSchema();
        $definedDirectives = $schema
            ? $schema->getDirectives()
            : Directive::getInternalDirectives();

        foreach ($definedDirectives as $directive) {
            $locationsMap[$directive->name] = $directive->locations;
        }

        $astDefinition = $context->getDocument()->definitions;

        foreach ($astDefinition as $def) {
            if (! ($def instanceof DirectiveDefinitionNode)) {
                continue;
            }

            $locationsMap[$def->name->value] = Utils::map(
                $def->locations,
                static function ($name) : string {
                    return $name->value;
                }
            );
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
            ) : void {
                $name      = $node->name->value;
                $locations = $locationsMap[$name] ?? null;

                if (! $locations) {
                    $context->reportError(new Error(
                        self::unknownDirectiveMessage($name),
                        [$node]
                    ));

                    return;
                }

                $candidateLocation = $this->getDirectiveLocationForASTPath($ancestors);

                if (! $candidateLocation || in_array($candidateLocation, $locations, true)) {
                    return;
                }
                $context->reportError(
                    new Error(
                        self::misplacedDirectiveMessage($name, $candidateLocation),
                        [$node]
                    )
                );
            },
        ];
    }

    public static function unknownDirectiveMessage($directiveName)
    {
        return sprintf('Unknown directive "%s".', $directiveName);
    }

    /**
     * @param Node[]|NodeList[] $ancestors The type is actually (Node|NodeList)[] but this PSR-5 syntax is so far not supported by most of the tools
     *
     * @return string
     */
    private function getDirectiveLocationForASTPath(array $ancestors)
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
                break;
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
            case $appliedTo instanceof SchemaTypeExtensionNode:
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
        }

        throw new Exception('Unknown directive location: ' . get_class($appliedTo));
    }

    public static function misplacedDirectiveMessage($directiveName, $location)
    {
        return sprintf('Directive "%s" may not be used on "%s".', $directiveName, $location);
    }
}
