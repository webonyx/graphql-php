<?php
namespace GraphQL\Validator\Rules;

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
     * Returns structure suitable for GraphQL\Language\Visitor
     *
     * @see \GraphQL\Language\Visitor
     * @param ValidationContext $context
     * @return array
     */
    abstract public function getVisitor(ValidationContext $context);
}
