<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Schema;
use function assert;
use function is_callable;

class NonNull extends Type implements WrappingType, OutputType, InputType
{
    /** @var callable|(NullableType&Type) */
    private $ofType;

    /**
     * NonNull constructor.
     * @param  callable|(NullableType&Type) $type
     */
    public function __construct($type)
    {
        assert($type instanceof NullableType || is_callable($type), new InvariantViolation('NonNull constructor expects an instance of NullableType, or a callable'));

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
