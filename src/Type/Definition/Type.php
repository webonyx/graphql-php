<?php
namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\DefinitionContainer;
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
abstract class Type
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
     * @return Type|array
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
     * @deprecated in favor of defining ObjectType 'fields' as closure (vs defining closure per field type)
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
        if ($type instanceof DefinitionContainer) {
            $type = $type->getDefinition();
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
     * @param $value
     * @param mixed $context
     * @param AbstractType $abstractType
     * @return Type
     * @throws \Exception
     */
    public static function getTypeOf($value, $context, ResolveInfo $info, AbstractType $abstractType)
    {
        $possibleTypes = $info->schema->getPossibleTypes($abstractType);

        foreach ($possibleTypes as $type) {
            /** @var ObjectType $type */
            if ($type->isTypeOf($value, $context, $info)) {
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
        } catch (\Exception $e) {
            echo $e;
        }
    }
}
