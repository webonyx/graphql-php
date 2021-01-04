<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Type\Schema;

class NonNull extends Type implements WrappingType, OutputType, InputType
{
    /** @var callable():(NullableType&Type)|(NullableType&Type) */
    private $ofType;

    /**
     * @param callable():(NullableType &Type)|(NullableType&Type) $type
     */
    public function __construct($type)
    {
        $this->ofType = $type;
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
