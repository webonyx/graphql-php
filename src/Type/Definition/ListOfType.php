<?php
namespace GraphQL\Type\Definition;


use GraphQL\Utils;

class ListOfType extends Type implements WrappingType, OutputType, InputType
{
    /**
     * @var callable|Type
     */
    private $ofType;

    /**
     * @param callable|Type $type
     */
    public function __construct($type)
    {
        Utils::invariant(
            $type instanceof Type || is_callable($type),
            'Expecting instance of GraphQL\Type\Definition\Type or callable returning instance of that class'
        );

        $this->ofType = $type;
    }

    /**
     * @return string
     */
    public function toString()
    {
        $str = $this->ofType instanceof Type ? $this->ofType->toString() : (string) $this->ofType;
        return '[' . $str . ']';
    }

    /**
     * @param bool $recurse
     * @return mixed
     */
    public function getWrappedType($recurse = false)
    {
        $type = Type::resolve($this->ofType);
        return ($recurse && $type instanceof WrappingType) ? $type->getWrappedType($recurse) : $type;
    }
}
