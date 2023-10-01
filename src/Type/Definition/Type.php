<?php declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Introspection;
use GraphQL\Type\Registry\DefaultStandardTypeRegistry;
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

    /**
     * @deprecated use the `typeRegistry` on the `Schema` instead
     *
     * @api
     *
     * @throws InvariantViolation
     */
    public static function int(): ScalarType
    {
        return DefaultStandardTypeRegistry::instance()->int();
    }

    /**
     * @deprecated use the `typeRegistry` on the `Schema` instead
     *
     * @api
     *
     * @throws InvariantViolation
     */
    public static function float(): ScalarType
    {
        return DefaultStandardTypeRegistry::instance()->float();
    }

    /**
     * @deprecated use the `typeRegistry` on the `Schema` instead
     *
     * @api
     *
     * @throws InvariantViolation
     */
    public static function string(): ScalarType
    {
        return DefaultStandardTypeRegistry::instance()->string();
    }

    /**
     * @deprecated use the `typeRegistry` on the `Schema` instead
     *
     * @api
     *
     * @throws InvariantViolation
     */
    public static function boolean(): ScalarType
    {
        return DefaultStandardTypeRegistry::instance()->boolean();
    }

    /**
     * @deprecated use the `typeRegistry` on the `Schema` instead
     *
     * @api
     *
     * @throws InvariantViolation
     */
    public static function id(): ScalarType
    {
        return DefaultStandardTypeRegistry::instance()->id();
    }

    /**
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
     * @param (NullableType&Type)|callable():(NullableType&Type) $type
     *
     * @api
     */
    public static function nonNull($type): NonNull
    {
        return new NonNull($type);
    }

    /**
     * Returns all builtin scalar types.
     *
     * @deprecated use the `typeRegistry` on the `Schema` instead
     *
     * @throws InvariantViolation
     *
     * @return array<string, ScalarType>
     */
    public static function getStandardTypes(): array
    {
        return DefaultStandardTypeRegistry::instance()->standardTypes();
    }

    /**
     * @deprecated use the `typeRegistry` on the `Schema` instead
     *
     * @param array<ScalarType> $types
     *
     * @throws InvariantViolation
     */
    public static function overrideStandardTypes(array $types): void
    {
        $standardTypes = DefaultStandardTypeRegistry::instance()->standardTypes();

        foreach ($types as $type) {
            // @phpstan-ignore-next-line generic type is not enforced by PHP
            if (! $type instanceof ScalarType) {
                $typeClass = ScalarType::class;
                $notType = Utils::printSafe($type);
                throw new InvariantViolation("Expecting instance of {$typeClass}, got {$notType}");
            }

            if (! in_array($type->name, self::STANDARD_TYPE_NAMES, true)) {
                $standardTypeNames = \implode(', ', self::STANDARD_TYPE_NAMES);
                $notStandardTypeName = Utils::printSafe($type->name);
                throw new InvariantViolation("Expecting one of the following names for a standard type: {$standardTypeNames}; got {$notStandardTypeName}");
            }

            $standardTypes[$type->name] = $type;
        }

        DefaultStandardTypeRegistry::register(new DefaultStandardTypeRegistry(
            $standardTypes[Type::INT],
            $standardTypes[Type::FLOAT],
            $standardTypes[Type::STRING],
            $standardTypes[Type::BOOLEAN],
            $standardTypes[Type::ID],
        ));
    }

    /**
     * @param mixed $type
     *
     * @api
     */
    public static function isInputType($type): bool
    {
        return self::getNamedType($type) instanceof InputType;
    }

    /**
     * @return (Type&NamedType)|null
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
     * @param mixed $type
     *
     * @api
     */
    public static function isOutputType($type): bool
    {
        return self::getNamedType($type) instanceof OutputType;
    }

    /**
     * @param mixed $type
     *
     * @api
     */
    public static function isLeafType($type): bool
    {
        return $type instanceof LeafType;
    }

    /**
     * @param mixed $type
     *
     * @api
     */
    public static function isCompositeType($type): bool
    {
        return $type instanceof CompositeType;
    }

    /**
     * @param mixed $type
     *
     * @api
     */
    public static function isAbstractType($type): bool
    {
        return $type instanceof AbstractType;
    }

    /**
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
