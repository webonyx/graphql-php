<?php declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Introspection;
use GraphQL\Utils\Utils;

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

    public const STANDARD_TYPE_NAMES = [
        self::INT,
        self::FLOAT,
        self::STRING,
        self::BOOLEAN,
        self::ID,
    ];

    public const BUILT_IN_TYPE_NAMES = [
        ...self::STANDARD_TYPE_NAMES,
        ...Introspection::TYPE_NAMES,
    ];

    /** @var array<string, ScalarType>|null */
    protected static ?array $standardTypes;

    /** @var array<string, Type&NamedType>|null */
    protected static ?array $builtInTypes;

    /**
     * Returns the registered or default standard Int type.
     *
     * @api
     */
    public static function int(): ScalarType
    {
        return static::$standardTypes[self::INT] ??= new IntType(); // @phpstan-ignore missingType.checkedException (static configuration is known to be correct)
    }

    /**
     * Returns the registered or default standard Float type.
     *
     * @api
     */
    public static function float(): ScalarType
    {
        return static::$standardTypes[self::FLOAT] ??= new FloatType(); // @phpstan-ignore missingType.checkedException (static configuration is known to be correct)
    }

    /**
     * Returns the registered or default standard String type.
     *
     * @api
     */
    public static function string(): ScalarType
    {
        return static::$standardTypes[self::STRING] ??= new StringType(); // @phpstan-ignore missingType.checkedException (static configuration is known to be correct)
    }

    /**
     * Returns the registered or default standard Boolean type.
     *
     * @api
     */
    public static function boolean(): ScalarType
    {
        return static::$standardTypes[self::BOOLEAN] ??= new BooleanType(); // @phpstan-ignore missingType.checkedException (static configuration is known to be correct)
    }

    /**
     * Returns the registered or default standard ID type.
     *
     * @api
     */
    public static function id(): ScalarType
    {
        return static::$standardTypes[self::ID] ??= new IDType(); // @phpstan-ignore missingType.checkedException (static configuration is known to be correct)
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
     * Returns all builtin in types including base scalar and introspection types.
     *
     * @return array<string, Type&NamedType>
     */
    public static function builtInTypes(): array
    {
        return self::$builtInTypes ??= array_merge(
            Introspection::getTypes(),
            self::getStandardTypes()
        );
    }

    /**
     * Returns all builtin scalar types.
     *
     * @return array<string, ScalarType>
     */
    public static function getStandardTypes(): array
    {
        return [
            self::INT => static::int(),
            self::FLOAT => static::float(),
            self::STRING => static::string(),
            self::BOOLEAN => static::boolean(),
            self::ID => static::id(),
        ];
    }

    /**
     * Allows partially or completely overriding the standard types.
     *
     * @param array<ScalarType> $types
     *
     * @throws InvariantViolation
     */
    public static function overrideStandardTypes(array $types): void
    {
        // Reset caches that might contain instances of standard types
        static::$builtInTypes = null;
        Introspection::resetCachedInstances();
        Directive::resetCachedInstances();

        foreach ($types as $type) {
            // @phpstan-ignore-next-line generic type is not enforced by PHP
            if (! $type instanceof ScalarType) {
                $typeClass = ScalarType::class;
                $notType = Utils::printSafe($type);
                throw new InvariantViolation("Expecting instance of {$typeClass}, got {$notType}");
            }

            if (! in_array($type->name, self::STANDARD_TYPE_NAMES, true)) {
                $standardTypeNames = implode(', ', self::STANDARD_TYPE_NAMES);
                $notStandardTypeName = Utils::printSafe($type->name);
                throw new InvariantViolation("Expecting one of the following names for a standard type: {$standardTypeNames}; got {$notStandardTypeName}");
            }

            static::$standardTypes[$type->name] = $type;
        }
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
