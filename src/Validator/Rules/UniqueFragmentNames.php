<?php
namespace GraphQL\Validator\Rules;

use GraphQL\Error;
use GraphQL\Language\AST\Argument;
use GraphQL\Language\AST\FragmentDefinition;
use GraphQL\Language\AST\Node;
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
            Node::OPERATION_DEFINITION => function () {
                return Visitor::skipNode();
            },
            Node::FRAGMENT_DEFINITION => function (FragmentDefinition $node) use ($context) {
                $fragmentName = $node->name->value;
                if (!empty($this->knownFragmentNames[$fragmentName])) {
                    $context->reportError(new Error(
                        self::duplicateFragmentNameMessage($fragmentName),
                        [ $this->knownFragmentNames[$fragmentName], $node->name ]
                    ));
                } else {
                    $this->knownFragmentNames[$fragmentName] = $node->name;
                }
                return Visitor::skipNode();
            }
        ];
    }
}
