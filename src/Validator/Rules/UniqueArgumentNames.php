<?php
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Argument;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeType;
use GraphQL\Language\Visitor;
use GraphQL\Validator\ValidationContext;

class UniqueArgumentNames
{
    static function duplicateArgMessage($argName)
    {
        return "There can be only one argument named \"$argName\".";
    }

    public $knownArgNames;

    public function __invoke(ValidationContext $context)
    {
        $this->knownArgNames = [];

        return [
            NodeType::FIELD => function () {
                $this->knownArgNames = [];;
            },
            NodeType::DIRECTIVE => function () {
                $this->knownArgNames = [];
            },
            NodeType::ARGUMENT => function (Argument $node) use ($context) {
                $argName = $node->getName()->getValue();
                if (!empty($this->knownArgNames[$argName])) {
                    $context->reportError(new Error(
                        self::duplicateArgMessage($argName),
                        [$this->knownArgNames[$argName], $node->getName()]
                    ));
                } else {
                    $this->knownArgNames[$argName] = $node->getName();
                }
                return Visitor::skipNode();
            }
        ];
    }
}
