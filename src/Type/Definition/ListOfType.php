<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Type\Schema;

use function is_callable;

/**
 * @template OfType of Type
 */
class ListOfType extends Type implements WrappingType, OutputType, NullableType, InputType
{
    /**
     * @var Type|callable
     * @phpstan-var OfType|callable(): OfType
     */
    public $ofType;

    /**
     * @param Type|callable $type
     * @phpstan-param OfType|callable(): OfType $type
     */
    public function __construct($type)
    {
        $this->ofType = is_callable($type)
            ? $type
            : Type::assertType($type);
    }

    public function toString(): string
    {
        return '[' . $this->getOfType()->toString() . ']';
    }

    /**
     * @phpstan-return OfType
     */
    public function getOfType(): Type
    {
        return Schema::resolveType($this->ofType);
    }

    public function getWrappedType(bool $recurse = false): Type
    {
        $type = $this->getOfType();

        return $recurse && $type instanceof WrappingType
            ? $type->getWrappedType($recurse)
            : $type;
    }
}
