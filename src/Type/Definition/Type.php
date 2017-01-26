<?php
namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Utils;

/*
export type GraphQLType =
GraphQLScalarType |
GraphQLObjectType |
GraphQLInterfaceType |
GraphQLUnionType |
GraphQLEnumType |
GraphQLInputObjectType |
GraphQLList |
GraphQLNonNull;
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
     * @return IDType
     */
    public static function id()
    {
        return self::getInternalType(self::ID);
    }

    /**
     * @return StringType
     */
    public static function string()
    {
        return self::getInternalType(self::STRING);
    }

    /**
     * @return BooleanType
     */
    public static function boolean()
    {
        return self::getInternalType(self::BOOLEAN);
    }

    /**
     * @return IntType
     */
    public static function int()
    {
        return self::getInternalType(self::INT);
    }

    /**
     * @return FloatType
     */
    public static function float()
    {
        return self::getInternalType(self::FLOAT);
    }

    /**
     * @param $wrappedType
     * @return ListOfType
     */
    public static function listOf($wrappedType)
    {
        return new ListOfType($wrappedType);
    }

    /**
     * @param $wrappedType
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
     * @param $type
     * @return bool
     */
    public static function isInputType($type)
    {
        $nakedType = self::getNamedType($type);
        return $nakedType instanceof InputType;
    }

    /**
     * @param $type
     * @return bool
     */
    public static function isOutputType($type)
    {
        $nakedType = self::getNamedType($type);
        return $nakedType instanceof OutputType;
    }

    /**
     * @param $type
     * @return bool
     */
    public static function isLeafType($type)
    {
        $nakedType = self::getNamedType($type);
        return $nakedType instanceof LeafType;
    }

    /**
     * @param $type
     * @return bool
     */
    public static function isCompositeType($type)
    {
        return $type instanceof CompositeType;
    }

    /**
     * @param $type
     * @return bool
     */
    public static function isAbstractType($type)
    {
        return $type instanceof AbstractType;
    }

    /**
     * @param $type
     * @return Type
     */
    public static function getNullableType($type)
    {
        return $type instanceof NonNull ? $type->getWrappedType() : $type;
    }

    /**
     * @param $type
     * @return UnmodifiedType
     */
    public static function getNamedType($type)
    {
        if (null === $type) {
            return null;
        }
        while ($type instanceof WrappingType) {
            $type = $type->getWrappedType();
        }
        return self::resolve($type);
    }

    /**
     * @param $type
     * @return mixed
     */
    public static function resolve($type)
    {
        if (is_callable($type)) {
            trigger_error(
                'Passing type as closure is deprecated (see https://github.com/webonyx/graphql-php/issues/35 for alternatives)',
                E_USER_DEPRECATED
            );
            $type = $type();
        }

        if (!$type instanceof Type) {
            throw new InvariantViolation(sprintf(
                'Expecting instance of ' . __CLASS__ . ', got "%s"',
                Utils::getVariableType($type)
            ));
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
        }
    }
}
