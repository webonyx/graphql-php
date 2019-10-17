<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

class ListOfType extends Type implements WrappingType, OutputType, NullableType, InputType
{
    /** @var Type */
    public $ofType;

    public function __construct(Type $type)
    {
        $this->ofType = is_callable($type) ? $type : Type::assertType($type);
    }

    public function toString() : string
    {
        return '[' . $this->ofType->toString() . ']';
    }

    public function __get($name)
    {
        switch ($name) {
            case "ofType":
                return Type::resolveLazyType($this->_ofType);
        }
    }

    /**
     * @param bool $recurse
     *
     * @return ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType
     */
    public function getWrappedType(bool $recurse = false) : Type
    {
        $type = $this->ofType;

        return $recurse && $type instanceof WrappingType
            ? $type->getWrappedType($recurse)
            : $type;
    }
}
