<?php
namespace GraphQL\Type;


use GraphQL\Schema;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\FieldDefinition;
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
    private static $_map = [];

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
      types {
        ...FullType
      }
      directives {
        name
        description
        args {
          ...InputValue
        }
        onOperation
        onFragment
        onField
      }
    }
  }

  fragment FullType on __Type {
    kind
    name
    description
    fields {
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
    enumValues {
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
      types {
        ...FullType
      }
      directives {
        name
        args {
          ...InputValue
        }
        onOperation
        onFragment
        onField
      }
    }
  }

  fragment FullType on __Type {
    kind
    name
    fields {
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
    enumValues {
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
        }
      }
    }
  }
EOD;
        return $includeDescription ? $withDescription : $withoutDescription;
    }

    public static function _schema()
    {
        if (!isset(self::$_map['__Schema'])) {
            self::$_map['__Schema'] = new ObjectType([
                'name' => '__Schema',
                'description' =>
                    'A GraphQL Schema defines the capabilities of a GraphQL ' .
                    'server. It exposes all available types and directives on ' .
                    'the server, as well as the entry points for query and ' .
                    'mutation operations.',
                'fields' => [
                    'types' => [
                        'description' => 'A list of all types supported by this server.',
                        'type' => new NonNull(new ListOfType(new NonNull(self::_type()))),
                        'resolve' => function (Schema $schema) {
                            return array_values($schema->getTypeMap());
                        }
                    ],
                    'queryType' => [
                        'description' => 'The type that query operations will be rooted at.',
                        'type' => new NonNull(self::_type()),
                        'resolve' => function (Schema $schema) {
                            return $schema->getQueryType();
                        }
                    ],
                    'mutationType' => [
                        'description' =>
                            'If this server supports mutation, the type that ' .
                            'mutation operations will be rooted at.',
                        'type' => self::_type(),
                        'resolve' => function (Schema $schema) {
                            return $schema->getMutationType();
                        }
                    ],
                    'subscriptionType' => [
                        'description' => 'If this server support subscription, the type that subscription operations will be rooted at.',
                        'type'        => self::_type(),
                        'resolve'     => function (Schema $schema) {
                            return $schema->getSubscriptionType();
                        },
                    ],
                    'directives' => [
                        'description' => 'A list of all directives supported by this server.',
                        'type' => Type::nonNull(Type::listOf(Type::nonNull(self::_directive()))),
                        'resolve' => function(Schema $schema) {
                            return $schema->getDirectives();
                        }
                    ]
                ]
            ]);
        }
        return self::$_map['__Schema'];
    }

    public static function _directive()
    {
        if (!isset(self::$_map['__Directive'])) {
            self::$_map['__Directive'] = new ObjectType([
                'name' => '__Directive',
                'fields' => [
                    'name' => ['type' => Type::nonNull(Type::string())],
                    'description' => ['type' => Type::string()],
                    'args' => [
                        'type' => Type::nonNull(Type::listOf(Type::nonNull(self::_inputValue()))),
                        'resolve' => function(Directive $directive) {return $directive->args ?: [];}
                    ],
                    'onOperation' => ['type' => Type::nonNull(Type::boolean())],
                    'onFragment' => ['type' => Type::nonNull(Type::boolean())],
                    'onField' => ['type' => Type::nonNull(Type::boolean())]
                ]
            ]);
        }
        return self::$_map['__Directive'];
    }

    public static function _type()
    {
        if (!isset(self::$_map['__Type'])) {
            self::$_map['__Type'] = new ObjectType([
                'name' => '__Type',
                'fields' => [
                    'kind' => [
                        'type' => Type::nonNull(self::_typeKind()),
                        'resolve' => function (Type $type) {
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
                                    throw new \Exception("Unknown kind of type: " . print_r($type, true));
                            }
                        }
                    ],
                    'name' => ['type' => Type::string()],
                    'description' => ['type' => Type::string()],
                    'fields' => [
                        'type' => Type::listOf(Type::nonNull(self::_field())),
                        'args' => [
                            'includeDeprecated' => ['type' => Type::boolean(), 'defaultValue' => false]
                        ],
                        'resolve' => function (Type $type, $args) {
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
                    ],
                    'interfaces' => [
                        'type' => Type::listOf(Type::nonNull([__CLASS__, '_type'])),
                        'resolve' => function ($type) {
                            if ($type instanceof ObjectType) {
                                return $type->getInterfaces();
                            }
                            return null;
                        }
                    ],
                    'possibleTypes' => [
                        'type' => Type::listOf(Type::nonNull([__CLASS__, '_type'])),
                        'resolve' => function ($type) {
                            if ($type instanceof InterfaceType || $type instanceof UnionType) {
                                return $type->getPossibleTypes();
                            }
                            return null;
                        }
                    ],
                    'enumValues' => [
                        'type' => Type::listOf(Type::nonNull(self::_enumValue())),
                        'args' => [
                            'includeDeprecated' => ['type' => Type::boolean(), 'defaultValue' => false]
                        ],
                        'resolve' => function ($type, $args) {
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
                    ],
                    'inputFields' => [
                        'type' => Type::listOf(Type::nonNull(self::_inputValue())),
                        'resolve' => function ($type) {
                            if ($type instanceof InputObjectType) {
                                return array_values($type->getFields());
                            }
                            return null;
                        }
                    ],
                    'ofType' => [
                        'type' => [__CLASS__, '_type'],
                        'resolve' => function($type) {
                            if ($type instanceof WrappingType) {
                                return $type->getWrappedType();
                            }
                            return null;
                    }]
                ]
            ]);
        }
        return self::$_map['__Type'];
    }

    public static function _field()
    {
        if (!isset(self::$_map['__Field'])) {

            self::$_map['__Field'] = new ObjectType([
                'name' => '__Field',
                'fields' => [
                    'name' => ['type' => Type::nonNull(Type::string())],
                    'description' => ['type' => Type::string()],
                    'args' => [
                        'type' => Type::nonNull(Type::listOf(Type::nonNull(self::_inputValue()))),
                        'resolve' => function (FieldDefinition $field) {
                            return empty($field->args) ? [] : $field->args;
                        }
                    ],
                    'type' => [
                        'type' => Type::nonNull([__CLASS__, '_type']),
                        'resolve' => function ($field) {
                            return $field->getType();
                        }
                    ],
                    'isDeprecated' => [
                        'type' => Type::nonNull(Type::boolean()),
                        'resolve' => function (FieldDefinition $field) {
                            return !!$field->deprecationReason;
                        }
                    ],
                    'deprecationReason' => [
                        'type' => Type::string()
                    ]
                ]
            ]);
        }
        return self::$_map['__Field'];
    }

    public static function _inputValue()
    {
        if (!isset(self::$_map['__InputValue'])) {
            self::$_map['__InputValue'] = new ObjectType([
                'name' => '__InputValue',
                'fields' => [
                    'name' => ['type' => Type::nonNull(Type::string())],
                    'description' => ['type' => Type::string()],
                    'type' => [
                        'type' => Type::nonNull([__CLASS__, '_type']),
                        'resolve' => function($value) {
                            return method_exists($value, 'getType') ? $value->getType() : $value->type;
                        }
                    ],
                    'defaultValue' => [
                        'type' => Type::string(),
                        'resolve' => function ($inputValue) {
                            return $inputValue->defaultValue === null ? null : json_encode($inputValue->defaultValue);
                        }
                    ]
                ]
            ]);
        }
        return self::$_map['__InputValue'];
    }

    public static function _enumValue()
    {
        if (!isset(self::$_map['__EnumValue'])) {
            self::$_map['__EnumValue'] = new ObjectType([
                'name' => '__EnumValue',
                'fields' => [
                    'name' => ['type' => Type::nonNull(Type::string())],
                    'description' => ['type' => Type::string()],
                    'isDeprecated' => [
                        'type' => Type::nonNull(Type::boolean()),
                        'resolve' => function ($enumValue) {
                            return !!$enumValue->deprecationReason;
                        }
                    ],
                    'deprecationReason' => [
                        'type' => Type::string()
                    ]
                ]
            ]);
        }
        return self::$_map['__EnumValue'];
    }

    public static function _typeKind()
    {
        if (!isset(self::$_map['__TypeKind'])) {
            self::$_map['__TypeKind'] = new EnumType([
                'name' => '__TypeKind',
                'description' => 'An enum describing what kind of type a given __Type is',
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
        return self::$_map['__TypeKind'];
    }

    public static function schemaMetaFieldDef()
    {
        if (!isset(self::$_map['__schema'])) {
            self::$_map['__schema'] = FieldDefinition::create([
                'name' => '__schema',
                'type' => Type::nonNull(self::_schema()),
                'description' => 'Access the current type schema of this server.',
                'args' => [],
                'resolve' => function (
                    $source,
                    $args,
                    ResolveInfo $info
                ) {
                    return $info->schema;
                }
            ]);
        }
        return self::$_map['__schema'];
    }

    public static function typeMetaFieldDef()
    {
        if (!isset(self::$_map['__type'])) {
            self::$_map['__type'] = FieldDefinition::create([
                'name' => '__type',
                'type' => self::_type(),
                'description' => 'Request the type information of a single type.',
                'args' => [
                    ['name' => 'name', 'type' => Type::nonNull(Type::string())]
                ],
                'resolve' => function ($source, $args, ResolveInfo $info) {
                    return $info->schema->getType($args['name']);
                }
            ]);
        }
        return self::$_map['__type'];
    }

    public static function typeNameMetaFieldDef()
    {
        if (!isset(self::$_map['__typename'])) {
            self::$_map['__typename'] = FieldDefinition::create([
                'name' => '__typename',
                'type' => Type::nonNull(Type::string()),
                'description' => 'The name of the current Object type at runtime.',
                'args' => [],
                'resolve' => function (
                    $source,
                    $args,
                    ResolveInfo $info
                ) {
                    return $info->parentType->name;
                }
            ]);
        }
        return self::$_map['__typename'];
    }
}
