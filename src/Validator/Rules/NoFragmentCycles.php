<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Validator\QueryValidationContext;

class NoFragmentCycles extends ValidationRule
{
    /** @var array<string, bool> */
    protected array $visitedFrags;

    /** @var array<int, FragmentSpreadNode> */
    protected array $spreadPath;

    /** @var array<string, int|null> */
    protected array $spreadPathIndexByName;

    public function getVisitor(QueryValidationContext $context): array
    {
        // Tracks already visited fragments to maintain O(N) and to ensure that cycles
        // are not redundantly reported.
        $this->visitedFrags = [];

        // Array of AST nodes used to produce meaningful errors
        $this->spreadPath = [];

        // Position in the spread path
        $this->spreadPathIndexByName = [];

        return [
            NodeKind::OPERATION_DEFINITION => static fn (): VisitorOperation => Visitor::skipNode(),
            NodeKind::FRAGMENT_DEFINITION => function (FragmentDefinitionNode $node) use ($context): VisitorOperation {
                $this->detectCycleRecursive($node, $context);

                return Visitor::skipNode();
            },
        ];
    }

    protected function detectCycleRecursive(FragmentDefinitionNode $fragment, QueryValidationContext $context): void
    {
        if (isset($this->visitedFrags[$fragment->name->value])) {
            return;
        }

        $fragmentName = $fragment->name->value;
        $this->visitedFrags[$fragmentName] = true;

        $spreadNodes = $context->getFragmentSpreads($fragment);

        if ($spreadNodes === []) {
            return;
        }

        $this->spreadPathIndexByName[$fragmentName] = count($this->spreadPath);

        foreach ($spreadNodes as $spreadNode) {
            $spreadName = $spreadNode->name->value;
            $cycleIndex = $this->spreadPathIndexByName[$spreadName] ?? null;

            $this->spreadPath[] = $spreadNode;
            if ($cycleIndex === null) {
                $spreadFragment = $context->getFragment($spreadName);
                if ($spreadFragment !== null) {
                    $this->detectCycleRecursive($spreadFragment, $context);
                }
            } else {
                $cyclePath = array_slice($this->spreadPath, $cycleIndex);
                $fragmentNames = [];
                foreach (array_slice($cyclePath, 0, -1) as $frag) {
                    $fragmentNames[] = $frag->name->value;
                }

                $context->reportError(new Error(
                    static::cycleErrorMessage($spreadName, $fragmentNames),
                    $cyclePath
                ));
            }

            array_pop($this->spreadPath);
        }

        $this->spreadPathIndexByName[$fragmentName] = null;
    }

    /** @param array<string> $spreadNames */
    public static function cycleErrorMessage(string $fragName, array $spreadNames = []): string
    {
        $via = $spreadNames === []
            ? ''
            : ' via ' . implode(', ', $spreadNames);

        return "Cannot spread fragment \"{$fragName}\" within itself{$via}.";
    }
}
