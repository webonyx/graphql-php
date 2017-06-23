<?php
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Visitor;
use GraphQL\Validator\ValidationContext;

class UniqueFragmentNames
{
    public static function duplicateFragmentNameMessage($fragName)
    {
        return "There can only be one fragment named \"$fragName\".";
    }

    public $knownFragmentNames;

    public function __invoke(ValidationContext $context)
    {
        $this->knownFragmentNames = [];

        return [
            NodeKind::OPERATION_DEFINITION => function () {
                return Visitor::skipNode();
            },
            NodeKind::FRAGMENT_DEFINITION => function (FragmentDefinitionNode $node) use ($context) {
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
