<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Language\Visitor;
use GraphQL\Validator\QueryValidationContext;
use GraphQL\Validator\SDLValidationContext;

/**
 * @phpstan-import-type VisitorArray from Visitor
 */
abstract class ValidationRule
{
    protected string $name;

    public function getName(): string
    {
        return $this->name ?? static::class;
    }

    /**
     * Returns structure suitable for @see \GraphQL\Language\Visitor.
     *
     * @phpstan-return VisitorArray
     */
    public function getVisitor(QueryValidationContext $context): array
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
