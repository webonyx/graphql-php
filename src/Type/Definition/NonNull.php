<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

class NonNull extends Type implements WrappingType, OutputType, InputType
{
    /** @var NullableType&Type */
    private $ofType;

    public function __construct(NullableType $type)
    {
        /** @var Type&NullableType $nullableType*/
        $nullableType = $type;
        $this->ofType = $nullableType;
    }

    public function toString() : string
    {
        return $this->getWrappedType()->toString() . '!';
    }

    public function getWrappedType(bool $recurse = false) : Type
    {
        $type = $this->ofType;

        return $recurse && $type instanceof WrappingType
            ? $type->getWrappedType($recurse)
            : $type;
    }
}
