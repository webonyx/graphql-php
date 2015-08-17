<?php
namespace GraphQL\Type\Definition;

use GraphQL\Utils;

abstract class Type
{
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
    const STRING = 'String';
    const INT = 'Int';
    const BOOLEAN = 'Boolean';
    const FLOAT = 'Float';
    const ID = 'ID';

    private static $internalTypes;

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
     * @return Type
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
     * @return Type
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

    public static function isLeafType($type)
    {
        // TODO: add LeafType interface
        $nakedType = self::getNamedType($type);
        return (
            $nakedType instanceof ScalarType ||
            $nakedType instanceof EnumType
        );
    }

    public static function isCompositeType($type)
    {
        return $type instanceof CompositeType;
    }

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

    public static function resolve($type)
    {
        if (is_callable($type)) {
            $type = $type();
        }

        Utils::invariant(
            $type instanceof Type,
            'Expecting instance of ' . __CLASS__ . ' (or callable returning instance of that type), got "%s"',
            Utils::getVariableType($type)
        );
        return $type;
    }

    /**
     * @param $value
     * @param AbstractType $abstractType
     * @return Type
     * @throws \Exception
     */
    public static function getTypeOf($value, ResolveInfo $info, AbstractType $abstractType)
    {
        $possibleTypes = $abstractType->getPossibleTypes();

        for ($i = 0; $i < count($possibleTypes); $i++) {
            /** @var ObjectType $type */
            $type = $possibleTypes[$i];
            $isTypeOf = $type->isTypeOf($value, $info);

            if ($isTypeOf === null) {
                // TODO: move this to a JS impl specific type system validation step
                // so the error can be found before execution.
                throw new \Exception(
                    'Non-Object Type ' . $abstractType->name . ' does not implement ' .
                    'getObjectType and Object Type ' . $type->name . ' does not implement ' .
                    'isTypeOf. There is no way to determine if a value is of this type.'
                );
            }

            if ($isTypeOf) {
                return $type;
            }
        }
        return null;
    }

    /**
     * @var string
     */
    public $name;

    /**
     * @var string|null
     */
    public $description;

    public function toString()
    {
        return $this->name;
    }

    public function __toString()
    {
        try {
            return $this->toString();
        } catch (\Exception $e) {
            echo $e;
        }
    }
}
