<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Type\Schema;

class NonNull extends Type implements WrappingType, OutputType, InputType
{
    /** @var (NullableType&Type)|callable():(NullableType&Type) */
    private $ofType;

    /**
     * code sniffer doesn't understand this syntax. Pr with a fix here: waiting on https://github.com/squizlabs/PHP_CodeSniffer/pull/2919
     * phpcs:disable Squiz.Commenting.FunctionComment.SpacingAfterParamType
     * @param (NullableType&Type)|callable():(NullableType&Type) $type
     */
    public function __construct($type)
    {
        $this->ofType = $type;
    }

    public function toString(): string
    {
        return $this->getWrappedType()->toString() . '!';
    }

    /**
     * @return NullableType&Type
     */
    public function getOfType(): Type
    {
        // @phpstan-ignore-next-line generics in Schema::resolveType() are not recognized correctly
        return Schema::resolveType($this->ofType);
    }

    /**
     * @return NullableType&Type
     */
    public function getWrappedType(bool $recurse = false): Type
    {
        $type = $this->getOfType();

        // @phpstan-ignore-next-line might return another type intermediary, but will always match the return type when done with recursion
        return $recurse && $type instanceof WrappingType
            ? $type->getWrappedType($recurse)
            : $type;
    }
}
