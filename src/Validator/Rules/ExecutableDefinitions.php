<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\ExecutableDefinitionNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\SchemaExtensionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionNode;
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
                        if ($definition instanceof SchemaDefinitionNode || $definition instanceof SchemaExtensionNode) {
                            $defName = 'schema';
                        } else {
                            assert(
                                $definition instanceof TypeDefinitionNode || $definition instanceof TypeExtensionNode,
                                'only other option'
                            );
                            $defName = "\"{$definition->getName()->value}\"";
                        }

                        $context->reportError(new Error(
                            static::nonExecutableDefinitionMessage($defName),
                            [$definition]
                        ));
                    }
                }

                return Visitor::skipNode();
            },
        ];
    }

    public static function nonExecutableDefinitionMessage(string $defName): string
    {
        return "The {$defName} definition is not executable.";
    }
}
