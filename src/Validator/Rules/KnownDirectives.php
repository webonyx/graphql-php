<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error\Error;
use GraphQL\Language\AST\Directive;
use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\FragmentDefinition;
use GraphQL\Language\AST\FragmentSpread;
use GraphQL\Language\AST\InlineFragment;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeType;
use GraphQL\Language\AST\OperationDefinition;
use GraphQL\Validator\Messages;
use GraphQL\Validator\ValidationContext;
use GraphQL\Type\Definition\Directive as DirectiveDef;

class KnownDirectives
{
    static function unknownDirectiveMessage($directiveName)
    {
        return "Unknown directive \"$directiveName\".";
    }

    static function misplacedDirectiveMessage($directiveName, $location)
    {
        return "Directive \"$directiveName\" may not be used on \"$location\".";
    }

    public function __invoke(ValidationContext $context)
    {
        return [
            NodeType::DIRECTIVE => function (Directive $node, $key, $parent, $path, $ancestors) use ($context) {
                $directiveDef = null;
                foreach ($context->getSchema()->getDirectives() as $def) {
                    if ($def->name === $node->getName()->getValue()) {
                        $directiveDef = $def;
                        break;
                    }
                }

                if (!$directiveDef) {
                    $context->reportError(new Error(
                        self::unknownDirectiveMessage($node->getName()->getValue()),
                        [$node]
                    ));
                    return ;
                }
                $appliedTo = $ancestors[count($ancestors) - 1];
                $candidateLocation = $this->getLocationForAppliedNode($appliedTo);

                if (!$candidateLocation) {
                    $context->reportError(new Error(
                        self::misplacedDirectiveMessage($node->getName()->getValue(), $node->type),
                        [$node]
                    ));
                } else if (!in_array($candidateLocation, $directiveDef->locations)) {
                    $context->reportError(new Error(
                        self::misplacedDirectiveMessage($node->getName()->getValue(), $candidateLocation),
                        [ $node ]
                    ));
                }
            }
        ];
    }

    private function getLocationForAppliedNode(Node $appliedTo)
    {
        switch ($appliedTo->getKind()) {
            case NodeType::OPERATION_DEFINITION:
                switch ($appliedTo->getOperation()) {
                    case 'query': return DirectiveDef::LOCATION_QUERY;
                    case 'mutation': return DirectiveDef::LOCATION_MUTATION;
                    case 'subscription': return DirectiveDef::LOCATION_SUBSCRIPTION;
                }
                break;
            case NodeType::FIELD: return DirectiveDef::LOCATION_FIELD;
            case NodeType::FRAGMENT_SPREAD: return DirectiveDef::LOCATION_FRAGMENT_SPREAD;
            case NodeType::INLINE_FRAGMENT: return DirectiveDef::LOCATION_INLINE_FRAGMENT;
            case NodeType::FRAGMENT_DEFINITION: return DirectiveDef::LOCATION_FRAGMENT_DEFINITION;
        }
    }
}
