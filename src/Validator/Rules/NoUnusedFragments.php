<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error;
use GraphQL\Language\AST\FragmentDefinition;
use GraphQL\Language\AST\FragmentSpread;
use GraphQL\Language\AST\Node;
use GraphQL\Validator\Messages;
use GraphQL\Validator\ValidationContext;

class NoUnusedFragments
{
    static function unusedFragMessage($fragName)
    {
        return "Fragment \"$fragName\" is never used.";
    }

    public function __invoke(ValidationContext $context)
    {
        $fragmentDefs = [];
        $spreadsWithinOperation = [];
        $fragAdjacencies = new \stdClass();
        $spreadNames = new \stdClass();

        return [
            Node::OPERATION_DEFINITION => function() use (&$spreadNames, &$spreadsWithinOperation) {
                $spreadNames = new \stdClass();
                $spreadsWithinOperation[] = $spreadNames;
            },
            Node::FRAGMENT_DEFINITION => function(FragmentDefinition $def) use (&$fragmentDefs, &$spreadNames, &$fragAdjacencies) {
                $fragmentDefs[] = $def;
                $spreadNames = new \stdClass();
                $fragAdjacencies->{$def->name->value} = $spreadNames;
            },
            Node::FRAGMENT_SPREAD => function(FragmentSpread $spread) use (&$spreadNames) {
                $spreadNames->{$spread->name->value} = true;
            },
            Node::DOCUMENT => [
                'leave' => function() use (&$fragAdjacencies, &$spreadsWithinOperation, &$fragmentDefs) {
                    $fragmentNameUsed = [];

                    foreach ($spreadsWithinOperation as $spreads) {
                        $this->reduceSpreadFragments($spreads, $fragmentNameUsed, $fragAdjacencies);
                    }

                    $errors = [];
                    foreach ($fragmentDefs as $def) {
                        if (empty($fragmentNameUsed[$def->name->value])) {
                            $errors[] = new Error(
                                self::unusedFragMessage($def->name->value),
                                [$def]
                            );
                        }
                    }
                    return !empty($errors) ? $errors : null;
                }
            ]
        ];
    }

    private function reduceSpreadFragments($spreads, &$fragmentNameUsed, &$fragAdjacencies)
    {
        foreach ($spreads as $fragName => $fragment) {
            if (empty($fragmentNameUsed[$fragName])) {
                $fragmentNameUsed[$fragName] = true;

                if (isset($fragAdjacencies->{$fragName})) {
                    $this->reduceSpreadFragments(
                        $fragAdjacencies->{$fragName},
                        $fragmentNameUsed,
                        $fragAdjacencies
                    );
                }
            }
        }
    }
}
