<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Validator\ASTValidationContext;
use GraphQL\Validator\SDLValidationContext;
use GraphQL\Validator\ValidationContext;
use function sprintf;

class UniqueArgumentNames extends ValidationRule
{
    /** @var NameNode[] */
    public $knownArgNames;

    public function getSDLVisitor(SDLValidationContext $context)
    {
        return $this->getASTVisitor($context);
    }

    public function getVisitor(ValidationContext $context)
    {
        return $this->getASTVisitor($context);
    }

    public function getASTVisitor(ASTValidationContext $context)
    {
        $this->knownArgNames = [];

        return [
            NodeKind::FIELD     => function () : void {
                $this->knownArgNames = [];
            },
            NodeKind::DIRECTIVE => function () : void {
                $this->knownArgNames = [];
            },
            NodeKind::ARGUMENT  => function (ArgumentNode $node) use ($context) : VisitorOperation {
                $argName = $node->name->value;
                if ($this->knownArgNames[$argName] ?? false) {
                    $context->reportError(new Error(
                        self::duplicateArgMessage($argName),
                        [$this->knownArgNames[$argName], $node->name]
                    ));
                } else {
                    $this->knownArgNames[$argName] = $node->name;
                }

                return Visitor::skipNode();
            },
        ];
    }

    public static function duplicateArgMessage($argName)
    {
        return sprintf('There can be only one argument named "%s".', $argName);
    }
}
