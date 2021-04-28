<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Language\AST\Node;
use GraphQL\Language\VisitorOperation;
use GraphQL\Validator\SDLValidationContext;
use GraphQL\Validator\ValidationContext;

use function class_alias;

abstract class ValidationRule
{
    protected string $name;

    public function getName(): string
    {
        return $this->name === '' || $this->name === null
            ? static::class
            : $this->name;
    }

    /**
     * @return array<string, callable(Node): VisitorOperation|mixed|null>|array<string, array<string, callable(Node): VisitorOperation|mixed|null>>
     */
    public function __invoke(ValidationContext $context)
    {
        return $this->getVisitor($context);
    }

    /**
     * Returns structure suitable for GraphQL\Language\Visitor
     *
     * @see \GraphQL\Language\Visitor
     *
     * @return array<string, callable(Node): VisitorOperation|mixed|null>|array<string, array<string, callable(Node): VisitorOperation|mixed|null>>
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
     * @return array<string, callable(Node): VisitorOperation|mixed|null>|array<string, array<string, callable(Node): VisitorOperation|mixed|null>>
     */
    public function getSDLVisitor(SDLValidationContext $context)
    {
        return [];
    }
}

class_alias(ValidationRule::class, 'GraphQL\Validator\Rules\AbstractValidationRule');
