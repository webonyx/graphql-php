<?php declare(strict_types=1);

namespace GraphQL\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\Argument;
use GraphQL\Type\Definition\BooleanType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\FloatType;
use GraphQL\Type\Definition\IDType;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\IntType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\StringType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\WrappingType;
use GraphQL\Utils\AST;
use GraphQL\Utils\Utils;

class BuiltInDefinitions
{
    protected const SCALAR_TYPE_NAMES = [
        Type::INT,
        Type::FLOAT,
        Type::STRING,
        Type::BOOLEAN,
        Type::ID,
    ];

    public const INTROSPECTION_TYPE_NAMES = [
        Introspection::SCHEMA_OBJECT_NAME,
        Introspection::TYPE_OBJECT_NAME,
        Introspection::DIRECTIVE_OBJECT_NAME,
        Introspection::FIELD_OBJECT_NAME,
        Introspection::INPUT_VALUE_OBJECT_NAME,
        Introspection::ENUM_VALUE_OBJECT_NAME,
        Introspection::TYPE_KIND_ENUM_NAME,
        Introspection::DIRECTIVE_LOCATION_ENUM_NAME,
    ];

    public const BUILT_IN_TYPE_NAMES = [
        ...self::SCALAR_TYPE_NAMES,
        ...self::INTROSPECTION_TYPE_NAMES,
    ];

    public const BUILT_IN_DIRECTIVE_NAMES = [
        Directive::INCLUDE_NAME,
        Directive::SKIP_NAME,
        Directive::DEPRECATED_NAME,
        Directive::ONE_OF_NAME,
    ];

    /** Singleton containing standard built-in definitions. */
    private static ?self $standard = null;

    /** @var array<string, ScalarType> */
    private array $scalarTypes = [];

    private ?ObjectType $schemaTypeInstance = null;

    private ?ObjectType $typeTypeInstance = null;

    private ?EnumType $typeKindTypeInstance = null;

    private ?ObjectType $fieldTypeInstance = null;

    private ?ObjectType $inputValueTypeInstance = null;

    private ?ObjectType $enumValueTypeInstance = null;

    private ?ObjectType $directiveTypeInstance = null;

    private ?EnumType $directiveLocationTypeInstance = null;

    /** @var array<string, FieldDefinition> */
    private array $metaFieldDefs = [];

    /** @var array<string, Directive> */
    private array $directives = [];

    /** @var array<string, Type&NamedType>|null */
    private ?array $typesCache = null;

    /** @var array<string, ScalarType> */
    private array $scalarTypeOverrides;

    /** @param array<string, ScalarType> $scalarTypeOverrides */
    public function __construct(array $scalarTypeOverrides = [])
    {
        $this->scalarTypeOverrides = $scalarTypeOverrides;
    }

    public static function standard(): self
    {
        return self::$standard ??= new self();
    }

    /** @param array<ScalarType> $types */
    public static function overrideScalarTypes(array $types): void
    {
        // Preserve non-overridden scalar instances from the current standard.
        $scalarOverrides = self::$standard !== null
            ? self::$standard->scalarTypes()
            : [];

        foreach ($types as $type) {
            // @phpstan-ignore-next-line generic type is not enforced by PHP
            if (! $type instanceof ScalarType) {
                $typeClass = ScalarType::class;
                $notType = Utils::printSafe($type);
                throw new InvariantViolation("Expecting instance of {$typeClass}, got {$notType}");
            }

            if (! in_array($type->name, self::SCALAR_TYPE_NAMES, true)) {
                $builtInScalarNames = implode(', ', self::SCALAR_TYPE_NAMES);
                $notBuiltInName = Utils::printSafe($type->name);
                throw new InvariantViolation("Expecting one of the following names for a built-in scalar type: {$builtInScalarNames}; got {$notBuiltInName}"); // @phpstan-ignore missingType.checkedException (validation is part of the public contract)
            }

            $scalarOverrides[$type->name] = $type;
        }

        self::$standard = new self($scalarOverrides);
    }

    public static function isIntrospectionType(NamedType $type): bool
    {
        return in_array($type->name, self::INTROSPECTION_TYPE_NAMES, true);
    }

    public static function isBuiltInDirective(Directive $directive): bool
    {
        return in_array($directive->name, self::BUILT_IN_DIRECTIVE_NAMES, true);
    }

