<?php
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeType;
use GraphQL\Language\AST\ObjectField;
use GraphQL\Language\Visitor;
use GraphQL\Validator\ValidationContext;

class UniqueInputFieldNames
{
    static function duplicateInputFieldMessage($fieldName)
    {
      return "There can be only one input field named \"$fieldName\".";
    }

    public $knownNames;
    public $knownNameStack;

    public function __invoke(ValidationContext $context)
    {
        $this->knownNames = [];
        $this->knownNameStack = [];

        return [
            NodeType::OBJECT => [
                'enter' => function() {
                    $this->knownNameStack[] = $this->knownNames;
                    $this->knownNames = [];
                },
                'leave' => function() {
                    $this->knownNames = array_pop($this->knownNameStack);
                }
            ],
            NodeType::OBJECT_FIELD => function(ObjectField $node) use ($context) {
                $fieldName = $node->name->value;

                if (!empty($this->knownNames[$fieldName])) {
                    $context->reportError(new Error(
                        self::duplicateInputFieldMessage($fieldName),
                        [ $this->knownNames[$fieldName], $node->name ]
                    ));
                } else {
                    $this->knownNames[$fieldName] = $node->name;
                }
                return Visitor::skipNode();
            }
        ];
    }
}
