<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Language\AST\Node;
use GraphQL\Language\VisitorOperation;
use GraphQL\Validator\ValidationContext;

class CustomValidationRule extends ValidationRule
{
    /** @var callable */
    protected $visitorFn;

    /**
     * @param callable(ValidationContext): (array<string, callable(Node): VisitorOperation|mixed|null>|array<string, array<string, callable(Node): VisitorOperation|mixed|null>>) $visitorFn
     */
    public function __construct(string $name, callable $visitorFn)
    {
        $this->name      = $name;
        $this->visitorFn = $visitorFn;
    }

    public function getVisitor(ValidationContext $context)
    {
        return ($this->visitorFn)($context);
    }
}
