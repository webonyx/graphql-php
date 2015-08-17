<?php
/**
 * Created by PhpStorm.
 * User: Vladimir
 * Date: 11.07.2015
 * Time: 18:54
 */

namespace GraphQL\Validator\Rules;


use GraphQL\Error;
use GraphQL\Language\AST\FragmentDefinition;
use GraphQL\Language\AST\FragmentSpread;
use GraphQL\Language\AST\Node;
use GraphQL\Language\Visitor;
use GraphQL\Validator\ValidationContext;

class NoFragmentCycles
{
    static function cycleErrorMessage($fragName, array $spreadNames = [])
    {
        $via = !empty($spreadNames) ? ' via ' . implode(', ', $spreadNames) : '';
        return "Cannot spread fragment \"$fragName\" within itself$via.";
    }

    public function __invoke(ValidationContext $context)
    {
        // Gather all the fragment spreads ASTs for each fragment definition.
        // Importantly this does not include inline fragments.
        $definitions = $context->getDocument()->definitions;
        $spreadsInFragment = [];
        foreach ($definitions as $node) {
            if ($node instanceof FragmentDefinition) {
                $spreadsInFragment[$node->name->value] = $this->gatherSpreads($node);
            }
        }

        // Tracks spreads known to lead to cycles to ensure that cycles are not
        // redundantly reported.
        $knownToLeadToCycle = new \SplObjectStorage();

        return [
            Node::FRAGMENT_DEFINITION => function(FragmentDefinition $node) use ($spreadsInFragment, $knownToLeadToCycle) {
                $errors = [];
                $initialName = $node->name->value;

                // Array of AST nodes used to produce meaningful errors
                $spreadPath = [];

                $this->detectCycleRecursive($initialName, $spreadsInFragment, $knownToLeadToCycle, $initialName, $spreadPath, $errors);

                if (!empty($errors)) {
                    return $errors;
                }
            }
        ];
    }

    private function detectCycleRecursive($fragmentName, array $spreadsInFragment, \SplObjectStorage $knownToLeadToCycle, $initialName, array &$spreadPath, &$errors)
    {
        $spreadNodes = $spreadsInFragment[$fragmentName];

        for ($i = 0; $i < count($spreadNodes); ++$i) {
            $spreadNode = $spreadNodes[$i];
            if (isset($knownToLeadToCycle[$spreadNode])) {
                continue ;
            }
            if ($spreadNode->name->value === $initialName) {
                $cyclePath = array_merge($spreadPath, [$spreadNode]);
                foreach ($cyclePath as $spread) {
                    $knownToLeadToCycle[$spread] = true;
                }
                $errors[] = new Error(
                    self::cycleErrorMessage($initialName, array_map(function ($s) {
                        return $s->name->value;
                    }, $spreadPath)),
                    $cyclePath
                );
                continue;
            }

            foreach ($spreadPath as $spread) {
                if ($spread === $spreadNode) {
                    continue 2;
                }
            }

            $spreadPath[] = $spreadNode;
            $this->detectCycleRecursive($spreadNode->name->value, $spreadsInFragment, $knownToLeadToCycle, $initialName, $spreadPath, $errors);
            array_pop($spreadPath);
        }
    }


    private function gatherSpreads($node)
    {
        $spreadNodes = [];
        Visitor::visit($node, [
            Node::FRAGMENT_SPREAD => function(FragmentSpread $spread) use (&$spreadNodes) {
                $spreadNodes[] = $spread;
            }
        ]);
        return $spreadNodes;
    }
}
