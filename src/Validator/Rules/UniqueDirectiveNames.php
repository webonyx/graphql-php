<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use function array_key_exists;
use GraphQL\Error\Error;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Validator\SDLValidationContext;

/**
 * Unique directive names.
 *
 * A GraphQL document is only valid if all defined directives have unique names.
 */
class UniqueDirectiveNames extends ValidationRule
{
    public function getSDLVisitor(SDLValidationContext $context): array
    {
        $schema = $context->getSchema();
        /** @var array<string, NameNode> $knownDirectiveNames */
        $knownDirectiveNames = [];
        $checkDirectiveName = static function ($node) use ($context, $schema, &$knownDirectiveNames): ?VisitorOperation {
            $directiveName = $node->name->value;

            if ($schema !== null && $schema->getDirective($directiveName) !== null) {
                $context->reportError(
                    new Error(
                        'Directive "@' . $directiveName . '" already exists in the schema. It cannot be redefined.',
                        $node->name,
                    ),
                );

                return null;
            }

            if (array_key_exists($directiveName, $knownDirectiveNames)) {
                $context->reportError(
                    new Error(
                        'There can be only one directive named "@' . $directiveName . '".',
                        [
                            $knownDirectiveNames[$directiveName],
                            $node->name,
                        ]
                    ),
                );
            } else {
                $knownDirectiveNames[$directiveName] = $node->name;
            }

            return Visitor::skipNode();
        };

        return [
            NodeKind::DIRECTIVE_DEFINITION => $checkDirectiveName,
        ];
    }
}
