<?php declare(strict_types=1);

namespace GraphQL\Type\Registry;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Type\Definition\BooleanType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FloatType;
use GraphQL\Type\Definition\IDType;
use GraphQL\Type\Definition\IntType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\StringType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Introspection;

/**
 * A default implementation of the type registry.
 *
 * It will return the standard types by default, but can be initialized with custom types.
 *
 * When a type by class is requested, it will return a callable that will instantiate the type on first call.
 * On subsequent calls, it will return the same instance immediately.
 */
class DefaultStandardTypeRegistry implements StandardTypeRegistry, BuiltInDirectiveRegistry
{
    private static self $instance;

    /**
     * @var array{
     *   Int: (ScalarType|callable(): ScalarType),
     *   Float: (ScalarType|callable(): ScalarType),
     *   String: (ScalarType|callable(): ScalarType),
     *   Boolean: (ScalarType|callable(): ScalarType),
     *   ID: (ScalarType|callable(): ScalarType),
     * }
     */
    protected array $standardTypes;

    /** @var array<string, Directive> */
    protected array $directives = [];

    protected Introspection $introspection;

    /**
     * @param ScalarType|class-string<ScalarType> $intType
     * @param ScalarType|class-string<ScalarType> $floatType
     * @param ScalarType|class-string<ScalarType> $stringType
     * @param ScalarType|class-string<ScalarType> $booleanType
     * @param ScalarType|class-string<ScalarType> $idType
     */
    public function __construct(
        $intType = IntType::class,
        $floatType = FloatType::class,
        $stringType = StringType::class,
        $booleanType = BooleanType::class,
        $idType = IDType::class
    ) {
        $this->standardTypes = [
            Type::INT => $this->objectOrLazyInitialize($intType),
            Type::FLOAT => $this->objectOrLazyInitialize($floatType),
            Type::STRING => $this->objectOrLazyInitialize($stringType),
            Type::BOOLEAN => $this->objectOrLazyInitialize($booleanType),
            Type::ID => $this->objectOrLazyInitialize($idType),
        ];

        $this->introspection = new Introspection($this);
    }

    /**
     * @template T of object
     *
     * @param T|class-string<T> $objectOrClassName
     *
     * @return T|callable():T
     */
    protected function objectOrLazyInitialize($objectOrClassName)
    {
        return is_object($objectOrClassName) ? $objectOrClassName : fn () => new $objectOrClassName();
    }

    /** Returns a singleton instance of itself. */
    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Register a default instance of the type registry.
     *
     * When using multiple schemas, you should pass a type registry to the schema constructor instead.
     */
    public static function register(self $typeRegistry): void
    {
        self::$instance = $typeRegistry;
    }

    public function standardType(string $name): ScalarType
    {
        $type = $this->standardTypes[$name];
        if (is_callable($type)) {
            return $this->standardTypes[$name] = $type();
        }

        return $type;
    }

    /** @throws InvariantViolation */
    public function int(): ScalarType
    {
        return $this->standardType(Type::INT);
    }

    /** @throws InvariantViolation */
    public function float(): ScalarType
    {
        return $this->standardType(Type::FLOAT);
    }

    /** @throws InvariantViolation */
    public function string(): ScalarType
    {
        return $this->standardType(Type::STRING);
    }

    /** @throws InvariantViolation */
    public function boolean(): ScalarType
    {
        return $this->standardType(Type::BOOLEAN);
    }

    /** @throws InvariantViolation */
    public function id(): ScalarType
    {
        return $this->standardType(Type::ID);
    }

    /**
     * Returns all builtin scalar types.
     *
     * @throws InvariantViolation
     *
     * @return array<string, ScalarType>
     */
    public function standardTypes(): array
    {
        return [
            Type::INT => $this->int(),
            Type::FLOAT => $this->float(),
            Type::STRING => $this->string(),
            Type::BOOLEAN => $this->boolean(),
            Type::ID => $this->id(),
        ];
    }

    /**
     * @throws InvariantViolation
     *
     * @return array<string, Directive>
     */
    public function internalDirectives(): array
    {
        return [
            Directive::INCLUDE_NAME => $this->includeDirective(),
            Directive::SKIP_NAME => $this->skipDirective(),
            Directive::DEPRECATED_NAME => $this->deprecatedDirective(),
        ];
    }

    /** @throws InvariantViolation */
    public function includeDirective(): Directive
    {
        return $this->directives[Directive::INCLUDE_NAME] ??= new Directive([
            'name' => Directive::INCLUDE_NAME,
            'description' => 'Directs the executor to include this field or fragment only when the `if` argument is true.',
            'locations' => [
                DirectiveLocation::FIELD,
                DirectiveLocation::FRAGMENT_SPREAD,
                DirectiveLocation::INLINE_FRAGMENT,
            ],
            'args' => [
                Directive::IF_ARGUMENT_NAME => [
                    'type' => Type::nonNull($this->boolean()),
                    'description' => 'Included when true.',
                ],
            ],
        ]);
    }

    /** @throws InvariantViolation */
    public function skipDirective(): Directive
    {
        return $this->directives[Directive::SKIP_NAME] ??= new Directive([
            'name' => Directive::SKIP_NAME,
            'description' => 'Directs the executor to skip this field or fragment when the `if` argument is true.',
            'locations' => [
                DirectiveLocation::FIELD,
                DirectiveLocation::FRAGMENT_SPREAD,
                DirectiveLocation::INLINE_FRAGMENT,
            ],
            'args' => [
                Directive::IF_ARGUMENT_NAME => [
                    'type' => Type::nonNull($this->boolean()),
                    'description' => 'Skipped when true.',
                ],
            ],
        ]);
    }

    /** @throws InvariantViolation */
    public function deprecatedDirective(): Directive
    {
        return $this->directives[Directive::DEPRECATED_NAME] ??= new Directive([
            'name' => Directive::DEPRECATED_NAME,
            'description' => 'Marks an element of a GraphQL schema as no longer supported.',
            'locations' => [
                DirectiveLocation::FIELD_DEFINITION,
                DirectiveLocation::ENUM_VALUE,
                DirectiveLocation::ARGUMENT_DEFINITION,
                DirectiveLocation::INPUT_FIELD_DEFINITION,
            ],
            'args' => [
                Directive::REASON_ARGUMENT_NAME => [
                    'type' => $this->string(),
                    'description' => 'Explains why this element was deprecated, usually also including a suggestion for how to access supported similar data. Formatted using the Markdown syntax, as specified by [CommonMark](https://commonmark.org/).',
                    'defaultValue' => Directive::DEFAULT_DEPRECATION_REASON,
                ],
            ],
        ]);
    }

    public function introspection(): Introspection
    {
        return $this->introspection;
    }
}
