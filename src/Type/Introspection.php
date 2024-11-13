<?php declare(strict_types=1);

namespace GraphQL\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\GraphQL;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\Argument;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\WrappingType;
use GraphQL\Utils\AST;
use GraphQL\Utils\Utils;

/**
 * @phpstan-type IntrospectionOptions array{
 *     descriptions?: bool,
 *     directiveIsRepeatable?: bool,
 * }
 *
 * Available options:
 * - descriptions
 *   Whether to include descriptions in the introspection result.
 *   Default: true
 * - directiveIsRepeatable
 *   Whether to include `isRepeatable` flag on directives.
 *   Default: false
 *
 * @see \GraphQL\Tests\Type\IntrospectionTest
 */
class Introspection
{
    public const SCHEMA_FIELD_NAME = '__schema';
    public const TYPE_FIELD_NAME = '__type';
    public const TYPE_NAME_FIELD_NAME = '__typename';

    public const SCHEMA_OBJECT_NAME = '__Schema';
    public const TYPE_OBJECT_NAME = '__Type';
    public const DIRECTIVE_OBJECT_NAME = '__Directive';
    public const FIELD_OBJECT_NAME = '__Field';
    public const INPUT_VALUE_OBJECT_NAME = '__InputValue';
    public const ENUM_VALUE_OBJECT_NAME = '__EnumValue';
    public const TYPE_KIND_ENUM_NAME = '__TypeKind';
    public const DIRECTIVE_LOCATION_ENUM_NAME = '__DirectiveLocation';

    public const TYPE_NAMES = [
        self::SCHEMA_OBJECT_NAME,
        self::TYPE_OBJECT_NAME,
        self::DIRECTIVE_OBJECT_NAME,
        self::FIELD_OBJECT_NAME,
        self::INPUT_VALUE_OBJECT_NAME,
        self::ENUM_VALUE_OBJECT_NAME,
        self::TYPE_KIND_ENUM_NAME,
        self::DIRECTIVE_LOCATION_ENUM_NAME,
    ];

    /** @var array<string, mixed>|null */
    protected static ?array $cachedInstances;

    /**
     * @param IntrospectionOptions $options
     *
     * @api
     */
    public static function getIntrospectionQuery(array $options = []): string
    {
        $optionsWithDefaults = \array_merge([
            'descriptions' => true,
            'directiveIsRepeatable' => false,
        ], $options);

        $descriptions = $optionsWithDefaults['descriptions']
            ? 'description'
            : '';
        $directiveIsRepeatable = $optionsWithDefaults['directiveIsRepeatable']
            ? 'isRepeatable'
            : '';

        return <<<GRAPHQL
  query IntrospectionQuery {
    __schema {
      queryType { name }
      mutationType { name }
      subscriptionType { name }
      types {
        ...FullType
      }
      directives {
        name
        {$descriptions}
        args {
          ...InputValue
        }
        {$directiveIsRepeatable}
        locations
      }
    }
  }

  fragment FullType on __Type {
    kind
    name
    {$descriptions}
    fields(includeDeprecated: true) {
      name
      {$descriptions}
      args(includeDeprecated: true) {
        ...InputValue
      }
      type {
        ...TypeRef
      }
      isDeprecated
      deprecationReason
    }
    inputFields(includeDeprecated: true) {
      ...InputValue
    }
    interfaces {
      ...TypeRef
    }
    enumValues(includeDeprecated: true) {
      name
      {$descriptions}
      isDeprecated
      deprecationReason
    }
    possibleTypes {
      ...TypeRef
    }
  }

  fragment InputValue on __InputValue {
    name
    {$descriptions}
    type { ...TypeRef }
    defaultValue
    isDeprecated
    deprecationReason
  }

  fragment TypeRef on __Type {
    kind
    name
    ofType {
      kind
      name
      ofType {
        kind
        name
        ofType {
          kind
          name
          ofType {
            kind
            name
            ofType {
              kind
              name
              ofType {
                kind
                name
                ofType {
                  kind
                  name
                }
              }
            }
          }
        }
      }
    }
  }
GRAPHQL;
    }

