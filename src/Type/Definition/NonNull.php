<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Schema;
use function is_callable;

class NonNull extends Type implements WrappingType, OutputType, InputType
{
    /** @var callable|(NullableType&Type) */
    private $ofType;

    /**
     * code sniffer doesn't understand this syntax. Pr with a fix here: waiting on https://github.com/squizlabs/PHP_CodeSniffer/pull/2919
     * phpcs:disable Squiz.Commenting.FunctionComment.SpacingAfterParamType
     * @param  (NullableType&Type)|callable $type
     */
    public function __construct($type)
    {
        /** @var Type&NullableType $nullableType*/
        $nullableType = $type;
        $this->ofType = $nullableType;
    }

    public function toString() : string
    {
        return $this->getWrappedType()->toString() . '!';
    }

    public function getOfType()
    {
        return Schema::resolveType($this->ofType);
    }

    public function getWrappedType(bool $recurse = false) : Type
    {
        $type = $this->getOfType();

        return $recurse && $type instanceof WrappingType
            ? $type->getWrappedType($recurse)
            : $type;
    }
}
