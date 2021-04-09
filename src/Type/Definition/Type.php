<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionNode;
use GraphQL\Type\Introspection;
use GraphQL\Utils\Utils;
use JsonSerializable;
use ReflectionClass;

use function array_keys;
use function array_merge;
use function assert;
use function implode;
use function in_array;
use function preg_replace;

/**
 * Registry of standard GraphQL types and base class for all other types.
 */
abstract class Type implements JsonSerializable
{
    public const STRING  = 'String';
    public const INT     = 'Int';
    public const BOOLEAN = 'Boolean';
    public const FLOAT   = 'Float';
    public const ID      = 'ID';

    /** @var array<string, ScalarType> */
    protected static $standardTypes;

    /** @var array<Type[] */
    private static $builtInTypes;

    /** @var string */
    public $name;

    /** @var string|null */
    public $description;

    /** @var (Node&TypeDefinitionNode)|null */
    public $astNode;

    /** @var mixed[] */
    public $config;

    /** @var array<Node&TypeExtensionNode> */
    public $extensionASTNodes;

    /**
     * @api
     */
    public static function id(): ScalarType
    {
        if (! isset(static::$standardTypes[self::ID])) {
            static::$standardTypes[self::ID] = new IDType();
        }

        return static::$standardTypes[self::ID];
    }

    /**
     * @api
     */
    public static function string(): ScalarType
    {
        if (! isset(static::$standardTypes[self::STRING])) {
            static::$standardTypes[self::STRING] = new StringType();
        }

        return static::$standardTypes[self::STRING];
    }

    /**
     * @api
     */
    public static function boolean(): ScalarType
    {
        if (! isset(static::$standardTypes[self::BOOLEAN])) {
            static::$standardTypes[self::BOOLEAN] = new BooleanType();
        }

        return static::$standardTypes[self::BOOLEAN];
    }

    /**
     * @api
     */
    public static function int(): ScalarType
    {
        if (! isset(static::$standardTypes[self::INT])) {
            static::$standardTypes[self::INT] = new IntType();
        }

        return static::$standardTypes[self::INT];
    }

    /**
     * @api
     */
    public static function float(): ScalarType
    {
        if (! isset(static::$standardTypes[self::FLOAT])) {
            static::$standardTypes[self::FLOAT] = new FloatType();
        }

        return static::$standardTypes[self::FLOAT];
    }

    /**
     * @param Type|callable():Type $type
     *
     * @api
     */
    public static function listOf($wrappedType): ListOfType
    {
        return new ListOfType($wrappedType);
    }

    /**
     * code sniffer doesn't understand this syntax. Pr with a fix here: waiting on https://github.com/squizlabs/PHP_CodeSniffer/pull/2919
     * phpcs:disable Squiz.Commenting.FunctionComment.SpacingAfterParamType
     * @param (NullableType&Type)|callable():(NullableType&Type) $type
     *
     * @api
     */
    public static function nonNull($wrappedType): NonNull
    {
        return new NonNull($wrappedType);
    }

    /**
     * Checks if the type is a builtin type.
     */
    public static function isBuiltInType(Type $type): bool
    {
        return in_array(
            $type->name,
            array_keys(self::getAllBuiltInTypes()),
            true
        );
    }

    /**
     * Returns all builtin in types including base scalar and introspection types.
     *
     * @return array<string, Type>
     */
    public static function getAllBuiltInTypes(): array
    {
        if (self::$builtInTypes === null) {
            self::$builtInTypes = array_merge(
                Introspection::getTypes(),
                self::getStandardTypes()
            );
        }

        return self::$builtInTypes;
    }

    /**
     * Returns all builtin scalar types.
     *
     * @return array<string, ScalarType>
     */
    public static function getStandardTypes(): array
    {
        return [
            self::ID => static::id(),
            self::STRING => static::string(),
            self::FLOAT => static::float(),
            self::INT => static::int(),
            self::BOOLEAN => static::boolean(),
        ];
    }

    /**
     * @param array<string, ScalarType> $types
     */
    public static function overrideStandardTypes(array $types): void
    {
        $standardTypes = self::getStandardTypes();
        foreach ($types as $type) {
            Utils::invariant(
                $type instanceof Type,
                'Expecting instance of %s, got %s',
                self::class,
                Utils::printSafe($type)
            );
            Utils::invariant(
                isset($type->name, $standardTypes[$type->name]),
                'Expecting one of the following names for a standard type: %s, got %s',
                implode(', ', array_keys($standardTypes)),
                Utils::printSafe($type->name ?? null)
            );
            static::$standardTypes[$type->name] = $type;
        }
    }

    /**
     * @api
     */
    public static function isInputType(Type $type): bool
    {
        return self::getNamedType($type) instanceof InputType;
    }

    /**
     * @api
     */
    public static function getNamedType(Type $type): ?Type
    {
        while ($type instanceof WrappingType) {
            $type = $type->getWrappedType();
        }

        return $type;
    }

    /**
     * @api
     */
    public static function isOutputType(Type $type): bool
    {
        return self::getNamedType($type) instanceof OutputType;
    }

    /**
     * @api
     */
    public static function isLeafType(Type $type): bool
    {
        return $type instanceof LeafType;
    }

    /**
     * @api
     */
    public static function isCompositeType(Type $type): bool
    {
        return $type instanceof CompositeType;
    }

    /**
     * @api
     */
    public static function isAbstractType(Type $type): bool
    {
        return $type instanceof AbstractType;
    }

    /**
     * @param mixed $type
     */
    public static function assertType($type): Type
    {
        assert(
            $type instanceof Type,
            new InvariantViolation('Expected ' . Utils::printSafe($type) . ' to be a GraphQL type.')
        );

        return $type;
    }

    /**
     * @api
     */
    public static function getNullableType(Type $type): Type
    {
        return $type instanceof NonNull
            ? $type->getWrappedType()
            : $type;
    }

    /**
     * @throws InvariantViolation
     */
    public function assertValid(): void
    {
        Utils::assertValidName($this->name);
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        return $this->name;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    protected function tryInferName(): ?string
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
