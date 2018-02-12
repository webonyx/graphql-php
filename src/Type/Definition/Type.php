<?php
namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\TypeDefinitionNode;

/**
 * Registry of standard GraphQL types
 * and a base class for all other types.
 *
 * @package GraphQL\Type\Definition
 */
abstract class Type implements \JsonSerializable
{
    const STRING = 'String';
    const INT = 'Int';
    const BOOLEAN = 'Boolean';
    const FLOAT = 'Float';
    const ID = 'ID';

    /**
     * @var array
     */
    private static $internalTypes;

    /**
     * @api
     * @return IDType
     */
    public static function id()
    {
        return self::getInternalType(self::ID);
    }

    /**
     * @api
     * @return StringType
     */
    public static function string()
    {
        return self::getInternalType(self::STRING);
    }

    /**
     * @api
     * @return BooleanType
     */
    public static function boolean()
    {
        return self::getInternalType(self::BOOLEAN);
    }

    /**
     * @api
     * @return IntType
     */
    public static function int()
    {
        return self::getInternalType(self::INT);
    }

    /**
     * @api
     * @return FloatType
     */
    public static function float()
    {
        return self::getInternalType(self::FLOAT);
    }

    /**
     * @api
     * @param ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType|ListOfType|NonNull $wrappedType
     * @return ListOfType
     */
    public static function listOf($wrappedType)
    {
        return new ListOfType($wrappedType);
    }

    /**
     * @api
     * @param ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType|ListOfType $wrappedType
     * @return NonNull
     */
    public static function nonNull($wrappedType)
    {
        return new NonNull($wrappedType);
    }

    /**
     * @param $name
     * @return array|IDType|StringType|FloatType|IntType|BooleanType
     */
    private static function getInternalType($name = null)
    {
        if (null === self::$internalTypes) {
            self::$internalTypes = [
                self::ID => new IDType(),
                self::STRING => new StringType(),
                self::FLOAT => new FloatType(),
                self::INT => new IntType(),
                self::BOOLEAN => new BooleanType()
            ];
        }
        return $name ? self::$internalTypes[$name] : self::$internalTypes;
    }

    /**
     * @return Type[]
     */
    public static function getInternalTypes()
    {
        return self::getInternalType();
    }

    /**
     * @api
     * @param Type $type
     * @return bool
     */
    public static function isInputType($type)
    {
        $nakedType = self::getNamedType($type);
        return $nakedType instanceof InputType;
    }

    /**
     * @api
     * @param Type $type
     * @return bool
     */
    public static function isOutputType($type)
    {
        $nakedType = self::getNamedType($type);
        return $nakedType instanceof OutputType;
    }

    public static function isNamedType($type)
    {
        $nakedType = self::getNamedType($type);
        return $nakedType instanceof OutputType || $nakedType instanceof InputType;
    }
    
    /**
     * @api
     * @param $type
     * @return bool
     */
    public static function isLeafType($type)
    {
        return $type instanceof LeafType;
    }

    /**
     * @api
     * @param Type $type
     * @return bool
     */
    public static function isCompositeType($type)
    {
        return $type instanceof CompositeType;
    }

    /**
     * @api
     * @param Type $type
     * @return bool
     */
    public static function isAbstractType($type)
    {
        return $type instanceof AbstractType;
    }

    /**
     * @api
     * @param Type $type
     * @return ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType|ListOfType
     */
    public static function getNullableType($type)
    {
        return $type instanceof NonNull ? $type->getWrappedType() : $type;
    }

    /**
     * @api
     * @param Type $type
     * @return ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType
     */
    public static function getNamedType($type)
    {
        if (null === $type) {
            return null;
        }
        while ($type instanceof WrappingType) {
            $type = $type->getWrappedType();
        }
        return $type;
    }

    /**
     * @var string
     */
    public $name;

    /**
     * @var string|null
     */
    public $description;

    /**
     * @var TypeDefinitionNode|null
     */
    public $astNode;

    /**
     * @var array
     */
    public $config;

    /**
     * @return null|string
     */
    protected function tryInferName()
    {
        if ($this->name) {
            return $this->name;
        }

        // If class is extended - infer name from className
        // QueryType -> Type
        // SomeOtherType -> SomeOther
        $tmp = new \ReflectionClass($this);
        $name = $tmp->getShortName();

        if ($tmp->getNamespaceName() !== __NAMESPACE__) {
            return preg_replace('~Type$~', '', $name);
        }
        return null;
    }

    /**
     * @throws InvariantViolation
     */
    public function assertValid()
    {
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
    public function jsonSerialize()
    {
        return $this->toString();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->toString();
        } catch (\Exception $e) {
            echo $e;
        } catch (\Throwable $e) {
            echo $e;
        }
    }
}
