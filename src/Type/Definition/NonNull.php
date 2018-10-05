<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use Exception;
use GraphQL\Error\InvariantViolation;
use GraphQL\Utils\Utils;

/**
 * Class NonNull
 */
class NonNull extends Type implements WrappingType, OutputType, InputType
{
    /** @var ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType|ListOfType */
    private $ofType;

    /**
     * @param callable|Type $type
     *
     * @throws Exception
     */
    public function __construct($type)
    {
        $this->ofType = self::assertNullableType($type);
    }

    /**
     * @param mixed $type
     *
     * @return ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType|ListOfType
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

    /**
     * @return string
     */
    public function toString()
    {
        return $this->getWrappedType()->toString() . '!';
    }

    /**
     * @param bool $recurse
     *
     * @return ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType|ListOfType
     *
     * @throws InvariantViolation
     */
    public function getWrappedType($recurse = false)
    {
        $type = $this->ofType;

        return $recurse && $type instanceof WrappingType ? $type->getWrappedType($recurse) : $type;
    }
}
