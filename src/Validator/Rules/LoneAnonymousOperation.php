<?php
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Document;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeType;
use GraphQL\Language\AST\OperationDefinition;
use GraphQL\Utils;
use GraphQL\Validator\ValidationContext;

/**
 * Lone anonymous operation
 *
 * A GraphQL document is only valid if when it contains an anonymous operation
 * (the query short-hand) that it contains only that one operation definition.
 */
class LoneAnonymousOperation
{
    static function anonOperationNotAloneMessage()
    {
        return 'This anonymous operation must be the only defined operation.';
    }

    public function __invoke(ValidationContext $context)
    {
        $operationCount = 0;
        return [
            NodeType::DOCUMENT => function(Document $node) use (&$operationCount) {
                $tmp = Utils::filter(
                    $node->getDefinitions(),
                    function ($definition) {
                        return $definition->getKind() === NodeType::OPERATION_DEFINITION;
                    }
                );
                $operationCount = count($tmp);
            },
            NodeType::OPERATION_DEFINITION => function(OperationDefinition $node) use (&$operationCount, $context) {
                if (!$node->getName() && $operationCount > 1) {
                    $context->reportError(
                        new Error(self::anonOperationNotAloneMessage(), [$node])
                    );
                }
            }
        ];
    }
}
