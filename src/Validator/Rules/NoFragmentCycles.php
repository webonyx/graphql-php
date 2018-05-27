<?php
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Visitor;
use GraphQL\Utils\Utils;
use GraphQL\Validator\ValidationContext;

class NoFragmentCycles extends AbstractValidationRule
{
    static function cycleErrorMessage($fragName, array $spreadNames = [])
    {
        $via = !empty($spreadNames) ? ' via ' . implode(', ', $spreadNames) : '';
        return "Cannot spread fragment \"$fragName\" within itself$via.";
    }

    public $visitedFrags;

    public $spreadPath;

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
            NodeKind::OPERATION_DEFINITION => function () {
                return Visitor::skipNode();
            },
            NodeKind::FRAGMENT_DEFINITION => function (FragmentDefinitionNode $node) use ($context) {
                if (!isset($this->visitedFrags[$node->name->value])) {
                    $this->detectCycleRecursive($node, $context);
                }
                return Visitor::skipNode();
            }
        ];
    }

    private function detectCycleRecursive(FragmentDefinitionNode $fragment, ValidationContext $context)
    {
        $fragmentName = $fragment->name->value;
        $this->visitedFrags[$fragmentName] = true;

        $spreadNodes = $context->getFragmentSpreads($fragment);

        if (empty($spreadNodes)) {
            return;
        }

        $this->spreadPathIndexByName[$fragmentName] = count($this->spreadPath);

        for ($i = 0; $i < count($spreadNodes); $i++) {
            $spreadNode = $spreadNodes[$i];
            $spreadName = $spreadNode->name->value;
            $cycleIndex = isset($this->spreadPathIndexByName[$spreadName]) ? $this->spreadPathIndexByName[$spreadName] : null;

            if ($cycleIndex === null) {
                $this->spreadPath[] = $spreadNode;
                if (empty($this->visitedFrags[$spreadName])) {
                    $spreadFragment = $context->getFragment($spreadName);
                    if ($spreadFragment) {
                        $this->detectCycleRecursive($spreadFragment, $context);
                    }
                }
                array_pop($this->spreadPath);
            } else {
                $cyclePath = array_slice($this->spreadPath, $cycleIndex);
                $nodes = $cyclePath;

                if (is_array($spreadNode)) {
                    $nodes = array_merge($nodes, $spreadNode);
                } else {
                    $nodes[] = $spreadNode;
                }

                $context->reportError(new Error(
                    self::cycleErrorMessage(
                        $spreadName,
                        Utils::map($cyclePath, function ($s) {
                            return $s->name->value;
                        })
                    ),
                    $nodes
                ));
            }
        }

        $this->spreadPathIndexByName[$fragmentName] = null;
    }
}
