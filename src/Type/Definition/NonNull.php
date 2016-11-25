<?php
namespace GraphQL\Type\Definition;

use GraphQL\Utils;

/**
 * Class NonNull
 * @package GraphQL\Type\Definition
 */
class NonNull extends Type implements WrappingType, OutputType, InputType
{
    /**
     * @var callable|Type
     */
    private $ofType;

    /**
     * @param callable|Type $type
     * @throws \Exception
     */
    public function __construct($type)
    {
        Utils::invariant(
            $type instanceof Type || is_callable($type),
            'Expecting instance of GraphQL\Type\Definition\Type or callable returning instance of that class'
        );
        Utils::invariant(
            !($type instanceof NonNull),
            'Cannot nest NonNull inside NonNull'
        );
        $this->ofType = $type;
    }

    /**
     * @param bool $recurse
     * @return mixed
     * @throws \Exception
     */
    public function getWrappedType($recurse = false)
    {
        $type = Type::resolve($this->ofType);

        Utils::invariant(
            !($type instanceof NonNull),
            'Cannot nest NonNull inside NonNull'
        );

        return ($recurse && $type instanceof WrappingType) ? $type->getWrappedType($recurse) : $type;
    }

    /**
     * @return string
     */
    public function toString()
    {
        return $this->getWrappedType()->toString() . '!';
    }
}
