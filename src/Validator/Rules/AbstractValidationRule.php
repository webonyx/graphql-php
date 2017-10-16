<?php
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Validator\ValidationContext;

abstract class AbstractValidationRule
{
    protected $name;

    public function getName()
    {
        return $this->name ?: get_class($this);
    }

    public function __invoke(ValidationContext $context)
    {
        return $this->getVisitor($context);
    }

    /**
     * @param ValidationContext $context
     * @return Error[]
     */
    abstract public function getVisitor(ValidationContext $context);
}
