<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Language\AST\Node;
use GraphQL\Language\VisitorOperation;
use GraphQL\Validator\SDLValidationContext;
use GraphQL\Validator\ValidationContext;

/**
 * @phpstan-type VisitorArray array<string, callable(Node): VisitorOperation|mixed|null>|array<string, array<string, callable(Node): VisitorOperation|mixed|null>>
 */
abstract class ValidationRule
{
    protected string $name;

    public function getName(): string
    {
        return $this->name ?? static::class;
    }

    /**
     * @phpstan-return VisitorArray
     */
    public function __invoke(ValidationContext $context): array
    {
        return $this->getVisitor($context);
    }

    /**
     * Returns structure suitable for @see \GraphQL\Language\Visitor.
     *
     * @phpstan-return VisitorArray
     */
    public function getVisitor(ValidationContext $context): array
    {
        return [];
    }

    /**
     * Returns structure suitable for @see \GraphQL\Language\Visitor.
     *
     * @phpstan-return VisitorArray
     */
    public function getSDLVisitor(SDLValidationContext $context): array
    {
        return [];
    }
}
