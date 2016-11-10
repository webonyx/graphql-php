<?php
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Argument;
use GraphQL\Language\AST\FragmentDefinition;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeType;
use GraphQL\Language\Visitor;
use GraphQL\Validator\ValidationContext;

class UniqueFragmentNames
{
    static function duplicateFragmentNameMessage($fragName)
    {
        return "There can only be one fragment named \"$fragName\".";
    }

    public $knownFragmentNames;

    public function __invoke(ValidationContext $context)
    {
        $this->knownFragmentNames = [];

        return [
            NodeType::OPERATION_DEFINITION => function () {
                return Visitor::skipNode();
            },
            NodeType::FRAGMENT_DEFINITION => function (FragmentDefinition $node) use ($context) {
                $fragmentName = $node->getName()->getValue();
                if (!empty($this->knownFragmentNames[$fragmentName])) {
                    $context->reportError(new Error(
                        self::duplicateFragmentNameMessage($fragmentName),
                        [ $this->knownFragmentNames[$fragmentName], $node->getName() ]
                    ));
                } else {
                    $this->knownFragmentNames[$fragmentName] = $node->getName();
                }
                return Visitor::skipNode();
            }
        ];
    }
}