    public function int(): ScalarType
    {
        return $this->scalarTypes[Type::INT]
            ??= $this->scalarTypeOverrides[Type::INT]
            ?? new IntType(); // @phpstan-ignore missingType.checkedException (static configuration is known to be correct)
    }

    public function float(): ScalarType
    {
        return $this->scalarTypes[Type::FLOAT]
            ??= $this->scalarTypeOverrides[Type::FLOAT]
            ?? new FloatType(); // @phpstan-ignore missingType.checkedException (static configuration is known to be correct)
    }

    public function string(): ScalarType
    {
        return $this->scalarTypes[Type::STRING]
            ??= $this->scalarTypeOverrides[Type::STRING]
            ?? new StringType(); // @phpstan-ignore missingType.checkedException (static configuration is known to be correct)
    }

    public function boolean(): ScalarType
    {
        return $this->scalarTypes[Type::BOOLEAN]
            ??= $this->scalarTypeOverrides[Type::BOOLEAN]
            ?? new BooleanType(); // @phpstan-ignore missingType.checkedException (static configuration is known to be correct)
    }

    public function id(): ScalarType
    {
        return $this->scalarTypes[Type::ID]
            ??= $this->scalarTypeOverrides[Type::ID]
            ?? new IDType(); // @phpstan-ignore missingType.checkedException (static configuration is known to be correct)
    }

    /** @return array<string, ScalarType> */
    public function scalarTypes(): array
    {
        return [
            Type::INT => $this->int(),
            Type::FLOAT => $this->float(),
            Type::STRING => $this->string(),
            Type::BOOLEAN => $this->boolean(),
            Type::ID => $this->id(),
        ];
    }

    public static function isBuiltInTypeName(string $name): bool
    {
        return in_array($name, BuiltInDefinitions::BUILT_IN_TYPE_NAMES, true);
    }

    public static function isBuiltInScalarName(string $name): bool
    {
        return in_array($name, self::SCALAR_TYPE_NAMES, true);
    }

    public static function isBuiltInType(NamedType $type): bool
    {
        return in_array($type->name, BuiltInDefinitions::BUILT_IN_TYPE_NAMES, true);
    }

    public function schemaType(): ObjectType
    {
        return $this->schemaTypeInstance ??= new ObjectType([ // @phpstan-ignore missingType.checkedException (static configuration is known to be correct)
            'name' => Introspection::SCHEMA_OBJECT_NAME,
            'isIntrospection' => true,
            'description' => 'A GraphQL Schema defines the capabilities of a GraphQL '
                . 'server. It exposes all available types and directives on '
                . 'the server, as well as the entry points for query, mutation, and '
                . 'subscription operations.',
            'fields' => [
                'description' => [
                    'type' => $this->string(),
                    'resolve' => static fn (Schema $schema): ?string => $schema->description,
                ],
                'types' => [
                    'description' => 'A list of all types supported by this server.',
                    'type' => new NonNull(new ListOfType(new NonNull($this->typeType()))),
                    'resolve' => static fn (Schema $schema): array => $schema->getTypeMap(),
                ],
                'queryType' => [
                    'description' => 'The type that query operations will be rooted at.',
                    'type' => new NonNull($this->typeType()),
                    'resolve' => static fn (Schema $schema): ?ObjectType => $schema->getQueryType(),
                ],
                'mutationType' => [
                    'description' => 'If this server supports mutation, the type that mutation operations will be rooted at.',
                    'type' => $this->typeType(),
                    'resolve' => static fn (Schema $schema): ?ObjectType => $schema->getMutationType(),
                ],
                'subscriptionType' => [
                    'description' => 'If this server support subscription, the type that subscription operations will be rooted at.',
                    'type' => $this->typeType(),
                    'resolve' => static fn (Schema $schema): ?ObjectType => $schema->getSubscriptionType(),
                ],
                'directives' => [
                    'description' => 'A list of all directives supported by this server.',
                    'type' => new NonNull(new ListOfType(new NonNull($this->directiveType()))),
                    'resolve' => static fn (Schema $schema): array => $schema->getDirectives(),
                ],
            ],
        ]);
    }

