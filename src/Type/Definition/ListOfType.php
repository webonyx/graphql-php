<?php
namespace GraphQL\Type\Definition;

use GraphQL\Utils;

/**
 * Class ListOfType
 * @package GraphQL\Type\Definition
 */
class ListOfType extends Type implements WrappingType, OutputType, InputType
{
    /**
     * @var callable|Type
     */
    public $ofType;

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
        $type = Type::resolve($this->ofType);
        $str = $type instanceof Type ? $type->toString() : (string) $type;
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
