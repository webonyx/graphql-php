<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use Exception;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionNode;
use GraphQL\Type\Introspection;
use GraphQL\Utils\Utils;
use JsonSerializable;
use ReflectionClass;
use Throwable;
use function array_keys;
use function array_merge;
use function in_array;
use function preg_replace;

/**
 * Registry of standard GraphQL types
 * and a base class for all other types.
 */
abstract class Type implements JsonSerializable
{
    public const STRING  = 'String';
    public const INT     = 'Int';
    public const BOOLEAN = 'Boolean';
    public const FLOAT   = 'Float';
    public const ID      = 'ID';

    /** @var Type[] */
    private static $internalTypes;

    /** @var Type[] */
    private static $builtInTypes;

    /** @var string */
    public $name;

    /** @var string|null */
    public $description;

    /** @var TypeDefinitionNode|null */
    public $astNode;

    /** @var mixed[] */
    public $config;

    /** @var TypeExtensionNode[] */
    public $extensionASTNodes;

    /**
     * @return IDType
     *
     * @api
     */
    public static function id()
    {
        return self::getInternalType(self::ID);
    }

    /**
     * @param string $name
     *
     * @return (IDType|StringType|FloatType|IntType|BooleanType)[]|IDType|StringType|FloatType|IntType|BooleanType
     */
    private static function getInternalType($name = null)
    {
        if (self::$internalTypes === null) {
            self::$internalTypes = [
                self::ID      => new IDType(),
                self::STRING  => new StringType(),
                self::FLOAT   => new FloatType(),
                self::INT     => new IntType(),
                self::BOOLEAN => new BooleanType(),
            ];
        }

        return $name ? self::$internalTypes[$name] : self::$internalTypes;
    }

    /**
     * @return StringType
     *
     * @api
     */
    public static function string()
    {
        return self::getInternalType(self::STRING);
    }

    /**
     * @return BooleanType
     *
     * @api
     */
    public static function boolean()
    {
        return self::getInternalType(self::BOOLEAN);
    }

    /**
     * @return IntType
     *
     * @api
     */
    public static function int()
    {
        return self::getInternalType(self::INT);
    }

    /**
     * @return FloatType
     *
     * @api
     */
    public static function float()
    {
        return self::getInternalType(self::FLOAT);
    }

    /**
     * @param Type|ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType|ListOfType|NonNull $wrappedType
     *
     * @return ListOfType
     *
     * @api
     */
    public static function listOf($wrappedType)
    {
        return new ListOfType($wrappedType);
    }

    /**
     * @param ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType|ListOfType $wrappedType
     *
     * @return NonNull
     *
     * @api
     */
    public static function nonNull($wrappedType)
    {
        return new NonNull($wrappedType);
    }

    /**
     * Checks if the type is a builtin type
     *
     * @return bool
     */
    public static function isBuiltInType(Type $type)
    {
        return in_array($type->name, array_keys(self::getAllBuiltInTypes()));
    }

    /**
     * Returns all builtin in types including base scalar and
     * introspection types
     *
     * @return Type[]
     */
    public static function getAllBuiltInTypes()
    {
        if (self::$builtInTypes === null) {
            self::$builtInTypes = array_merge(
                Introspection::getTypes(),
                self::getInternalTypes()
            );
        }

        return self::$builtInTypes;
    }

    /**
     * Returns all builtin scalar types
     *
     * @return Type[]
     */
    public static function getInternalTypes()
    {
        return self::getInternalType();
    }

    /**
     * @param Type $type
     *
     * @return bool
     *
     * @api
     */
    public static function isInputType($type)
    {
        return $type instanceof InputType &&
            (
                ! $type instanceof WrappingType ||
                self::getNamedType($type) instanceof InputType
            );
    }

    /**
     * @param Type $type
     *
     * @return ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType
     *
     * @api
     */
    public static function getNamedType($type)
    {
        if ($type === null) {
            return null;
        }
        while ($type instanceof WrappingType) {
            $type = $type->getWrappedType();
        }

        return $type;
    }

    /**
     * @param Type $type
     *
     * @return bool
     *
     * @api
     */
    public static function isOutputType($type)
    {
        return $type instanceof OutputType &&
            (
                ! $type instanceof WrappingType ||
                self::getNamedType($type) instanceof OutputType
            );
    }

    /**
     * @param Type $type
     *
     * @return bool
     *
     * @api
     */
    public static function isLeafType($type)
    {
        return $type instanceof LeafType;
    }

    /**
     * @param Type $type
     *
     * @return bool
     *
     * @api
     */
    public static function isCompositeType($type)
    {
        return $type instanceof CompositeType;
    }

    /**
     * @param Type $type
     *
     * @return bool
     *
     * @api
     */
    public static function isAbstractType($type)
    {
        return $type instanceof AbstractType;
    }

    /**
     * @param mixed $type
     *
     * @return mixed
     */
    public static function assertType($type)
    {
        Utils::invariant(
            self::isType($type),
            'Expected ' . Utils::printSafe($type) . ' to be a GraphQL type.'
        );

        return $type;
    }

    /**
     * @param Type $type
     *
     * @return bool
     *
     * @api
     */
    public static function isType($type)
    {
        return $type instanceof Type;
    }

    /**
     * @param Type $type
     *
     * @return ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType|ListOfType
     *
     * @api
     */
    public static function getNullableType($type)
    {
        return $type instanceof NonNull ? $type->getWrappedType() : $type;
    }

    /**
     * @throws InvariantViolation
     */
    public function assertValid()
    {
        Utils::assertValidName($this->name);
    }

    /**
     * @return string
     */
    public function jsonSerialize()
    {
        return $this->toString();
    }

    /**
     * @return string
     */
    public function toString()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->toString();
        } catch (Exception $e) {
            echo $e;
        } catch (Throwable $e) {
            echo $e;
        }
    }

    /**
     * @return string|null
     */
    protected function tryInferName()
    {
        if ($this->name) {
            return $this->name;
        }

        // If class is extended - infer name from className
        // QueryType -> Type
        // SomeOtherType -> SomeOther
        $tmp  = new ReflectionClass($this);
        $name = $tmp->getShortName();

        if ($tmp->getNamespaceName() !== __NAMESPACE__) {
            return preg_replace('~Type$~', '', $name);
        }

        return null;
    }
}
