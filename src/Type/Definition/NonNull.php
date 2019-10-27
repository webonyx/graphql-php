<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Utils\Utils;

class NonNull extends Type implements WrappingType, OutputType, InputType
{
    /** @var NullableType */
    private $ofType;

    public function __construct(NullableType $type)
    {
        $this->ofType = $type;
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