    public function typeType(): ObjectType
    {
        return $this->typeTypeInstance ??= new ObjectType([ // @phpstan-ignore missingType.checkedException (static configuration is known to be correct)
            'name' => Introspection::TYPE_OBJECT_NAME,
            'isIntrospection' => true,
            'description' => 'The fundamental unit of any GraphQL Schema is the type. There are '
                . 'many kinds of types in GraphQL as represented by the `__TypeKind` enum.'
                . "\n\n"
                . 'Depending on the kind of a type, certain fields describe '
                . 'information about that type. Scalar types provide no information '
                . 'beyond a name and description, while Enum types provide their values. '
                . 'Object and Interface types provide the fields they describe. Abstract '
                . 'types, Union and Interface, provide the Object types possible '
                . 'at runtime. List and NonNull types compose other types.',
            'fields' => fn (): array => [
                'kind' => [
                    'type' => new NonNull($this->typeKindType()),
                    'resolve' => static function (Type $type): string {
                        switch (true) {
                            case $type instanceof ListOfType:
                                return TypeKind::LIST;
                            case $type instanceof NonNull:
                                return TypeKind::NON_NULL;
                            case $type instanceof ScalarType:
                                return TypeKind::SCALAR;
                            case $type instanceof ObjectType:
                                return TypeKind::OBJECT;
                            case $type instanceof EnumType:
                                return TypeKind::ENUM;
                            case $type instanceof InputObjectType:
                                return TypeKind::INPUT_OBJECT;
                            case $type instanceof InterfaceType:
                                return TypeKind::INTERFACE;
                            case $type instanceof UnionType:
                                return TypeKind::UNION;
                            default:
                                $safeType = Utils::printSafe($type);
                                throw new \Exception("Unknown kind of type: {$safeType}");
                        }
                    },
                ],
                'name' => [
                    'type' => $this->string(),
                    'resolve' => static fn (Type $type): ?string => $type instanceof NamedType
                        ? $type->name
                        : null,
                ],
                'description' => [
                    'type' => $this->string(),
                    'resolve' => static fn (Type $type): ?string => $type instanceof NamedType
                        ? $type->description
                        : null,
                ],
                'fields' => [
                    'type' => new ListOfType(new NonNull($this->fieldType())),
                    'args' => [
                        'includeDeprecated' => [
                            'type' => new NonNull($this->boolean()),
                            'defaultValue' => false,
                        ],
                    ],
                    'resolve' => static function (Type $type, $args): ?array {
                        if ($type instanceof ObjectType || $type instanceof InterfaceType) {
                            $fields = $type->getVisibleFields();

                            if (! $args['includeDeprecated']) {
                                return array_filter(
                                    $fields,
                                    static fn (FieldDefinition $field): bool => ! $field->isDeprecated()
                                );
                            }

                            return $fields;
                        }

                        return null;
                    },
                ],
                'interfaces' => [
                    'type' => new ListOfType(new NonNull($this->typeType())),
                    'resolve' => static fn ($type): ?array => $type instanceof ObjectType || $type instanceof InterfaceType
                        ? $type->getInterfaces()
                        : null,
                ],
                'possibleTypes' => [
                    'type' => new ListOfType(new NonNull($this->typeType())),
                    'resolve' => static fn ($type, $args, $context, ResolveInfo $info): ?array => $type instanceof InterfaceType || $type instanceof UnionType
                        ? $info->schema->getPossibleTypes($type)
                        : null,
                ],
                'enumValues' => [
                    'type' => new ListOfType(new NonNull($this->enumValueType())),
                    'args' => [
                        'includeDeprecated' => [
                            'type' => new NonNull($this->boolean()),
                            'defaultValue' => false,
                        ],
                    ],
                    'resolve' => static function ($type, $args): ?array {
                        if ($type instanceof EnumType) {
                            $values = $type->getValues();

                            if (! $args['includeDeprecated']) {
                                return array_filter(
                                    $values,
                                    static fn (EnumValueDefinition $value): bool => ! $value->isDeprecated()
                                );
                            }

                            return $values;
                        }

                        return null;
                    },
                ],
                'inputFields' => [
                    'type' => new ListOfType(new NonNull($this->inputValueType())),
                    'args' => [
                        'includeDeprecated' => [
                            'type' => new NonNull($this->boolean()),
                            'defaultValue' => false,
                        ],
                    ],
                    'resolve' => static function ($type, $args): ?array {
                        if ($type instanceof InputObjectType) {
                            $fields = $type->getFields();

                            if (! $args['includeDeprecated']) {
                                return array_filter(
                                    $fields,
                                    static fn (InputObjectField $field): bool => ! $field->isDeprecated(),
                                );
                            }

                            return $fields;
                        }

                        return null;
                    },
                ],
                'ofType' => [
                    'type' => $this->typeType(),
                    'resolve' => static fn ($type): ?Type => $type instanceof WrappingType
                        ? $type->getWrappedType()
                        : null,
                ],
                'isOneOf' => [
                    'type' => $this->boolean(),
                    'resolve' => static fn ($type): ?bool => $type instanceof InputObjectType
                        ? $type->isOneOf()
                        : null,
                ],
            ],
        ]);
    }

