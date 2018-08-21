<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Validator\ValidationContext;
use function class_alias;
use function get_class;

abstract class ValidationRule
{
    /** @var string */
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
     * @return mixed[]
     */
    abstract public function getVisitor(ValidationContext $context);
}

class_alias(ValidationRule::class, 'GraphQL\Validator\Rules\AbstractValidationRule');
