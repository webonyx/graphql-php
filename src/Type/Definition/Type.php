<?php declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Type\BuiltInDefinitions;
use GraphQL\Type\Introspection;

/**
 * Registry of standard GraphQL types and base class for all other types.
 */
abstract class Type implements \JsonSerializable
{
    public const INT = 'Int';
    public const FLOAT = 'Float';
    public const STRING = 'String';
    public const BOOLEAN = 'Boolean';
    public const ID = 'ID';

    /**
     * @deprecated use {@see BuiltInDefinitions::SCALAR_TYPE_NAMES} or {@see BuiltInDefinitions::isBuiltInScalarName()}
     *
     * @var array<string>
     */
    public const STANDARD_TYPE_NAMES = [
        self::INT,
        self::FLOAT,
        self::STRING,
        self::BOOLEAN,
        self::ID,
    ];

    /**
     * @deprecated use {@see BuiltInDefinitions::BUILT_IN_TYPE_NAMES} or {@see BuiltInDefinitions::isBuiltInTypeName()}
     *
     * @var array<string>
     */
    public const BUILT_IN_TYPE_NAMES = [
        ...self::STANDARD_TYPE_NAMES,
        ...Introspection::TYPE_NAMES,
    ];

    /**
     * Returns the registered or default standard Int type.
     *
     * @api
     */
    public static function int(): ScalarType
    {
        return BuiltInDefinitions::standard()->int();
    }

    /**
     * Returns the registered or default standard Float type.
     *
     * @api
     */
    public static function float(): ScalarType
    {
        return BuiltInDefinitions::standard()->float();
    }

    /**
     * Returns the registered or default standard String type.
     *
     * @api
     */
    public static function string(): ScalarType
    {
        return BuiltInDefinitions::standard()->string();
    }

    /**
     * Returns the registered or default standard Boolean type.
     *
     * @api
     */
    public static function boolean(): ScalarType
    {
        return BuiltInDefinitions::standard()->boolean();
    }

    /**
     * Returns the registered or default standard ID type.
     *
     * @api
     */
    public static function id(): ScalarType
    {
        return BuiltInDefinitions::standard()->id();
    }

    /**
     * Wraps the given type in a list type.
     *
     * @template T of Type
     *
     * @param T|callable():T $type
     *
     * @return ListOfType<T>
     *
     * @api
     */
    public static function listOf($type): ListOfType
    {
        return new ListOfType($type);
    }

    /**
     * Wraps the given type in a non-null type.
     *
     * @param NonNull|(NullableType&Type)|callable():(NullableType&Type) $type
     *
     * @api
     */
    public static function nonNull($type): NonNull
    {
        if ($type instanceof NonNull) {
            return $type;
        }

        return new NonNull($type);
    }

    /**
     * Determines if the given type is an input type.
     *
     * @param mixed $type
     *
     * @api
     */
    public static function isInputType($type): bool
    {
        return self::getNamedType($type) instanceof InputType;
    }

    /**
     * Returns the underlying named type of the given type.
     *
     * @return (Type&NamedType)|null
     *
     * @phpstan-return ($type is null ? null : Type&NamedType)
     *
     * @api
     */
    public static function getNamedType(?Type $type): ?Type
    {
        if ($type instanceof WrappingType) {
            return $type->getInnermostType();
        }

        assert($type === null || $type instanceof NamedType, 'only other option');

        return $type;
    }

    /**
     * Determines if the given type is an output type.
     *
     * @param mixed $type
     *
     * @api
     */
    public static function isOutputType($type): bool
    {
        return self::getNamedType($type) instanceof OutputType;
    }

    /**
     * Determines if the given type is a leaf type.
     *
     * @param mixed $type
     *
     * @api
     */
    public static function isLeafType($type): bool
    {
        return $type instanceof LeafType;
    }

    /**
     * Determines if the given type is a composite type.
     *
     * @param mixed $type
     *
     * @api
     */
    public static function isCompositeType($type): bool
    {
        return $type instanceof CompositeType;
    }

    /**
     * Determines if the given type is an abstract type.
     *
     * @param mixed $type
     *
     * @api
     */
    public static function isAbstractType($type): bool
    {
        return $type instanceof AbstractType;
    }

    /**
     * Unwraps a potentially non-null type to return the underlying nullable type.
     *
     * @return Type&NullableType
     *
     * @api
     */
    public static function getNullableType(Type $type): Type
    {
        if ($type instanceof NonNull) {
            return $type->getWrappedType();
        }

        assert($type instanceof NullableType, 'only other option');

        return $type;
    }

    /**
     * Returns all built-in types (scalars + introspection types).
     *
     * @deprecated use {@see BuiltInDefinitions::standard()}->types()
     *
     * @return array<string, Type&NamedType>
     */
    public static function builtInTypes(): array
    {
        return BuiltInDefinitions::standard()->types();
    }

    /**
     * Returns the standard scalar types (Int, Float, String, Boolean, ID).
     *
     * @deprecated use {@see BuiltInDefinitions::standard()}->scalarTypes()
     *
     * @return array<string, ScalarType>
     */
    public static function getStandardTypes(): array
    {
        return BuiltInDefinitions::standard()->scalarTypes();
    }

    /**
     * Replaces standard scalar types with the given types.
     *
     * @deprecated use {@see BuiltInDefinitions::overrideScalarTypes()}
     *
     * @param array<ScalarType> $types
     */
    public static function overrideStandardTypes(array $types): void
    {
        BuiltInDefinitions::overrideScalarTypes($types);
    }

    abstract public function toString(): string;

    public function __toString(): string
    {
        return $this->toString();
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize(): string
    {
        return $this->toString();
    }
}