    public function typeKindType(): EnumType
    {
        return $this->typeKindTypeInstance ??= new EnumType([ // @phpstan-ignore missingType.checkedException (static configuration is known to be correct)
            'name' => Introspection::TYPE_KIND_ENUM_NAME,
            'isIntrospection' => true,
            'description' => 'An enum describing what kind of type a given `__Type` is.',
            'values' => [
                'SCALAR' => [
                    'value' => TypeKind::SCALAR,
                    'description' => 'Indicates this type is a scalar.',
                ],
                'OBJECT' => [
                    'value' => TypeKind::OBJECT,
                    'description' => 'Indicates this type is an object. `fields` and `interfaces` are valid fields.',
                ],
                'INTERFACE' => [
                    'value' => TypeKind::INTERFACE,
                    'description' => 'Indicates this type is an interface. `fields`, `interfaces`, and `possibleTypes` are valid fields.',
                ],
                'UNION' => [
                    'value' => TypeKind::UNION,
                    'description' => 'Indicates this type is a union. `possibleTypes` is a valid field.',
                ],
                'ENUM' => [
                    'value' => TypeKind::ENUM,
                    'description' => 'Indicates this type is an enum. `enumValues` is a valid field.',
                ],
                'INPUT_OBJECT' => [
                    'value' => TypeKind::INPUT_OBJECT,
                    'description' => 'Indicates this type is an input object. `inputFields` is a valid field.',
                ],
                'LIST' => [
                    'value' => TypeKind::LIST,
                    'description' => 'Indicates this type is a list. `ofType` is a valid field.',
                ],
                'NON_NULL' => [
                    'value' => TypeKind::NON_NULL,
                    'description' => 'Indicates this type is a non-null. `ofType` is a valid field.',
                ],
            ],
        ]);
    }

    public function fieldType(): ObjectType
    {
        return $this->fieldTypeInstance ??= new ObjectType([ // @phpstan-ignore missingType.checkedException (static configuration is known to be correct)
            'name' => Introspection::FIELD_OBJECT_NAME,
            'isIntrospection' => true,
            'description' => 'Object and Interface types are described by a list of Fields, each of '
                    . 'which has a name, potentially a list of arguments, and a return type.',
            'fields' => fn (): array => [
                'name' => [
                    'type' => new NonNull($this->string()),
                    'resolve' => static fn (FieldDefinition $field): string => $field->name,
                ],
                'description' => [
                    'type' => $this->string(),
                    'resolve' => static fn (FieldDefinition $field): ?string => $field->description,
                ],
                'args' => [
                    'type' => new NonNull(new ListOfType(new NonNull($this->inputValueType()))),
                    'args' => [
                        'includeDeprecated' => [
                            'type' => new NonNull($this->boolean()),
                            'defaultValue' => false,
                        ],
                    ],
                    'resolve' => static function (FieldDefinition $field, $args): array {
                        $values = $field->args;

                        if (! $args['includeDeprecated']) {
                            return array_filter(
                                $values,
                                static fn (Argument $value): bool => ! $value->isDeprecated(),
                            );
                        }

                        return $values;
                    },
                ],
                'type' => [
                    'type' => new NonNull($this->typeType()),
                    'resolve' => static fn (FieldDefinition $field): Type => $field->getType(),
                ],
                'isDeprecated' => [
                    'type' => new NonNull($this->boolean()),
                    'resolve' => static fn (FieldDefinition $field): bool => $field->isDeprecated(),
                ],
                'deprecationReason' => [
                    'type' => $this->string(),
                    'resolve' => static fn (FieldDefinition $field): ?string => $field->deprecationReason,
                ],
            ],
        ]);
    }

