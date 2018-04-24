<?php
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Validator\ValidationContext;

class CustomValidationRule extends AbstractValidationRule
{
    private $visitorFn;

    public function __construct($name, callable $visitorFn)
    {
        $this->name = $name;
        $this->visitorFn = $visitorFn;
    }

    /**
     * @param ValidationContext $context
     * @return Error[]
     */
    public function getVisitor(ValidationContext $context)
    {
        $fn = $this->visitorFn;
        return $fn($context);
    }
}