    /**
     * Build an introspection query from a Schema.
     *
     * Introspection is useful for utilities that care about type and field
     * relationships, but do not need to traverse through those relationships.
     *
     * This is the inverse of BuildClientSchema::build(). The primary use case is
     * outside the server context, for instance when doing schema comparisons.
     *
     * @param IntrospectionOptions $options
     *
     * @throws \Exception
     * @throws \JsonException
     * @throws InvariantViolation
     *
     * @return array<string, array<mixed>>
     *
     * @api
     */
    public static function fromSchema(Schema $schema, array $options = []): array
    {
        $optionsWithDefaults = \array_merge(['directiveIsRepeatable' => true], $options);

        $result = GraphQL::executeQuery(
            $schema,
            self::getIntrospectionQuery($optionsWithDefaults)
        );

        $data = $result->data;
        if ($data === null) {
            $noDataResult = Utils::printSafeJson($result);
            throw new InvariantViolation("Introspection query returned no data: {$noDataResult}.");
        }

        return $data;
    }

    /** @param Type&NamedType $type */
    public static function isIntrospectionType(NamedType $type): bool
    {
        return \in_array($type->name, self::TYPE_NAMES, true);
    }

    /**
     * @throws InvariantViolation
     *
     * @return array<string, Type&NamedType>
     */
    public static function getTypes(): array
    {
        return [
            self::SCHEMA_OBJECT_NAME => self::_schema(),
            self::TYPE_OBJECT_NAME => self::_type(),
            self::DIRECTIVE_OBJECT_NAME => self::_directive(),
            self::FIELD_OBJECT_NAME => self::_field(),
            self::INPUT_VALUE_OBJECT_NAME => self::_inputValue(),
            self::ENUM_VALUE_OBJECT_NAME => self::_enumValue(),
            self::TYPE_KIND_ENUM_NAME => self::_typeKind(),
            self::DIRECTIVE_LOCATION_ENUM_NAME => self::_directiveLocation(),
        ];
    }

    /** @throws InvariantViolation */
    public static function _schema(): ObjectType
    {
        return self::$cachedInstances[self::SCHEMA_OBJECT_NAME] ??= new ObjectType([
            'name' => self::SCHEMA_OBJECT_NAME,
            'isIntrospection' => true,
            'description' => 'A GraphQL Schema defines the capabilities of a GraphQL '
                . 'server. It exposes all available types and directives on '
                . 'the server, as well as the entry points for query, mutation, and '
                . 'subscription operations.',
            'fields' => [
                'types' => [
                    'description' => 'A list of all types supported by this server.',
                    'type' => new NonNull(new ListOfType(new NonNull(self::_type()))),
                    'resolve' => static fn (Schema $schema): array => $schema->getTypeMap(),
                ],
                'queryType' => [
                    'description' => 'The type that query operations will be rooted at.',
                    'type' => new NonNull(self::_type()),
                    'resolve' => static fn (Schema $schema): ?ObjectType => $schema->getQueryType(),
                ],
                'mutationType' => [
                    'description' => 'If this server supports mutation, the type that mutation operations will be rooted at.',
                    'type' => self::_type(),
                    'resolve' => static fn (Schema $schema): ?ObjectType => $schema->getMutationType(),
                ],
                'subscriptionType' => [
                    'description' => 'If this server support subscription, the type that subscription operations will be rooted at.',
                    'type' => self::_type(),
                    'resolve' => static fn (Schema $schema): ?ObjectType => $schema->getSubscriptionType(),
                ],
                'directives' => [
                    'description' => 'A list of all directives supported by this server.',
                    'type' => Type::nonNull(Type::listOf(Type::nonNull(self::_directive()))),
                    'resolve' => static fn (Schema $schema): array => $schema->getDirectives(),
                ],
            ],
        ]);
    }

