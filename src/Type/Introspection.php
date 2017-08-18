<?php
namespace GraphQL\Type;


use GraphQL\Language\Printer;
use GraphQL\Schema;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\DirectiveLocation;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\WrappingType;
use GraphQL\Utils;
use GraphQL\Utils\AST;
use Opis\Closure\SerializableClosure;

class TypeKind {
    const SCALAR = 0;
    const OBJECT = 1;
    const INTERFACE_KIND = 2;
    const UNION = 3;
    const ENUM = 4;
    const INPUT_OBJECT = 5;
    const LIST_KIND = 6;
    const NON_NULL = 7;
}

class Introspection
{
    private static $map = [];

    /**
     * @return string
     */
    public static function getIntrospectionQuery($includeDescription = true)
    {
        $withDescription = <<<'EOD'
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
        description
        locations
        args {
          ...InputValue
        }
      }
    }
  }

  fragment FullType on __Type {
    kind
    name
    description
    fields(includeDeprecated: true) {
      name
      description
      args {
        ...InputValue
      }
      type {
        ...TypeRef
      }
      isDeprecated
      deprecationReason
    }
    inputFields {
      ...InputValue
    }
    interfaces {
      ...TypeRef
    }
    enumValues(includeDeprecated: true) {
      name
      description
      isDeprecated
      deprecationReason
    }
    possibleTypes {
      ...TypeRef
    }
  }

  fragment InputValue on __InputValue {
    name
    description
    type { ...TypeRef }
    defaultValue
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
EOD;
        $withoutDescription = <<<'EOD'
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
        locations
        args {
          ...InputValue
        }
      }
    }
  }

  fragment FullType on __Type {
    kind
    name
    fields(includeDeprecated: true) {
      name
      args {
        ...InputValue
      }
      type {
        ...TypeRef
      }
      isDeprecated
      deprecationReason
    }
    inputFields {
      ...InputValue
    }
    interfaces {
      ...TypeRef
    }
    enumValues(includeDeprecated: true) {
      name
      isDeprecated
      deprecationReason
    }
    possibleTypes {
      ...TypeRef
    }
  }

  fragment InputValue on __InputValue {
    name
    type { ...TypeRef }
    defaultValue
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
EOD;
        return $includeDescription ? $withDescription : $withoutDescription;
    }

    public static function _schema()
    {
        if (!isset(self::$map['__Schema'])) {
            self::$map['__Schema'] = new ObjectType([
                'name' => '__Schema',
                'description' =>
                    'A GraphQL Schema defines the capabilities of a GraphQL ' .
                    'server. It exposes all available types and directives on ' .
                    'the server, as well as the entry points for query, mutation, and ' .
                    'subscription operations.',
                'fields' => [
                    'types' => [
                        'description' => 'A list of all types supported by this server.',
                        'type' => new NonNull(new ListOfType(new NonNull(self::_type()))),
                        'resolve' => 'GraphQL\Type\Introspection::resolveIntrospectionSchemaFields'
                    ],
                    'queryType' => [
                        'description' => 'The type that query operations will be rooted at.',
                        'type' => new NonNull(self::_type()),
                        'resolve' => 'GraphQL\Type\Introspection::resolveIntrospectionSchemaQueryType'
                    ],
                    'mutationType' => [
                        'description' =>
                            'If this server supports mutation, the type that ' .
                            'mutation operations will be rooted at.',
                        'type' => self::_type(),
                        'resolve' => 'GraphQL\Type\Introspection::resolveIntrospectionSchemaMutationType'
                    ],
                    'subscriptionType' => [
                        'description' => 'If this server support subscription, the type that subscription operations will be rooted at.',
                        'type'        => self::_type(),
                        'resolve'     => 'GraphQL\Type\Introspection::resolveIntrospectionSchemaSubscriptionType'
                    ],
                    'directives' => [
                        'description' => 'A list of all directives supported by this server.',
                        'type' => Type::nonNull(Type::listOf(Type::nonNull(self::_directive()))),
                        'resolve' => 'GraphQL\Type\Introspection::resolveIntrospectionSchemaDirectives'
                    ]
                ]
            ]);
        }
        return self::$map['__Schema'];
    }

    public static function _directive()
    {
        if (!isset(self::$map['__Directive'])) {
            self::$map['__Directive'] = new ObjectType([
                'name' => '__Directive',
                'description' =>     'A Directive provides a way to describe alternate runtime execution and ' .
                    'type validation behavior in a GraphQL document.' .
                    "\n\nIn some cases, you need to provide options to alter GraphQL's " .
                    'execution behavior in ways field arguments will not suffice, such as ' .
                    'conditionally including or skipping a field. Directives provide this by ' .
                    'describing additional information to the executor.',
                'fields' => [
                    'name' => ['type' => Type::nonNull(Type::string())],
                    'description' => ['type' => Type::string()],
                    'locations' => [
                        'type' => Type::nonNull(Type::listOf(Type::nonNull(
                            self::_directiveLocation()
                        )))
                    ],
                    'args' => [
                        'type' => Type::nonNull(Type::listOf(Type::nonNull(self::_inputValue()))),
                        'resolve' => 'GraphQL\Type\Introspection::resolveIntrospectionDirectiveOnArgs'
                    ],

                    // NOTE: the following three fields are deprecated and are no longer part
                    // of the GraphQL specification.
                    'onOperation' => [
                        'deprecationReason' => 'Use `locations`.',
                        'type' => Type::nonNull(Type::boolean()),
                        'resolve' => 'GraphQL\Type\Introspection::resolveIntrospectionDirectiveOnOperation'
                    ],
                    'onFragment' => [
                        'deprecationReason' => 'Use `locations`.',
                        'type' => Type::nonNull(Type::boolean()),
                        'resolve' => 'GraphQL\Type\Introspection::resolveIntrospectionDirectiveOnFragment'
                    ],
                    'onField' => [
                        'deprecationReason' => 'Use `locations`.',
                        'type' => Type::nonNull(Type::boolean()),
                        'resolve' => 'GraphQL\Type\Introspection::resolveIntrospectionDirectiveOnField'
                    ]
                ]
            ]);
        }
        return self::$map['__Directive'];
    }

    public static function _directiveLocation()
    {
        if (!isset(self::$map['__DirectiveLocation'])) {
            self::$map['__DirectiveLocation'] = new EnumType([
                'name' => '__DirectiveLocation',
                'description' =>
                    'A Directive can be adjacent to many parts of the GraphQL language, a ' .
                    '__DirectiveLocation describes one such possible adjacencies.',
                'values' => [
                    'QUERY' => [
                        'value' => DirectiveLocation::QUERY,
                        'description' => 'Location adjacent to a query operation.'
                    ],
                    'MUTATION' => [
                        'value' => DirectiveLocation::MUTATION,
                        'description' => 'Location adjacent to a mutation operation.'
                    ],
                    'SUBSCRIPTION' => [
                        'value' => DirectiveLocation::SUBSCRIPTION,
                        'description' => 'Location adjacent to a subscription operation.'
                    ],
                    'FIELD' => [
                        'value' => DirectiveLocation::FIELD,
                        'description' => 'Location adjacent to a field.'
                    ],
                    'FRAGMENT_DEFINITION' => [
                        'value' => DirectiveLocation::FRAGMENT_DEFINITION,
                        'description' => 'Location adjacent to a fragment definition.'
                    ],
                    'FRAGMENT_SPREAD' => [
                        'value' => DirectiveLocation::FRAGMENT_SPREAD,
                        'description' => 'Location adjacent to a fragment spread.'
                    ],
                    'INLINE_FRAGMENT' => [
                        'value' => DirectiveLocation::INLINE_FRAGMENT,
                        'description' => 'Location adjacent to an inline fragment.'
                    ],
                    'SCHEMA' => [
                      'value' => DirectiveLocation::SCHEMA,
                      'description' =>  'Location adjacent to a schema definition.'
                    ],
                    'SCALAR' => [
                      'value' => DirectiveLocation::SCALAR,
                      'description' =>  'Location adjacent to a scalar definition.'
                    ],
                    'OBJECT' => [
                      'value' => DirectiveLocation::OBJECT,
                      'description' =>  'Location adjacent to an object type definition.'
                    ],
                    'FIELD_DEFINITION' => [
                      'value' => DirectiveLocation::FIELD_DEFINITION,
                      'description' =>  'Location adjacent to a field definition.'
                    ],
                    'ARGUMENT_DEFINITION' => [
                      'value' => DirectiveLocation::ARGUMENT_DEFINITION,
                      'description' =>  'Location adjacent to an argument definition.'
                    ],
                    'INTERFACE' => [
                      'value' => DirectiveLocation::IFACE,
                      'description' =>  'Location adjacent to an interface definition.'
                    ],
                    'UNION' => [
                      'value' => DirectiveLocation::UNION,
                      'description' =>  'Location adjacent to a union definition.'
                    ],
                    'ENUM' => [
                      'value' => DirectiveLocation::ENUM,
                      'description' =>  'Location adjacent to an enum definition.'
                    ],
                    'ENUM_VALUE' => [
                      'value' => DirectiveLocation::ENUM_VALUE,
                      'description' =>  'Location adjacent to an enum value definition.'
                    ],
                    'INPUT_OBJECT' => [
                      'value' => DirectiveLocation::INPUT_OBJECT,
                      'description' =>  'Location adjacent to an input object type definition.'
                    ],
                    'INPUT_FIELD_DEFINITION' => [
                      'value' => DirectiveLocation::INPUT_FIELD_DEFINITION,
                      'description' =>  'Location adjacent to an input object field definition.'
                    ]

                ]
            ]);
        }
        return self::$map['__DirectiveLocation'];
    }

    public static function _type()
    {
        if (!isset(self::$map['__Type'])) {
            self::$map['__Type'] = new ObjectType([
                'name' => '__Type',
                'description' =>
                    'The fundamental unit of any GraphQL Schema is the type. There are ' .
                    'many kinds of types in GraphQL as represented by the `__TypeKind` enum.' .
                    "\n\n".
                    'Depending on the kind of a type, certain fields describe ' .
                    'information about that type. Scalar types provide no information ' .
                    'beyond a name and description, while Enum types provide their values. ' .
                    'Object and Interface types provide the fields they describe. Abstract ' .
                    'types, Union and Interface, provide the Object types possible ' .
                    'at runtime. List and NonNull types compose other types.',
                'fields' => new SerializableClosure(function() {
                    return [
                        'kind' => [
                            'type' => Type::nonNull(self::_typeKind()),
                            'resolve' => 'GraphQL\Type\Introspection::resolveIntrospectionTypeKind'
                        ],
                        'name' => ['type' => Type::string()],
                        'description' => ['type' => Type::string()],
                        'fields' => [
                            'type' => Type::listOf(Type::nonNull(self::_field())),
                            'args' => [
                                'includeDeprecated' => ['type' => Type::boolean(), 'defaultValue' => false]
                            ],
                            'resolve' => 'GraphQL\Type\Introspection::resolveIntrospectionTypeFields'
                        ],
                        'interfaces' => [
                            'type' => Type::listOf(Type::nonNull(self::_type())),
                            'resolve' => 'GraphQL\Type\Introspection::resolveIntrospectionTypeInterfaces'
                        ],
                        'possibleTypes' => [
                            'type' => Type::listOf(Type::nonNull(self::_type())),
                            'resolve' => 'GraphQL\Type\Introspection::resolveIntrospectionTypePossibleTypes'
                        ],
                        'enumValues' => [
                            'type' => Type::listOf(Type::nonNull(self::_enumValue())),
                            'args' => [
                                'includeDeprecated' => ['type' => Type::boolean(), 'defaultValue' => false]
                            ],
                            'resolve' => 'GraphQL\Type\Introspection::resolveIntrospectionTypeEnumValues'
                        ],
                        'inputFields' => [
                            'type' => Type::listOf(Type::nonNull(self::_inputValue())),
                            'resolve' => 'GraphQL\Type\Introspection::resolveIntrospectionTypeInputFields'
                        ],
                        'ofType' => [
                            'type' => self::_type(),
                            'resolve' => 'GraphQL\Type\Introspection::resolveIntrospectionTypeOfType'
                        ]
                    ];
                })
            ]);
        }
        return self::$map['__Type'];
    }

    public static function _field()
    {
        if (!isset(self::$map['__Field'])) {

            self::$map['__Field'] = new ObjectType([
                'name' => '__Field',
                'description' =>
                    'Object and Interface types are described by a list of Fields, each of ' .
                    'which has a name, potentially a list of arguments, and a return type.',
                'fields' => new SerializableClosure(function() {
                    return [
                        'name' => ['type' => Type::nonNull(Type::string())],
                        'description' => ['type' => Type::string()],
                        'args' => [
                            'type' => Type::nonNull(Type::listOf(Type::nonNull(self::_inputValue()))),
                            'resolve' => 'GraphQL\Type\Introspection::resolveIntrospectionFieldArgs'
                        ],
                        'type' => [
                            'type' => Type::nonNull(self::_type()),
                            'resolve' => 'GraphQL\Type\Introspection::resolveIntrospectionFieldType'
                        ],
                        'isDeprecated' => [
                            'type' => Type::nonNull(Type::boolean()),
                            'resolve' => 'GraphQL\Type\Introspection::resolveIntrospectionFieldIsDeprecated'
                        ],
                        'deprecationReason' => [
                            'type' => Type::string()
                        ]
                    ];
                })
            ]);
        }
        return self::$map['__Field'];
    }

    public static function _inputValue()
    {
        if (!isset(self::$map['__InputValue'])) {
            self::$map['__InputValue'] = new ObjectType([
                'name' => '__InputValue',
                'description' =>
                    'Arguments provided to Fields or Directives and the input fields of an ' .
                    'InputObject are represented as Input Values which describe their type ' .
                    'and optionally a default value.',
                'fields' => new SerializableClosure(function() {
                    return [
                        'name' => ['type' => Type::nonNull(Type::string())],
                        'description' => ['type' => Type::string()],
                        'type' => [
                            'type' => Type::nonNull(self::_type()),
                            'resolve' => 'GraphQL\Type\Introspection::resolveIntrospectionInputValueType'
                        ],
                        'defaultValue' => [
                            'type' => Type::string(),
                            'description' =>
                                'A GraphQL-formatted string representing the default value for this input value.',
                            'resolve' => 'GraphQL\Type\Introspection::resolveIntrospectionInputValueDefaultValue'
                        ]
                    ];
                })
            ]);
        }
        return self::$map['__InputValue'];
    }

    public static function _enumValue()
    {
        if (!isset(self::$map['__EnumValue'])) {
            self::$map['__EnumValue'] = new ObjectType([
                'name' => '__EnumValue',
                'description' =>
                    'One possible value for a given Enum. Enum values are unique values, not ' .
                    'a placeholder for a string or numeric value. However an Enum value is ' .
                    'returned in a JSON response as a string.',
                'fields' => [
                    'name' => ['type' => Type::nonNull(Type::string())],
                    'description' => ['type' => Type::string()],
                    'isDeprecated' => [
                        'type' => Type::nonNull(Type::boolean()),
                        'resolve' => 'GraphQL\Type\Introspection::resolveIntrospectionEnumIsDeprecated'
                    ],
                    'deprecationReason' => [
                        'type' => Type::string()
                    ]
                ]
            ]);
        }
        return self::$map['__EnumValue'];
    }

    public static function _typeKind()
    {
        if (!isset(self::$map['__TypeKind'])) {
            self::$map['__TypeKind'] = new EnumType([
                'name' => '__TypeKind',
                'description' => 'An enum describing what kind of type a given `__Type` is.',
                'values' => [
                    'SCALAR' => [
                        'value' => TypeKind::SCALAR,
                        'description' => 'Indicates this type is a scalar.'
                    ],
                    'OBJECT' => [
                        'value' => TypeKind::OBJECT,
                        'description' => 'Indicates this type is an object. `fields` and `interfaces` are valid fields.'
                    ],
                    'INTERFACE' => [
                        'value' => TypeKind::INTERFACE_KIND,
                        'description' => 'Indicates this type is an interface. `fields` and `possibleTypes` are valid fields.'
                    ],
                    'UNION' => [
                        'value' => TypeKind::UNION,
                        'description' => 'Indicates this type is a union. `possibleTypes` is a valid field.'
                    ],
                    'ENUM' => [
                        'value' => TypeKind::ENUM,
                        'description' => 'Indicates this type is an enum. `enumValues` is a valid field.'
                    ],
                    'INPUT_OBJECT' => [
                        'value' => TypeKind::INPUT_OBJECT,
                        'description' => 'Indicates this type is an input object. `inputFields` is a valid field.'
                    ],
                    'LIST' => [
                        'value' => TypeKind::LIST_KIND,
                        'description' => 'Indicates this type is a list. `ofType` is a valid field.'
                    ],
                    'NON_NULL' => [
                        'value' => TypeKind::NON_NULL,
                        'description' => 'Indicates this type is a non-null. `ofType` is a valid field.'
                    ]
                ]
            ]);
        }
        return self::$map['__TypeKind'];
    }

    public static function schemaMetaFieldDef()
    {
        if (!isset(self::$map['__schema'])) {
            self::$map['__schema'] = FieldDefinition::create([
                'name' => '__schema',
                'type' => Type::nonNull(self::_schema()),
                'description' => 'Access the current type schema of this server.',
                'args' => [],
                'resolve' => function (
                    $source,
                    $args,
                    $context,
                    ResolveInfo $info
                ) {
                    return $info->schema;
                }
            ]);
        }
        return self::$map['__schema'];
    }

    public static function typeMetaFieldDef()
    {
        if (!isset(self::$map['__type'])) {
            self::$map['__type'] = FieldDefinition::create([
                'name' => '__type',
                'type' => self::_type(),
                'description' => 'Request the type information of a single type.',
                'args' => [
                    ['name' => 'name', 'type' => Type::nonNull(Type::string())]
                ],
                'resolve' => function ($source, $args, $context, ResolveInfo $info) {
                    return $info->schema->getType($args['name']);
                }
            ]);
        }
        return self::$map['__type'];
    }

    public static function typeNameMetaFieldDef()
    {
        if (!isset(self::$map['__typename'])) {
            self::$map['__typename'] = FieldDefinition::create([
                'name' => '__typename',
                'type' => Type::nonNull(Type::string()),
                'description' => 'The name of the current Object type at runtime.',
                'args' => [],
                'resolve' => function (
                    $source,
                    $args,
                    $context,
                    ResolveInfo $info
                ) {
                    return $info->parentType->name;
                }
            ]);
        }
        return self::$map['__typename'];
    }

    /**
     * RESOVLERS
     */

    public static function resolveIntrospectionSchemaFields(Schema $schema)
    {
        return array_values($schema->getTypeMap());
    }

    public static function resolveIntrospectionSchemaQueryType(Schema $schema)
    {
        return $schema->getQueryType();
    }

    public static function resolveIntrospectionSchemaMutationType(Schema $schema)
    {
        return $schema->getMutationType();
    }

    public static function resolveIntrospectionSchemaSubscriptionType(Schema $schema)
    {
        return $schema->getSubscriptionType();
    }

    public static function resolveIntrospectionSchemaDirectives(Schema $schema)
    {
        return $schema->getDirectives();
    }

    public static function resolveIntrospectionDirectiveOnArgs(Directive $directive)
    {
        return $directive->args ?: [];
    }

    public static function resolveIntrospectionDirectiveOnOperation($d)
    {
        return in_array(DirectiveLocation::QUERY, $d->locations) ||
            in_array(DirectiveLocation::MUTATION, $d->locations) ||
            in_array(DirectiveLocation::SUBSCRIPTION, $d->locations);
    }

    public static function resolveIntrospectionDirectiveOnFragment($d)
    {
        return in_array(DirectiveLocation::FRAGMENT_SPREAD, $d->locations) ||
            in_array(DirectiveLocation::INLINE_FRAGMENT, $d->locations) ||
            in_array(DirectiveLocation::FRAGMENT_DEFINITION, $d->locations);
    }

    public static function resolveIntrospectionDirectiveOnField($d)
    {
        return in_array(DirectiveLocation::FIELD, $d->locations);
    }

    public static function resolveIntrospectionTypeKind(Type $type)
    {
        switch (true) {
            case $type instanceof ListOfType:
                return TypeKind::LIST_KIND;
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
                return TypeKind::INTERFACE_KIND;
            case $type instanceof UnionType:
                return TypeKind::UNION;
            default:
                throw new \Exception("Unknown kind of type: " . Utils::printSafe($type));
        }
    }

    public static function resolveIntrospectionTypeFields(Type $type, $args)
    {
        if ($type instanceof ObjectType || $type instanceof InterfaceType) {
            $fields = $type->getFields();

            if (empty($args['includeDeprecated'])) {
                $fields = array_filter($fields, function (FieldDefinition $field) {
                    return !$field->deprecationReason;
                });
            }

            return array_values($fields);
        }

        return null;
    }

    public static function resolveIntrospectionTypeInterfaces($type)
    {
        if ($type instanceof ObjectType) {
            return $type->getInterfaces();
        }

        return null;
    }

    public static function resolveIntrospectionTypePossibleTypes($type, $args, $context, ResolveInfo $info)
    {
        if ($type instanceof InterfaceType || $type instanceof UnionType) {
            return $info->schema->getPossibleTypes($type);
        }

        return null;
    }

    public static function resolveIntrospectionTypeEnumValues($type, $args)
    {
        if ($type instanceof EnumType) {
            $values = array_values($type->getValues());

            if (empty($args['includeDeprecated'])) {
                $values = array_filter($values, function ($value) {
                    return !$value->deprecationReason;
                });
            }

            return $values;
        }

        return null;
    }

    public static function resolveIntrospectionTypeInputFields($type)
    {
        if ($type instanceof InputObjectType) {
            return array_values($type->getFields());
        }

        return null;
    }

    public static function resolveIntrospectionTypeOfType($type)
    {
        if ($type instanceof WrappingType) {
            return $type->getWrappedType();
        }

        return null;
    }

    public static function resolveIntrospectionFieldArgs(FieldDefinition $field)
    {
        return empty($field->args) ? [] : $field->args;
    }

    public static function resolveIntrospectionFieldType(FieldDefinition $field)
    {
        return $field->getType();
    }

    public static function resolveIntrospectionFieldIsDeprecated(FieldDefinition $field)
    {
        return !! $field->deprecationReason;
    }

    public static function resolveIntrospectionInputValueType($value)
    {
        return method_exists($value, 'getType') ? $value->getType() : $value->type;
    }

    public static function resolveIntrospectionInputValueDefaultValue($inputValue)
    {
        /** @var FieldArgument|InputObjectField $inputValue */
        return !$inputValue->defaultValueExists()
            ? null
            : Printer::doPrint(AST::astFromValue($inputValue->defaultValue, $inputValue->getType()));
    }

    public static function resolveIntrospectionEnumIsDeprecated($enumValue)
    {
        return !! $enumValue->deprecationReason;
    }

    public static function resolveIntrospectionSchema($source, $args, $context, ResolveInfo $info)
    {
        return $info->schema;
    }

    public static function resolveIntrospectionType($source, $args, $context, ResolveInfo $info)
    {
        return $info->schema->getType($args['name']);
    }

    public static function resolveIntrospectionTypeName($source, $args, $context, ResolveInfo $info)
    {
        return $info->parentType->name;
    }
}
