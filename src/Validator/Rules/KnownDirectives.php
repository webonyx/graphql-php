<?php
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Validator\ValidationContext;

class KnownDirectives extends AbstractValidationRule
{
    static function unknownDirectiveMessage($directiveName)
    {
        return "Unknown directive \"$directiveName\".";
    }

    static function misplacedDirectiveMessage($directiveName, $location)
    {
        return "Directive \"$directiveName\" may not be used on \"$location\".";
    }

    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::DIRECTIVE => function (DirectiveNode $node, $key, $parent, $path, $ancestors) use ($context) {
                $directiveDef = null;
                foreach ($context->getSchema()->getDirectives() as $def) {
                    if ($def->name === $node->name->value) {
                        $directiveDef = $def;
                        break;
                    }
                }

                if (!$directiveDef) {
                    $context->reportError(new Error(
                        self::unknownDirectiveMessage($node->name->value),
                        [$node]
                    ));
                    return;
                }
                $candidateLocation = $this->getDirectiveLocationForASTPath($ancestors);

                if (!$candidateLocation) {
                    $context->reportError(new Error(
                        self::misplacedDirectiveMessage($node->name->value, $node->type),
                        [$node]
                    ));
                } else if (!in_array($candidateLocation, $directiveDef->locations)) {
                    $context->reportError(new Error(
                        self::misplacedDirectiveMessage($node->name->value, $candidateLocation),
                        [ $node ]
                    ));
                }
            }
        ];
    }

    private function getDirectiveLocationForASTPath(array $ancestors)
    {
        $appliedTo = $ancestors[count($ancestors) - 1];
        switch ($appliedTo->kind) {
            case NodeKind::OPERATION_DEFINITION:
                switch ($appliedTo->operation) {
                    case 'query': return DirectiveLocation::QUERY;
                    case 'mutation': return DirectiveLocation::MUTATION;
                    case 'subscription': return DirectiveLocation::SUBSCRIPTION;
                }
                break;
            case NodeKind::FIELD:
                return DirectiveLocation::FIELD;
            case NodeKind::FRAGMENT_SPREAD:
                return DirectiveLocation::FRAGMENT_SPREAD;
            case NodeKind::INLINE_FRAGMENT:
                return DirectiveLocation::INLINE_FRAGMENT;
            case NodeKind::FRAGMENT_DEFINITION:
                return DirectiveLocation::FRAGMENT_DEFINITION;
            case NodeKind::SCHEMA_DEFINITION:
                return DirectiveLocation::SCHEMA;
            case NodeKind::SCALAR_TYPE_DEFINITION:
            case NodeKind::SCALAR_TYPE_EXTENSION:
                return DirectiveLocation::SCALAR;
            case NodeKind::OBJECT_TYPE_DEFINITION:
            case NodeKind::OBJECT_TYPE_EXTENSION:
                return DirectiveLocation::OBJECT;
            case NodeKind::FIELD_DEFINITION:
                return DirectiveLocation::FIELD_DEFINITION;
            case NodeKind::INTERFACE_TYPE_DEFINITION:
            case NodeKind::INTERFACE_TYPE_EXTENSION:
                return DirectiveLocation::IFACE;
            case NodeKind::UNION_TYPE_DEFINITION:
            case NodeKind::UNION_TYPE_EXTENSION:
                return DirectiveLocation::UNION;
            case NodeKind::ENUM_TYPE_DEFINITION:
            case NodeKind::ENUM_TYPE_EXTENSION:
                return DirectiveLocation::ENUM;
            case NodeKind::ENUM_VALUE_DEFINITION:
                return DirectiveLocation::ENUM_VALUE;
            case NodeKind::INPUT_OBJECT_TYPE_DEFINITION:
            case NodeKind::INPUT_OBJECT_TYPE_EXTENSION:
                return DirectiveLocation::INPUT_OBJECT;
            case NodeKind::INPUT_VALUE_DEFINITION:
                $parentNode = $ancestors[count($ancestors) - 3];
                return $parentNode instanceof InputObjectTypeDefinitionNode
                    ? DirectiveLocation::INPUT_FIELD_DEFINITION
                    : DirectiveLocation::ARGUMENT_DEFINITION;
        }
    }
}