    public function inputValueType(): ObjectType
    {
        return $this->inputValueTypeInstance ??= new ObjectType([ // @phpstan-ignore missingType.checkedException (static configuration is known to be correct)
            'name' => Introspection::INPUT_VALUE_OBJECT_NAME,
            'isIntrospection' => true,
            'description' => 'Arguments provided to Fields or Directives and the input fields of an '
                    . 'InputObject are represented as Input Values which describe their type '
                    . 'and optionally a default value.',
            'fields' => fn (): array => [
                'name' => [
                    'type' => new NonNull($this->string()),
                    /** @param Argument|InputObjectField $inputValue */
                    'resolve' => static fn ($inputValue): string => $inputValue->name,
                ],
                'description' => [
                    'type' => $this->string(),
                    /** @param Argument|InputObjectField $inputValue */
                    'resolve' => static fn ($inputValue): ?string => $inputValue->description,
                ],
                'type' => [
                    'type' => new NonNull($this->typeType()),
                    /** @param Argument|InputObjectField $inputValue */
                    'resolve' => static fn ($inputValue): Type => $inputValue->getType(),
                ],
                'defaultValue' => [
                    'type' => $this->string(),
                    'description' => 'A GraphQL-formatted string representing the default value for this input value.',
                    /** @param Argument|InputObjectField $inputValue */
                    'resolve' => static function ($inputValue): ?string {
                        if ($inputValue->defaultValueExists()) {
                            $defaultValueAST = AST::astFromValue($inputValue->defaultValue, $inputValue->getType());

                            if ($defaultValueAST === null) {
                                $inconvertibleDefaultValue = Utils::printSafe($inputValue->defaultValue);
                                throw new InvariantViolation("Unable to convert defaultValue of argument {$inputValue->name} into AST: {$inconvertibleDefaultValue}.");
                            }

                            return Printer::doPrint($defaultValueAST);
                        }

                        return null;
                    },
                ],
                'isDeprecated' => [
                    'type' => new NonNull($this->boolean()),
                    /** @param Argument|InputObjectField $inputValue */
                    'resolve' => static fn ($inputValue): bool => $inputValue->isDeprecated(),
                ],
                'deprecationReason' => [
                    'type' => $this->string(),
                    /** @param Argument|InputObjectField $inputValue */
                    'resolve' => static fn ($inputValue): ?string => $inputValue->deprecationReason,
                ],
            ],
        ]);
    }

    public function enumValueType(): ObjectType
    {
        return $this->enumValueTypeInstance ??= new ObjectType([ // @phpstan-ignore missingType.checkedException (static configuration is known to be correct)
            'name' => Introspection::ENUM_VALUE_OBJECT_NAME,
            'isIntrospection' => true,
            'description' => 'One possible value for a given Enum. Enum values are unique values, not '
                    . 'a placeholder for a string or numeric value. However an Enum value is '
                    . 'returned in a JSON response as a string.',
            'fields' => [
                'name' => [
                    'type' => new NonNull($this->string()),
                    'resolve' => static fn (EnumValueDefinition $enumValue): string => $enumValue->name,
                ],
                'description' => [
                    'type' => $this->string(),
                    'resolve' => static fn (EnumValueDefinition $enumValue): ?string => $enumValue->description,
                ],
                'isDeprecated' => [
                    'type' => new NonNull($this->boolean()),
                    'resolve' => static fn (EnumValueDefinition $enumValue): bool => $enumValue->isDeprecated(),
                ],
                'deprecationReason' => [
                    'type' => $this->string(),
                    'resolve' => static fn (EnumValueDefinition $enumValue): ?string => $enumValue->deprecationReason,
                ],
            ],
        ]);
    }

