<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Validator\QueryValidationContext;

class QueryDepth extends QuerySecurityRule
{
    /** @var array<string, bool> Fragment names which are already calculated in recursion */
    protected array $calculatedFragments = [];

    /** @var array<string, int> Depth of each fragment relative to where it is spread, -1 when it has no nested selections; reset per document */
    private array $fragmentDepths = [];

    /** @var array<string, bool> Fragment names which contain a cycle; reset per document */
    private array $cyclicFragments = [];

    protected int $maxQueryDepth;

    /** @throws \InvalidArgumentException */
    public function __construct(int $maxQueryDepth)
    {
        $this->setMaxQueryDepth($maxQueryDepth);
    }

    public function getVisitor(QueryValidationContext $context): array
    {
        $this->fragmentDepths = [];
        $this->cyclicFragments = [];

        return $this->invokeIfNeeded(
            $context,
            [
                NodeKind::OPERATION_DEFINITION => [
                    'leave' => function (OperationDefinitionNode $operationDefinition) use ($context): void {
                        $maxDepth = $this->fieldDepth($operationDefinition);

                        if ($maxDepth <= $this->maxQueryDepth) {
                            return;
                        }

                        $context->reportError(
                            new Error(static::maxQueryDepthErrorMessage($this->maxQueryDepth, $maxDepth))
                        );
                    },
                ],
            ]
        );
    }

    /** @param OperationDefinitionNode|FieldNode|InlineFragmentNode|FragmentDefinitionNode $node */
    protected function fieldDepth(Node $node, int $depth = 0, int $maxDepth = 0): int
    {
        if ($node->selectionSet instanceof SelectionSetNode) {
            foreach ($node->selectionSet->selections as $childNode) {
                $maxDepth = $this->nodeDepth($childNode, $depth, $maxDepth);
            }
        }

        return $maxDepth;
    }

    protected function nodeDepth(Node $node, int $depth = 0, int $maxDepth = 0): int
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
                    $name = $fragment->name->value;
                    if (isset($this->calculatedFragments[$name]) || isset($this->cyclicFragments[$name])) {
                        $this->cyclicFragments += $this->calculatedFragments;

                        return $this->maxQueryDepth + 1;
                    }

                    // Measure each fragment once, so spreading it repeatedly does not re-walk it
                    if (! isset($this->fragmentDepths[$name])) {
                        $this->calculatedFragments[$name] = true;
                        $fragmentDepth = $this->fieldDepth($fragment, 0, -1);
                        unset($this->calculatedFragments[$name]);

                        if (isset($this->cyclicFragments[$name])) {
                            return $this->maxQueryDepth + 1;
                        }

                        $this->fragmentDepths[$name] = $fragmentDepth;
                    }

                    $maxDepth = max($maxDepth, $depth + $this->fragmentDepths[$name]);
                }

                break;
        }

        return $maxDepth;
    }

    public function getMaxQueryDepth(): int
    {
        return $this->maxQueryDepth;
    }

    /**
     * Set max query depth. If equal to 0 no check is done. Must be greater or equal to 0.
     *
     * @throws \InvalidArgumentException
     */
    public function setMaxQueryDepth(int $maxQueryDepth): void
    {
        $this->checkIfGreaterOrEqualToZero('maxQueryDepth', $maxQueryDepth);

        $this->maxQueryDepth = $maxQueryDepth;
    }

    public static function maxQueryDepthErrorMessage(int $max, int $count): string
    {
        return "Max query depth should be {$max} but got {$count}.";
    }

    protected function isEnabled(): bool
    {
        return $this->maxQueryDepth !== self::DISABLED;
    }
}
