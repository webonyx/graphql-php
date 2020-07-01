<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Validator\SDLValidationContext;
use GraphQL\Validator\ValidationContext;
use function class_alias;

abstract class ValidationRule
{
    /** @var string */
    protected $name;

    public function getName()
    {
        return $this->name === '' || $this->name === null  ? static::class : $this->name;
    }

    public function __invoke(ValidationContext $context)
    {
        return $this->getVisitor($context);
    }

    /**
     * Returns structure suitable for GraphQL\Language\Visitor
     *
     * @see \GraphQL\Language\Visitor
     *
     * @return mixed[]
     */
    public function getVisitor(ValidationContext $context)
    {
        return [];
    }

    /**
     * Returns structure suitable for GraphQL\Language\Visitor
     *
     * @see \GraphQL\Language\Visitor
     *
     * @return mixed[]
     */
    public function getSDLVisitor(SDLValidationContext $context)
    {
        return [];
    }
}

class_alias(ValidationRule::class, 'GraphQL\Validator\Rules\AbstractValidationRule');
