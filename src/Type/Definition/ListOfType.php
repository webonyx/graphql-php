<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

/**
 * Class ListOfType
 */
class ListOfType extends Type implements WrappingType, OutputType, InputType
{
    /** @var ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType */
    public $ofType;

    /**
     * @param callable|Type $type
     */
    public function __construct($type)
    {
        $this->ofType = Type::assertType($type);
    }

    /**
     * @return string
     */
    public function toString()
    {
        $type = $this->ofType;
        $str  = $type instanceof Type ? $type->toString() : (string) $type;

        return '[' . $str . ']';
    }

    /**
     * @param bool $recurse
     *
     * @return ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType
     */
    public function getWrappedType($recurse = false)
    {
        $type = $this->ofType;

        return $recurse && $type instanceof WrappingType ? $type->getWrappedType($recurse) : $type;
    }
}
