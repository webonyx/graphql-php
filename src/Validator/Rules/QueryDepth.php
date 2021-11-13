<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Validator\ValidationContext;

use function sprintf;

class QueryDepth extends QuerySecurityRule
{
    protected int $maxQueryDepth;

    public function __construct($maxQueryDepth)
    {
        $this->setMaxQueryDepth($maxQueryDepth);
    }

    public function getVisitor(ValidationContext $context): array
    {
        return $this->invokeIfNeeded(
            $context,
            [
                NodeKind::OPERATION_DEFINITION => [
                    'leave' => function (OperationDefinitionNode $operationDefinition) use ($context): void {
                        $maxDepth = $this->fieldDepth($operationDefinition);

                        if ($maxDepth <= $this->getMaxQueryDepth()) {
                            return;
                        }

                        $context->reportError(
                            new Error(static::maxQueryDepthErrorMessage($this->getMaxQueryDepth(), $maxDepth))
                        );
                    },
                ],
            ]
        );
    }

    protected function fieldDepth($node, $depth = 0, $maxDepth = 0)
    {
        if (isset($node->selectionSet) && $node->selectionSet instanceof SelectionSetNode) {
            foreach ($node->selectionSet->selections as $childNode) {
                $maxDepth = $this->nodeDepth($childNode, $depth, $maxDepth);
            }
        }

        return $maxDepth;
    }

    protected function nodeDepth(Node $node, $depth = 0, $maxDepth = 0)
    {
        switch (true) {
            case $node instanceof FieldNode:
                // node has children?
                if ($node->selectionSet !== null) {
                    // update maxDepth if needed
                    if ($depth > $maxDepth) {
                        $maxDepth = $depth;
                    }

                    $maxDepth = $this->fieldDepth($node, $depth + 1, $maxDepth);
                }

                break;

            case $node instanceof InlineFragmentNode:
                $maxDepth = $this->fieldDepth($node, $depth, $maxDepth);

                break;

            case $node instanceof FragmentSpreadNode:
                $fragment = $this->getFragment($node);

                if ($fragment !== null) {
                    $maxDepth = $this->fieldDepth($fragment, $depth, $maxDepth);
                }

                break;
        }

        return $maxDepth;
    }

    public function getMaxQueryDepth()
    {
        return $this->maxQueryDepth;
    }

    /**
     * Set max query depth. If equal to 0 no check is done. Must be greater or equal to 0.
     */
    public function setMaxQueryDepth($maxQueryDepth): void
    {
        $this->checkIfGreaterOrEqualToZero('maxQueryDepth', $maxQueryDepth);

        $this->maxQueryDepth = (int) $maxQueryDepth;
    }

    public static function maxQueryDepthErrorMessage($max, $count)
    {
        return sprintf('Max query depth should be %d but got %d.', $max, $count);
    }

    protected function isEnabled(): bool
    {
        return $this->getMaxQueryDepth() !== self::DISABLED;
    }
}
