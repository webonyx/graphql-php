<?php
namespace GraphQL\Validator\Rules;

use GraphQL\Error;
use GraphQL\Language\AST\Argument;
use GraphQL\Language\AST\Node;
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
            Node::FIELD => function () {
                $this->knownArgNames = [];;
            },
            Node::DIRECTIVE => function () {
                $this->knownArgNames = [];
            },
            Node::ARGUMENT => function (Argument $node) use ($context) {
                $argName = $node->name->value;
                if (!empty($this->knownArgNames[$argName])) {
                    $context->reportError(new Error(
                        self::duplicateArgMessage($argName),
                        [$this->knownArgNames[$argName], $node->name]
                    ));
                } else {
                    $this->knownArgNames[$argName] = $node->name;
                }
                return Visitor::skipNode();
            }
        ];
    }
}
