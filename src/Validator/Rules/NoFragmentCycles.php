<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Utils\Utils;
use GraphQL\Validator\ValidationContext;
use function array_pop;
use function array_slice;
use function count;
use function implode;
use function sprintf;

class NoFragmentCycles extends ValidationRule
{
    /** @var bool[] */
    public $visitedFrags;

    /** @var FragmentSpreadNode[] */
    public $spreadPath;

    /** @var (int|null)[] */
    public $spreadPathIndexByName;

    public function getVisitor(ValidationContext $context)
    {
        // Tracks already visited fragments to maintain O(N) and to ensure that cycles
        // are not redundantly reported.
        $this->visitedFrags = [];

        // Array of AST nodes used to produce meaningful errors
        $this->spreadPath = [];

        // Position in the spread path
        $this->spreadPathIndexByName = [];

        return [
            NodeKind::OPERATION_DEFINITION => static function () : VisitorOperation {
                return Visitor::skipNode();
            },
            NodeKind::FRAGMENT_DEFINITION  => function (FragmentDefinitionNode $node) use ($context) : VisitorOperation {
                $this->detectCycleRecursive($node, $context);

                return Visitor::skipNode();
            },
        ];
    }

    private function detectCycleRecursive(FragmentDefinitionNode $fragment, ValidationContext $context)
    {
        if (isset($this->visitedFrags[$fragment->name->value])) {
            return;
        }

        $fragmentName                      = $fragment->name->value;
        $this->visitedFrags[$fragmentName] = true;

        $spreadNodes = $context->getFragmentSpreads($fragment);

        if (count($spreadNodes) === 0) {
            return;
        }

        $this->spreadPathIndexByName[$fragmentName] = count($this->spreadPath);

        for ($i = 0; $i < count($spreadNodes); $i++) {
            $spreadNode = $spreadNodes[$i];
            $spreadName = $spreadNode->name->value;
            $cycleIndex = $this->spreadPathIndexByName[$spreadName] ?? null;

            $this->spreadPath[] = $spreadNode;
            if ($cycleIndex === null) {
                $spreadFragment = $context->getFragment($spreadName);
                if ($spreadFragment) {
                    $this->detectCycleRecursive($spreadFragment, $context);
                }
            } else {
                $cyclePath     = array_slice($this->spreadPath, $cycleIndex);
                $fragmentNames = Utils::map(array_slice($cyclePath, 0, -1), static function ($s) {
                    return $s->name->value;
                });

                $context->reportError(new Error(
                    self::cycleErrorMessage($spreadName, $fragmentNames),
                    $cyclePath
                ));
            }
            array_pop($this->spreadPath);
        }

        $this->spreadPathIndexByName[$fragmentName] = null;
    }

    /**
     * @param string[] $spreadNames
     */
    public static function cycleErrorMessage($fragName, array $spreadNames = [])
    {
        return sprintf(
            'Cannot spread fragment "%s" within itself%s.',
            $fragName,
            count($spreadNames) > 0 ? ' via ' . implode(', ', $spreadNames) : ''
        );
    }
}