    public function directiveType(): ObjectType
    {
        return $this->directiveTypeInstance ??= new ObjectType([ // @phpstan-ignore missingType.checkedException (static configuration is known to be correct)
            'name' => Introspection::DIRECTIVE_OBJECT_NAME,
            'isIntrospection' => true,
            'description' => 'A Directive provides a way to describe alternate runtime execution and '
                . 'type validation behavior in a GraphQL document.'
                . "\n\nIn some cases, you need to provide options to alter GraphQL's "
                . 'execution behavior in ways field arguments will not suffice, such as '
                . 'conditionally including or skipping a field. Directives provide this by '
                . 'describing additional information to the executor.',
            'fields' => [
                'name' => [
                    'type' => new NonNull($this->string()),
                    'resolve' => static fn (Directive $directive): string => $directive->name,
                ],
                'description' => [
                    'type' => $this->string(),
                    'resolve' => static fn (Directive $directive): ?string => $directive->description,
                ],
                'isRepeatable' => [
                    'type' => new NonNull($this->boolean()),
                    'resolve' => static fn (Directive $directive): bool => $directive->isRepeatable,
                ],
                'locations' => [
                    'type' => new NonNull(new ListOfType(new NonNull(
                        $this->directiveLocationType()
                    ))),
                    'resolve' => static fn (Directive $directive): array => $directive->locations,
                ],
                'args' => [
                    'type' => new NonNull(new ListOfType(new NonNull($this->inputValueType()))),
                    'args' => [
                        'includeDeprecated' => [
                            'type' => new NonNull($this->boolean()),
                            'defaultValue' => false,
                        ],
                    ],
                    'resolve' => static function (Directive $directive, $args): array {
                        $values = $directive->args;

                        if (! $args['includeDeprecated']) {
                            return array_filter(
                                $values,
                                static fn (Argument $value): bool => ! $value->isDeprecated(),
                            );
                        }

                        return $values;
                    },
                ],
            ],
        ]);
    }

    public function directiveLocationType(): EnumType
    {
        return $this->directiveLocationTypeInstance ??= new EnumType([ // @phpstan-ignore missingType.checkedException (static configuration is known to be correct)
            'name' => Introspection::DIRECTIVE_LOCATION_ENUM_NAME,
            'isIntrospection' => true,
            'description' => 'A Directive can be adjacent to many parts of the GraphQL language, a '
                    . '__DirectiveLocation describes one such possible adjacencies.',
            'values' => [
                'QUERY' => [
                    'value' => DirectiveLocation::QUERY,
                    'description' => 'Location adjacent to a query operation.',
                ],
                'MUTATION' => [
                    'value' => DirectiveLocation::MUTATION,
                    'description' => 'Location adjacent to a mutation operation.',
                ],
                'SUBSCRIPTION' => [
                    'value' => DirectiveLocation::SUBSCRIPTION,
                    'description' => 'Location adjacent to a subscription operation.',
                ],
                'FIELD' => [
                    'value' => DirectiveLocation::FIELD,
                    'description' => 'Location adjacent to a field.',
                ],
                'FRAGMENT_DEFINITION' => [
                    'value' => DirectiveLocation::FRAGMENT_DEFINITION,
                    'description' => 'Location adjacent to a fragment definition.',
                ],
                'FRAGMENT_SPREAD' => [
                    'value' => DirectiveLocation::FRAGMENT_SPREAD,
                    'description' => 'Location adjacent to a fragment spread.',
                ],
                'INLINE_FRAGMENT' => [
                    'value' => DirectiveLocation::INLINE_FRAGMENT,
                    'description' => 'Location adjacent to an inline fragment.',
                ],
                'VARIABLE_DEFINITION' => [
                    'value' => DirectiveLocation::VARIABLE_DEFINITION,
                    'description' => 'Location adjacent to a variable definition.',
                ],
                'SCHEMA' => [
                    'value' => DirectiveLocation::SCHEMA,
                    'description' => 'Location adjacent to a schema definition.',
                ],
                'SCALAR' => [
                    'value' => DirectiveLocation::SCALAR,
                    'description' => 'Location adjacent to a scalar definition.',
                ],
                'OBJECT' => [
                    'value' => DirectiveLocation::OBJECT,
                    'description' => 'Location adjacent to an object type definition.',
                ],
                'FIELD_DEFINITION' => [
                    'value' => DirectiveLocation::FIELD_DEFINITION,
                    'description' => 'Location adjacent to a field definition.',
                ],
                'ARGUMENT_DEFINITION' => [
                    'value' => DirectiveLocation::ARGUMENT_DEFINITION,
                    'description' => 'Location adjacent to an argument definition.',
                ],
                'INTERFACE' => [
                    'value' => DirectiveLocation::IFACE,
                    'description' => 'Location adjacent to an interface definition.',
                ],
                'UNION' => [
                    'value' => DirectiveLocation::UNION,
                    'description' => 'Location adjacent to a union definition.',
                ],
                'ENUM' => [
                    'value' => DirectiveLocation::ENUM,
                    'description' => 'Location adjacent to an enum definition.',
                ],
                'ENUM_VALUE' => [
                    'value' => DirectiveLocation::ENUM_VALUE,
                    'description' => 'Location adjacent to an enum value definition.',
                ],
                'INPUT_OBJECT' => [
                    'value' => DirectiveLocation::INPUT_OBJECT,
                    'description' => 'Location adjacent to an input object type definition.',
                ],
                'INPUT_FIELD_DEFINITION' => [
                    'value' => DirectiveLocation::INPUT_FIELD_DEFINITION,
                    'description' => 'Location adjacent to an input object field definition.',
                ],
            ],
        ]);
    }

