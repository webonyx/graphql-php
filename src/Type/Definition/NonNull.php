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

    /**
     * @param mixed $type
     *
     * @return NullableType
     */
    public static function assertNullableType($type)
    {
        Utils::invariant(
            Type::isType($type) && ! $type instanceof self,
            'Expected ' . Utils::printSafe($type) . ' to be a GraphQL nullable type.'
        );

        return $type;
    }

    /**
     * @param mixed $type
     *
     * @return self
     */
    public static function assertNullType($type)
    {
        Utils::invariant(
            $type instanceof self,
            'Expected ' . Utils::printSafe($type) . ' to be a GraphQL Non-Null type.'
        );

        return $type;
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