    /** @throws InvariantViolation */
    public static function _type(): ObjectType
    {
        return self::$cachedInstances[self::TYPE_OBJECT_NAME] ??= new ObjectType([
            'name' => self::TYPE_OBJECT_NAME,
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
            'fields' => static fn (): array => [
                'kind' => [
                    'type' => Type::nonNull(self::_typeKind()),
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
                    'type' => Type::string(),
                    'resolve' => static fn (Type $type): ?string => $type instanceof NamedType
                        ? $type->name
                        : null,
                ],
                'description' => [
                    'type' => Type::string(),
                    'resolve' => static fn (Type $type): ?string => $type instanceof NamedType
                        ? $type->description
                        : null,
                ],
                'fields' => [
                    'type' => Type::listOf(Type::nonNull(self::_field())),
                    'args' => [
                        'includeDeprecated' => [
                            'type' => Type::boolean(),
                            'defaultValue' => false,
                        ],
                    ],
                    'resolve' => static function (Type $type, $args): ?array {
                        if ($type instanceof ObjectType || $type instanceof InterfaceType) {
                            $fields = $type->getVisibleFields();

                            if (! ($args['includeDeprecated'] ?? false)) {
                                return \array_filter(
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
                    'type' => Type::listOf(Type::nonNull(self::_type())),
                    'resolve' => static fn ($type): ?array => $type instanceof ObjectType || $type instanceof InterfaceType
                        ? $type->getInterfaces()
                        : null,
                ],
                'possibleTypes' => [
                    'type' => Type::listOf(Type::nonNull(self::_type())),
                    'resolve' => static fn ($type, $args, $context, ResolveInfo $info): ?array => $type instanceof InterfaceType || $type instanceof UnionType
                        ? $info->schema->getPossibleTypes($type)
                        : null,
                ],
                'enumValues' => [
                    'type' => Type::listOf(Type::nonNull(self::_enumValue())),
                    'args' => [
                        'includeDeprecated' => [
                            'type' => Type::boolean(),
                            'defaultValue' => false,
                        ],
                    ],
                    'resolve' => static function ($type, $args): ?array {
                        if ($type instanceof EnumType) {
                            $values = $type->getValues();

                            if (! ($args['includeDeprecated'] ?? false)) {
                                return \array_filter(
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
                    'type' => Type::listOf(Type::nonNull(self::_inputValue())),
                    'args' => [
                        'includeDeprecated' => [
                            'type' => Type::boolean(),
                            'defaultValue' => false,
                        ],
                    ],
                    'resolve' => static function ($type, $args): ?array {
                        if ($type instanceof InputObjectType) {
                            $fields = $type->getFields();

                            if (! ($args['includeDeprecated'] ?? false)) {
                                return \array_filter(
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
                    'type' => self::_type(),
                    'resolve' => static fn ($type): ?Type => $type instanceof WrappingType
                        ? $type->getWrappedType()
                        : null,
                ],
            ],
        ]);
    }

    /** @throws InvariantViolation */
    public static function _typeKind(): EnumType
    {
        return self::$cachedInstances[self::TYPE_KIND_ENUM_NAME] ??= new EnumType([
            'name' => self::TYPE_KIND_ENUM_NAME,
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

    /** @throws InvariantViolation */
    public static function _field(): ObjectType
    {
        return self::$cachedInstances[self::FIELD_OBJECT_NAME] ??= new ObjectType([
            'name' => self::FIELD_OBJECT_NAME,
            'isIntrospection' => true,
            'description' => 'Object and Interface types are described by a list of Fields, each of '
                    . 'which has a name, potentially a list of arguments, and a return type.',
            'fields' => static fn (): array => [
                'name' => [
                    'type' => Type::nonNull(Type::string()),
                    'resolve' => static fn (FieldDefinition $field): string => $field->name,
                ],
                'description' => [
                    'type' => Type::string(),
                    'resolve' => static fn (FieldDefinition $field): ?string => $field->description,
                ],
                'args' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull(self::_inputValue()))),
                    'args' => [
                        'includeDeprecated' => [
                            'type' => Type::boolean(),
                            'defaultValue' => false,
                        ],
                    ],
                    'resolve' => static function (FieldDefinition $field, $args): array {
                        $values = $field->args;

                        if (! ($args['includeDeprecated'] ?? false)) {
                            return \array_filter(
                                $values,
                                static fn (Argument $value): bool => ! $value->isDeprecated(),
                            );
                        }

                        return $values;
                    },
                ],
                'type' => [
                    'type' => Type::nonNull(self::_type()),
                    'resolve' => static fn (FieldDefinition $field): Type => $field->getType(),
                ],
                'isDeprecated' => [
                    'type' => Type::nonNull(Type::boolean()),
                    'resolve' => static fn (FieldDefinition $field): bool => $field->isDeprecated(),
                ],
                'deprecationReason' => [
                    'type' => Type::string(),
                    'resolve' => static fn (FieldDefinition $field): ?string => $field->deprecationReason,
                ],
            ],
        ]);
    }

    /** @throws InvariantViolation */
    public static function _inputValue(): ObjectType
    {
        return self::$cachedInstances[self::INPUT_VALUE_OBJECT_NAME] ??= new ObjectType([
            'name' => self::INPUT_VALUE_OBJECT_NAME,
            'isIntrospection' => true,
            'description' => 'Arguments provided to Fields or Directives and the input fields of an '
                    . 'InputObject are represented as Input Values which describe their type '
                    . 'and optionally a default value.',
            'fields' => static fn (): array => [
                'name' => [
                    'type' => Type::nonNull(Type::string()),
                    /** @param Argument|InputObjectField $inputValue */
                    'resolve' => static fn ($inputValue): string => $inputValue->name,
                ],
                'description' => [
                    'type' => Type::string(),
                    /** @param Argument|InputObjectField $inputValue */
                    'resolve' => static fn ($inputValue): ?string => $inputValue->description,
                ],
                'type' => [
                    'type' => Type::nonNull(self::_type()),
                    /** @param Argument|InputObjectField $inputValue */
                    'resolve' => static fn ($inputValue): Type => $inputValue->getType(),
                ],
                'defaultValue' => [
                    'type' => Type::string(),
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
                    'type' => Type::nonNull(Type::boolean()),
                    /** @param Argument|InputObjectField $inputValue */
                    'resolve' => static fn ($inputValue): bool => $inputValue->isDeprecated(),
                ],
                'deprecationReason' => [
                    'type' => Type::string(),
                    /** @param Argument|InputObjectField $inputValue */
                    'resolve' => static fn ($inputValue): ?string => $inputValue->deprecationReason,
                ],
            ],
        ]);
    }

    /** @throws InvariantViolation */
    public static function _enumValue(): ObjectType
    {
        return self::$cachedInstances[self::ENUM_VALUE_OBJECT_NAME] ??= new ObjectType([
            'name' => self::ENUM_VALUE_OBJECT_NAME,
            'isIntrospection' => true,
            'description' => 'One possible value for a given Enum. Enum values are unique values, not '
                    . 'a placeholder for a string or numeric value. However an Enum value is '
                    . 'returned in a JSON response as a string.',
            'fields' => [
                'name' => [
                    'type' => Type::nonNull(Type::string()),
                    'resolve' => static fn (EnumValueDefinition $enumValue): string => $enumValue->name,
                ],
                'description' => [
                    'type' => Type::string(),
                    'resolve' => static fn (EnumValueDefinition $enumValue): ?string => $enumValue->description,
                ],
                'isDeprecated' => [
                    'type' => Type::nonNull(Type::boolean()),
                    'resolve' => static fn (EnumValueDefinition $enumValue): bool => $enumValue->isDeprecated(),
                ],
                'deprecationReason' => [
                    'type' => Type::string(),
                    'resolve' => static fn (EnumValueDefinition $enumValue): ?string => $enumValue->deprecationReason,
                ],
            ],
        ]);
    }

    /** @throws InvariantViolation */
    public static function _directive(): ObjectType
    {
        return self::$cachedInstances[self::DIRECTIVE_OBJECT_NAME] ??= new ObjectType([
            'name' => self::DIRECTIVE_OBJECT_NAME,
            'isIntrospection' => true,
            'description' => 'A Directive provides a way to describe alternate runtime execution and '
                . 'type validation behavior in a GraphQL document.'
                . "\n\nIn some cases, you need to provide options to alter GraphQL's "
                . 'execution behavior in ways field arguments will not suffice, such as '
                . 'conditionally including or skipping a field. Directives provide this by '
                . 'describing additional information to the executor.',
            'fields' => [
                'name' => [
                    'type' => Type::nonNull(Type::string()),
                    'resolve' => static fn (Directive $directive): string => $directive->name,
                ],
                'description' => [
                    'type' => Type::string(),
                    'resolve' => static fn (Directive $directive): ?string => $directive->description,
                ],
                'isRepeatable' => [
                    'type' => Type::nonNull(Type::boolean()),
                    'resolve' => static fn (Directive $directive): bool => $directive->isRepeatable,
                ],
                'locations' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull(
                        self::_directiveLocation()
                    ))),
                    'resolve' => static fn (Directive $directive): array => $directive->locations,
                ],
                'args' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull(self::_inputValue()))),
                    'resolve' => static fn (Directive $directive): array => $directive->args,
                ],
            ],
        ]);
    }

    /** @throws InvariantViolation */
    public static function _directiveLocation(): EnumType
    {
        return self::$cachedInstances[self::DIRECTIVE_LOCATION_ENUM_NAME] ??= new EnumType([
            'name' => self::DIRECTIVE_LOCATION_ENUM_NAME,
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

    /** @throws InvariantViolation */
    public static function schemaMetaFieldDef(): FieldDefinition
    {
        return self::$cachedInstances[self::SCHEMA_FIELD_NAME] ??= new FieldDefinition([
            'name' => self::SCHEMA_FIELD_NAME,
            'type' => Type::nonNull(self::_schema()),
            'description' => 'Access the current type schema of this server.',
            'args' => [],
            'resolve' => static fn ($source, array $args, $context, ResolveInfo $info): Schema => $info->schema,
        ]);
    }

    /** @throws InvariantViolation */
    public static function typeMetaFieldDef(): FieldDefinition
    {
        return self::$cachedInstances[self::TYPE_FIELD_NAME] ??= new FieldDefinition([
            'name' => self::TYPE_FIELD_NAME,
            'type' => self::_type(),
            'description' => 'Request the type information of a single type.',
            'args' => [
                [
                    'name' => 'name',
                    'type' => Type::nonNull(Type::string()),
                ],
            ],
            'resolve' => static fn ($source, array $args, $context, ResolveInfo $info): ?Type => $info->schema->getType($args['name']),
        ]);
    }

    /** @throws InvariantViolation */
    public static function typeNameMetaFieldDef(): FieldDefinition
    {
        return self::$cachedInstances[self::TYPE_NAME_FIELD_NAME] ??= new FieldDefinition([
            'name' => self::TYPE_NAME_FIELD_NAME,
            'type' => Type::nonNull(Type::string()),
            'description' => 'The name of the current Object type at runtime.',
            'args' => [],
            'resolve' => static fn ($source, array $args, $context, ResolveInfo $info): string => $info->parentType->name,
        ]);
    }

    public static function resetCachedInstances(): void
    {
        self::$cachedInstances = null;
    }
}
