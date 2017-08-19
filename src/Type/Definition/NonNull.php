<?php
namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Utils\Utils;

/**
 * Class NonNull
 * @package GraphQL\Type\Definition
 */
class NonNull extends Type implements WrappingType, OutputType, InputType
{
    /**
     * @var ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType
     */
    private $ofType;

    /**
     * @param callable|Type $type
     * @throws \Exception
     */
    public function __construct($type)
    {
        if (!$type instanceof Type && !is_callable($type)) {
            throw new InvariantViolation(
                'Can only create NonNull of a Nullable GraphQLType but got: ' . Utils::printSafe($type)
            );
        }
        if ($type instanceof NonNull) {
            throw new InvariantViolation(
                'Can only create NonNull of a Nullable GraphQLType but got: ' . Utils::printSafe($type)
            );
        }
        Utils::invariant(
            !($type instanceof NonNull),
            'Cannot nest NonNull inside NonNull'
        );
        $this->ofType = $type;
    }

    /**
     * @param bool $recurse
     * @return ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType
     * @throws InvariantViolation
     */
    public function getWrappedType($recurse = false)
    {
        $type = $this->ofType;

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