    /** @return array<string, Type&NamedType> */
    public function introspectionTypes(): array
    {
        return [
            Introspection::SCHEMA_OBJECT_NAME => $this->schemaType(),
            Introspection::TYPE_OBJECT_NAME => $this->typeType(),
            Introspection::DIRECTIVE_OBJECT_NAME => $this->directiveType(),
            Introspection::FIELD_OBJECT_NAME => $this->fieldType(),
            Introspection::INPUT_VALUE_OBJECT_NAME => $this->inputValueType(),
            Introspection::ENUM_VALUE_OBJECT_NAME => $this->enumValueType(),
            Introspection::TYPE_KIND_ENUM_NAME => $this->typeKindType(),
            Introspection::DIRECTIVE_LOCATION_ENUM_NAME => $this->directiveLocationType(),
        ];
    }

    public function schemaMetaFieldDef(): FieldDefinition
    {
        return $this->metaFieldDefs[Introspection::SCHEMA_FIELD_NAME] ??= new FieldDefinition([
            'name' => Introspection::SCHEMA_FIELD_NAME,
            'type' => new NonNull($this->schemaType()),
            'description' => 'Access the current type schema of this server.',
            'args' => [],
            'resolve' => static fn ($source, array $args, $context, ResolveInfo $info): Schema => $info->schema,
        ]);
    }

    public function typeMetaFieldDef(): FieldDefinition
    {
        return $this->metaFieldDefs[Introspection::TYPE_FIELD_NAME] ??= new FieldDefinition([
            'name' => Introspection::TYPE_FIELD_NAME,
            'type' => $this->typeType(),
            'description' => 'Request the type information of a single type.',
            'args' => [
                [
                    'name' => 'name',
                    'type' => new NonNull($this->string()),
                ],
            ],
            'resolve' => static fn ($source, array $args, $context, ResolveInfo $info): ?Type => $info->schema->getType($args['name']),
        ]);
    }

    public function typeNameMetaFieldDef(): FieldDefinition
    {
        return $this->metaFieldDefs[Introspection::TYPE_NAME_FIELD_NAME] ??= new FieldDefinition([
            'name' => Introspection::TYPE_NAME_FIELD_NAME,
            'type' => new NonNull($this->string()),
            'description' => 'The name of the current Object type at runtime.',
            'args' => [],
            'resolve' => static fn ($source, array $args, $context, ResolveInfo $info): string => $info->parentType->name,
        ]);
    }

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
                    'type' => new NonNull($this->boolean()),
                    'description' => 'Included when true.',
                ],
            ],
        ]);
    }

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
                    'type' => new NonNull($this->boolean()),
                    'description' => 'Skipped when true.',
                ],
            ],
        ]);
    }

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

    public function oneOfDirective(): Directive
    {
        return $this->directives[Directive::ONE_OF_NAME] ??= new Directive([
            'name' => Directive::ONE_OF_NAME,
            'description' => 'Indicates that an Input Object is a OneOf Input Object (and thus requires exactly one of its fields be provided).',
            'locations' => [
                DirectiveLocation::INPUT_OBJECT,
            ],
            'args' => [],
        ]);
    }

    /** @return array<string, Directive> */
    public function directives(): array
    {
        return [
            Directive::INCLUDE_NAME => $this->includeDirective(),
            Directive::SKIP_NAME => $this->skipDirective(),
            Directive::DEPRECATED_NAME => $this->deprecatedDirective(),
            Directive::ONE_OF_NAME => $this->oneOfDirective(),
        ];
    }

    /** @return array<string, Type&NamedType> */
    public function types(): array
    {
        return $this->typesCache ??= array_merge(
            $this->introspectionTypes(),
            $this->scalarTypes()
        );
    }
}
