<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error;
use GraphQL\Language\AST\Directive;
use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\FragmentDefinition;
use GraphQL\Language\AST\FragmentSpread;
use GraphQL\Language\AST\InlineFragment;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\OperationDefinition;
use GraphQL\Validator\Messages;
use GraphQL\Validator\ValidationContext;

class KnownDirectives
{
    static function unknownDirectiveMessage($directiveName)
    {
        return "Unknown directive \"$directiveName\".";
    }

    static function misplacedDirectiveMessage($directiveName, $placement)
    {
        return "Directive \"$directiveName\" may not be used on \"$placement\".";
    }

    public function __invoke(ValidationContext $context)
    {
        return [
            Node::DIRECTIVE => function (Directive $node, $key, $parent, $path, $ancestors) use ($context) {
                $directiveDef = null;
                foreach ($context->getSchema()->getDirectives() as $def) {
                    if ($def->name === $node->name->value) {
                        $directiveDef = $def;
                        break;
                    }
                }

                if (!$directiveDef) {
                    return new Error(
                        self::unknownDirectiveMessage($node->name->value),
                        [$node]
                    );
                }
                $appliedTo = $ancestors[count($ancestors) - 1];

                if ($appliedTo instanceof OperationDefinition && !$directiveDef->onOperation) {
                    return new Error(
                        self::misplacedDirectiveMessage($node->name->value, 'operation'),
                        [$node]
                    );
                }
                if ($appliedTo instanceof Field && !$directiveDef->onField) {
                    return new Error(
                        self::misplacedDirectiveMessage($node->name->value, 'field'),
                        [$node]
                    );
                }

                $fragmentKind = (
                    $appliedTo instanceof FragmentSpread ||
                    $appliedTo instanceof InlineFragment ||
                    $appliedTo instanceof FragmentDefinition
                );

                if ($fragmentKind && !$directiveDef->onFragment) {
                    return new Error(
                        self::misplacedDirectiveMessage($node->name->value, 'fragment'),
                        [$node]
                    );
                }
            }
        ];
    }
}
