<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\ExecutableDefinitionNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\TypeSystemDefinitionNode;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Validator\QueryValidationContext;

/**
 * Executable definitions.
 *
 * A GraphQL document is only valid for execution if all definitions are either
 * operation or fragment definitions.
 */
class ExecutableDefinitions extends ValidationRule
{
    public function getVisitor(QueryValidationContext $context): array
    {
        return [
            NodeKind::DOCUMENT => static function (DocumentNode $node) use ($context): VisitorOperation {
                foreach ($node->definitions as $definition) {
                    if (! $definition instanceof ExecutableDefinitionNode) {
                        assert($definition instanceof TypeSystemDefinitionNode, 'only other option');

                        $context->reportError(new Error(
                            static::nonExecutableDefinitionMessage($definition->name->value),
                            [$definition->name]
                        ));
                    }
                }

                return Visitor::skipNode();
            },
        ];
    }

    public static function nonExecutableDefinitionMessage(string $defName): string
    {
        return "The \"{$defName}\" definition is not executable.";
    }
}
