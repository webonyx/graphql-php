<?php
namespace GraphQL\Type;

use GraphQL\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Schema;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Validator\Messages;

class IntrospectionTest extends \PHPUnit_Framework_TestCase
{
    function testExecutesAnIntrospectionQuery()
    {
        $emptySchema = new Schema(new ObjectType([
            'name' => 'QueryRoot',
            'fields' => []
        ]));

        $request = <<<'EOD'
      query IntrospectionTestQuery {
        schemaType: __type(name: "__Schema") {
          name
        }
        queryRootType: __type(name: "QueryRoot") {
          name
        }
        __schema {
          __typename
          types {
            __typename
            kind
            name
            fields {
              __typename
              name
              args {
                __typename
                name
                type { ...TypeRef }
                defaultValue
              }
              type {
                ...TypeRef
              }
              isDeprecated
              deprecationReason
            }
            interfaces {
              ...TypeRef
            }
            enumValues {
              __typename
              name
              isDeprecated
              deprecationReason
            }
          }
          directives {
            __typename
            name
            type { ...TypeRef }
            onOperation
            onFragment
            onField
          }
        }
      }

      fragment TypeRef on __Type {
        __typename
        kind
        name
        ofType {
          __typename
          kind
          name
          ofType {
            __typename
            kind
            name
            ofType {
              __typename
              kind
              name
            }
          }
        }
      }
EOD;

        $expected = array(
            'data' =>
                array(
                    'schemaType' =>
                        array(
                            'name' => '__Schema',
                        ),
                    'queryRootType' =>
                        array(
                            'name' => 'QueryRoot',
                        ),
                    '__schema' =>
                        array(
                            '__typename' => '__Schema',
                            'types' =>
                                array(
                                    0 =>
                                        array(
                                            '__typename' => '__Type',
                                            'kind' => 'OBJECT',
                                            'name' => 'QueryRoot',
                                            'fields' =>
                                                array(
                                                ),
                                            'interfaces' =>
                                                array(),
                                            'enumValues' => NULL,
                                        ),
                                    1 =>
                                        array(
                                            '__typename' => '__Type',
                                            'kind' => 'OBJECT',
                                            'name' => '__Schema',
                                            'fields' =>
                                                array(
                                                    0 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'types',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'NON_NULL',
                                                                    'name' => NULL,
                                                                    'ofType' =>
                                                                        array(
                                                                            '__typename' => '__Type',
                                                                            'kind' => 'LIST',
                                                                            'name' => NULL,
                                                                            'ofType' =>
                                                                                array(
                                                                                    '__typename' => '__Type',
                                                                                    'kind' => 'NON_NULL',
                                                                                    'name' => NULL,
                                                                                    'ofType' =>
                                                                                        array(
                                                                                            '__typename' => '__Type',
                                                                                            'kind' => 'OBJECT',
                                                                                            'name' => '__Type',
                                                                                        ),
                                                                                ),
                                                                        ),
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    1 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'queryType',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'NON_NULL',
                                                                    'name' => NULL,
                                                                    'ofType' =>
                                                                        array(
                                                                            '__typename' => '__Type',
                                                                            'kind' => 'OBJECT',
                                                                            'name' => '__Type',
                                                                            'ofType' => NULL,
                                                                        ),
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    2 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'mutationType',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'OBJECT',
                                                                    'name' => '__Type',
                                                                    'ofType' => NULL,
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    3 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'directives',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'NON_NULL',
                                                                    'name' => NULL,
                                                                    'ofType' =>
                                                                        array(
                                                                            '__typename' => '__Type',
                                                                            'kind' => 'LIST',
                                                                            'name' => NULL,
                                                                            'ofType' =>
                                                                                array(
                                                                                    '__typename' => '__Type',
                                                                                    'kind' => 'NON_NULL',
                                                                                    'name' => NULL,
                                                                                    'ofType' =>
                                                                                        array(
                                                                                            '__typename' => '__Type',
                                                                                            'kind' => 'OBJECT',
                                                                                            'name' => '__Directive',
                                                                                        ),
                                                                                ),
                                                                        ),
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                ),
                                            'interfaces' =>
                                                array(),
                                            'enumValues' => NULL,
                                        ),
                                    2 =>
                                        array(
                                            '__typename' => '__Type',
                                            'kind' => 'OBJECT',
                                            'name' => '__Type',
                                            'fields' =>
                                                array(
                                                    0 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'kind',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'NON_NULL',
                                                                    'name' => NULL,
                                                                    'ofType' =>
                                                                        array(
                                                                            '__typename' => '__Type',
                                                                            'kind' => 'ENUM',
                                                                            'name' => '__TypeKind',
                                                                            'ofType' => NULL,
                                                                        ),
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    1 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'name',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'SCALAR',
                                                                    'name' => 'String',
                                                                    'ofType' => NULL,
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    2 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'description',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'SCALAR',
                                                                    'name' => 'String',
                                                                    'ofType' => NULL,
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    3 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'fields',
                                                            'args' =>
                                                                array(
                                                                    0 =>
                                                                        array(
                                                                            '__typename' => '__InputValue',
                                                                            'name' => 'includeDeprecated',
                                                                            'type' =>
                                                                                array(
                                                                                    '__typename' => '__Type',
                                                                                    'kind' => 'SCALAR',
                                                                                    'name' => 'Boolean',
                                                                                    'ofType' => NULL,
                                                                                ),
                                                                            'defaultValue' => 'false',
                                                                        ),
                                                                ),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'LIST',
                                                                    'name' => NULL,
                                                                    'ofType' =>
                                                                        array(
                                                                            '__typename' => '__Type',
                                                                            'kind' => 'NON_NULL',
                                                                            'name' => NULL,
                                                                            'ofType' =>
                                                                                array(
                                                                                    '__typename' => '__Type',
                                                                                    'kind' => 'OBJECT',
                                                                                    'name' => '__Field',
                                                                                    'ofType' => NULL,
                                                                                ),
                                                                        ),
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    4 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'interfaces',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'LIST',
                                                                    'name' => NULL,
                                                                    'ofType' =>
                                                                        array(
                                                                            '__typename' => '__Type',
                                                                            'kind' => 'NON_NULL',
                                                                            'name' => NULL,
                                                                            'ofType' =>
                                                                                array(
                                                                                    '__typename' => '__Type',
                                                                                    'kind' => 'OBJECT',
                                                                                    'name' => '__Type',
                                                                                    'ofType' => NULL,
                                                                                ),
                                                                        ),
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    5 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'possibleTypes',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'LIST',
                                                                    'name' => NULL,
                                                                    'ofType' =>
                                                                        array(
                                                                            '__typename' => '__Type',
                                                                            'kind' => 'NON_NULL',
                                                                            'name' => NULL,
                                                                            'ofType' =>
                                                                                array(
                                                                                    '__typename' => '__Type',
                                                                                    'kind' => 'OBJECT',
                                                                                    'name' => '__Type',
                                                                                    'ofType' => NULL,
                                                                                ),
                                                                        ),
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    6 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'enumValues',
                                                            'args' =>
                                                                array(
                                                                    0 =>
                                                                        array(
                                                                            '__typename' => '__InputValue',
                                                                            'defaultValue' => 'false',
                                                                            'name' => 'includeDeprecated',
                                                                            'type' =>
                                                                                array(
                                                                                    '__typename' => '__Type',
                                                                                    'kind' => 'SCALAR',
                                                                                    'name' => 'Boolean',
                                                                                    'ofType' => NULL,
                                                                                ),
                                                                        ),
                                                                ),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'LIST',
                                                                    'name' => NULL,
                                                                    'ofType' =>
                                                                        array(
                                                                            '__typename' => '__Type',
                                                                            'kind' => 'NON_NULL',
                                                                            'name' => NULL,
                                                                            'ofType' =>
                                                                                array(
                                                                                    '__typename' => '__Type',
                                                                                    'kind' => 'OBJECT',
                                                                                    'name' => '__EnumValue',
                                                                                    'ofType' => NULL,
                                                                                ),
                                                                        ),
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    7 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'inputFields',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'LIST',
                                                                    'name' => NULL,
                                                                    'ofType' =>
                                                                        array(
                                                                            '__typename' => '__Type',
                                                                            'kind' => 'NON_NULL',
                                                                            'name' => NULL,
                                                                            'ofType' =>
                                                                                array(
                                                                                    '__typename' => '__Type',
                                                                                    'kind' => 'OBJECT',
                                                                                    'name' => '__InputValue',
                                                                                    'ofType' => NULL,
                                                                                ),
                                                                        ),
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    8 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'ofType',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'OBJECT',
                                                                    'name' => '__Type',
                                                                    'ofType' => NULL,
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                ),
                                            'interfaces' =>
                                                array(),
                                            'enumValues' => NULL,
                                        ),
                                    3 =>
                                        array(
                                            '__typename' => '__Type',
                                            'kind' => 'ENUM',
                                            'name' => '__TypeKind',
                                            'fields' => NULL,
                                            'interfaces' => NULL,
                                            'enumValues' =>
                                                array(
                                                    0 =>
                                                        array(
                                                            '__typename' => '__EnumValue',
                                                            'name' => 'SCALAR',
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    1 =>
                                                        array(
                                                            '__typename' => '__EnumValue',
                                                            'name' => 'OBJECT',
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    2 =>
                                                        array(
                                                            '__typename' => '__EnumValue',
                                                            'name' => 'INTERFACE',
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    3 =>
                                                        array(
                                                            '__typename' => '__EnumValue',
                                                            'name' => 'UNION',
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    4 =>
                                                        array(
                                                            '__typename' => '__EnumValue',
                                                            'name' => 'ENUM',
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    5 =>
                                                        array(
                                                            '__typename' => '__EnumValue',
                                                            'name' => 'INPUT_OBJECT',
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    6 =>
                                                        array(
                                                            '__typename' => '__EnumValue',
                                                            'name' => 'LIST',
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    7 =>
                                                        array(
                                                            '__typename' => '__EnumValue',
                                                            'name' => 'NON_NULL',
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                ),
                                        ),
                                    4 =>
                                        array(
                                            '__typename' => '__Type',
                                            'kind' => 'SCALAR',
                                            'name' => 'String',
                                            'fields' => NULL,
                                            'interfaces' => NULL,
                                            'enumValues' => NULL,
                                        ),
                                    5 =>
                                        array(
                                            '__typename' => '__Type',
                                            'kind' => 'SCALAR',
                                            'name' => 'Boolean',
                                            'fields' => NULL,
                                            'interfaces' => NULL,
                                            'enumValues' => NULL,
                                        ),
                                    6 =>
                                        array(
                                            '__typename' => '__Type',
                                            'kind' => 'OBJECT',
                                            'name' => '__Field',
                                            'fields' =>
                                                array(
                                                    0 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'name',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'NON_NULL',
                                                                    'name' => NULL,
                                                                    'ofType' =>
                                                                        array(
                                                                            '__typename' => '__Type',
                                                                            'kind' => 'SCALAR',
                                                                            'name' => 'String',
                                                                            'ofType' => NULL,
                                                                        ),
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    1 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'description',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'SCALAR',
                                                                    'name' => 'String',
                                                                    'ofType' => NULL,
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    2 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'args',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'NON_NULL',
                                                                    'name' => NULL,
                                                                    'ofType' =>
                                                                        array(
                                                                            '__typename' => '__Type',
                                                                            'kind' => 'LIST',
                                                                            'name' => NULL,
                                                                            'ofType' =>
                                                                                array(
                                                                                    '__typename' => '__Type',
                                                                                    'kind' => 'NON_NULL',
                                                                                    'name' => NULL,
                                                                                    'ofType' =>
                                                                                        array(
                                                                                            '__typename' => '__Type',
                                                                                            'kind' => 'OBJECT',
                                                                                            'name' => '__InputValue',
                                                                                        ),
                                                                                ),
                                                                        ),
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    3 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'type',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'NON_NULL',
                                                                    'name' => NULL,
                                                                    'ofType' =>
                                                                        array(
                                                                            '__typename' => '__Type',
                                                                            'kind' => 'OBJECT',
                                                                            'name' => '__Type',
                                                                            'ofType' => NULL,
                                                                        ),
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    4 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'isDeprecated',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'NON_NULL',
                                                                    'name' => NULL,
                                                                    'ofType' =>
                                                                        array(
                                                                            '__typename' => '__Type',
                                                                            'kind' => 'SCALAR',
                                                                            'name' => 'Boolean',
                                                                            'ofType' => NULL,
                                                                        ),
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    5 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'deprecationReason',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'SCALAR',
                                                                    'name' => 'String',
                                                                    'ofType' => NULL,
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                ),
                                            'interfaces' =>
                                                array(),
                                            'enumValues' => NULL,
                                        ),
                                    7 =>
                                        array(
                                            '__typename' => '__Type',
                                            'kind' => 'OBJECT',
                                            'name' => '__InputValue',
                                            'fields' =>
                                                array(
                                                    0 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'name',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'NON_NULL',
                                                                    'name' => NULL,
                                                                    'ofType' =>
                                                                        array(
                                                                            '__typename' => '__Type',
                                                                            'kind' => 'SCALAR',
                                                                            'name' => 'String',
                                                                            'ofType' => NULL,
                                                                        ),
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    1 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'description',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'SCALAR',
                                                                    'name' => 'String',
                                                                    'ofType' => NULL,
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    2 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'type',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'NON_NULL',
                                                                    'name' => NULL,
                                                                    'ofType' =>
                                                                        array(
                                                                            '__typename' => '__Type',
                                                                            'kind' => 'OBJECT',
                                                                            'name' => '__Type',
                                                                            'ofType' => NULL,
                                                                        ),
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    3 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'defaultValue',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'SCALAR',
                                                                    'name' => 'String',
                                                                    'ofType' => NULL,
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                ),
                                            'interfaces' =>
                                                array(),
                                            'enumValues' => NULL,
                                        ),
                                    8 =>
                                        array(
                                            '__typename' => '__Type',
                                            'kind' => 'OBJECT',
                                            'name' => '__EnumValue',
                                            'fields' =>
                                                array(
                                                    0 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'name',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'NON_NULL',
                                                                    'name' => NULL,
                                                                    'ofType' =>
                                                                        array(
                                                                            '__typename' => '__Type',
                                                                            'kind' => 'SCALAR',
                                                                            'name' => 'String',
                                                                            'ofType' => NULL,
                                                                        ),
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    1 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'description',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'SCALAR',
                                                                    'name' => 'String',
                                                                    'ofType' => NULL,
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    2 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'isDeprecated',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'NON_NULL',
                                                                    'name' => NULL,
                                                                    'ofType' =>
                                                                        array(
                                                                            '__typename' => '__Type',
                                                                            'kind' => 'SCALAR',
                                                                            'name' => 'Boolean',
                                                                            'ofType' => NULL,
                                                                        ),
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    3 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'deprecationReason',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'SCALAR',
                                                                    'name' => 'String',
                                                                    'ofType' => NULL,
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                ),
                                            'interfaces' =>
                                                array(),
                                            'enumValues' => NULL,
                                        ),
                                    9 =>
                                        array(
                                            '__typename' => '__Type',
                                            'kind' => 'OBJECT',
                                            'name' => '__Directive',
                                            'fields' =>
                                                array(
                                                    0 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'name',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'SCALAR',
                                                                    'name' => 'String',
                                                                    'ofType' => NULL,
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    1 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'description',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'SCALAR',
                                                                    'name' => 'String',
                                                                    'ofType' => NULL,
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    2 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'type',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'OBJECT',
                                                                    'name' => '__Type',
                                                                    'ofType' => NULL,
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    3 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'onOperation',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'SCALAR',
                                                                    'name' => 'Boolean',
                                                                    'ofType' => NULL,
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    4 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'onFragment',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'SCALAR',
                                                                    'name' => 'Boolean',
                                                                    'ofType' => NULL,
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                    5 =>
                                                        array(
                                                            '__typename' => '__Field',
                                                            'name' => 'onField',
                                                            'args' =>
                                                                array(),
                                                            'type' =>
                                                                array(
                                                                    '__typename' => '__Type',
                                                                    'kind' => 'SCALAR',
                                                                    'name' => 'Boolean',
                                                                    'ofType' => NULL,
                                                                ),
                                                            'isDeprecated' => false,
                                                            'deprecationReason' => NULL,
                                                        ),
                                                ),
                                            'interfaces' =>
                                                array(),
                                            'enumValues' => NULL,
                                        ),
                                    10 => [
                                        '__typename' => '__Type',
                                        'kind' => 'SCALAR',
                                        'name' => 'ID',
                                        'fields' => null,
                                        'interfaces' => null,
                                        'enumValues' => null,
                                    ],
                                    11 => [
                                        '__typename' => '__Type',
                                        'kind' => 'SCALAR',
                                        'name' => 'Float',
                                        'fields' => null,
                                        'interfaces' => null,
                                        'enumValues' => null,
                                    ],
                                    12 => [
                                        '__typename' => '__Type',
                                        'kind' => 'SCALAR',
                                        'name' => 'Int',
                                        'fields' => null,
                                        'interfaces' => null,
                                        'enumValues' => null,
                                    ]
                                ),
                            'directives' =>
                                array(
                                    0 =>
                                        array(
                                            '__typename' => '__Directive',
                                            'name' => 'if',
                                            'type' =>
                                                array(
                                                    '__typename' => '__Type',
                                                    'kind' => 'NON_NULL',
                                                    'name' => NULL,
                                                    'ofType' =>
                                                        array(
                                                            '__typename' => '__Type',
                                                            'kind' => 'SCALAR',
                                                            'name' => 'Boolean',
                                                            'ofType' => NULL,
                                                        ),
                                                ),
                                            'onOperation' => false,
                                            'onFragment' => false,
                                            'onField' => true,
                                        ),
                                    1 =>
                                        array(
                                            '__typename' => '__Directive',
                                            'name' => 'unless',
                                            'type' =>
                                                array(
                                                    '__typename' => '__Type',
                                                    'kind' => 'NON_NULL',
                                                    'name' => NULL,
                                                    'ofType' =>
                                                        array(
                                                            '__typename' => '__Type',
                                                            'kind' => 'SCALAR',
                                                            'name' => 'Boolean',
                                                            'ofType' => NULL,
                                                        ),
                                                ),
                                            'onOperation' => false,
                                            'onFragment' => false,
                                            'onField' => true,
                                        ),
                                ),
                        ),
                )
        );

        $this->assertEquals($expected, GraphQL::execute($emptySchema, $request));
    }

    function testIntrospectsOnInputObject()
    {
        $TestInputObject = new InputObjectType([
            'name' => 'TestInputObject',
            'fields' => [
                'a' => ['type' => Type::string(), 'defaultValue' => 'foo'],
                'b' => ['type' => Type::listOf(Type::string())]
            ]
        ]);

        $TestType = new ObjectType([
            'name' => 'TestType',
            'fields' => [
                'field' => [
                    'type' => Type::string(),
                    'args' => ['complex' => ['type' => $TestInputObject]],
                    'resolve' => function ($_, $args) {
                        return json_encode($args['complex']);
                    }
                ]
            ]
        ]);

        $schema = new Schema($TestType);
        $request = '
          {
            __schema {
              types {
                kind
                name
                inputFields {
                  name
                  type { ...TypeRef }
                  defaultValue
                }
              }
            }
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
        ';


        $expectedFragment = [
            'kind' => 'INPUT_OBJECT',
            'name' => 'TestInputObject',
            'inputFields' => [
                ['name' => 'a', 'type' => [
                    'kind' => 'SCALAR',
                    'name' => 'String',
                    'ofType' => null],
                    'defaultValue' => '"foo"'
                ],
                ['name' => 'b', 'type' => [
                    'kind' => 'LIST',
                    'name' => null,
                    'ofType' => ['kind' => 'SCALAR', 'name' => 'String', 'ofType' => null]],
                    'defaultValue' => null
                ]
            ]
        ];

        $result = GraphQL::execute($schema, $request);
        $result = $result['data']['__schema']['types'];
        // $this->assertEquals($expectedFragment, $result[1]);
        $this->assertContains($expectedFragment, $result);
    }

    public function testSupportsThe__typeRootField()
    {

        $TestType = new ObjectType([
            'name' => 'TestType',
            'fields' => [
                'testField' => [
                    'type' => Type::string(),
                ]
            ]
        ]);

        $schema = new Schema($TestType);
        $request = '
          {
            __type(name: "TestType") {
              name
            }
          }
        ';

        $expected = ['data' => [
            '__type' => [
                'name' => 'TestType'
            ]
        ]];

        $this->assertEquals($expected, GraphQL::execute($schema, $request));
    }

    public function testIdentifiesDeprecatedFields()
    {

        $TestType = new ObjectType([
            'name' => 'TestType',
            'fields' => [
                'nonDeprecated' => [
                    'type' => Type::string(),
                ],
                'deprecated' => [
                    'type' => Type::string(),
                    'deprecationReason' => 'Removed in 1.0'
                ]
            ]
        ]);

        $schema = new Schema($TestType);
        $request = '
          {
            __type(name: "TestType") {
              name
              fields(includeDeprecated: true) {
                name
                isDeprecated,
                deprecationReason
              }
            }
          }
        ';

        $expected = [
            'data' => [
                '__type' => [
                    'name' => 'TestType',
                    'fields' => [
                        [
                            'name' => 'nonDeprecated',
                            'isDeprecated' => false,
                            'deprecationReason' => null
                        ],
                        [
                            'name' => 'deprecated',
                            'isDeprecated' => true,
                            'deprecationReason' => 'Removed in 1.0'
                        ]
                    ]
                ]
            ]
        ];
        $this->assertEquals($expected, GraphQL::execute($schema, $request));
    }

    public function testRespectsTheIncludeDeprecatedParameterForFields()
    {
        $TestType = new ObjectType([
            'name' => 'TestType',
            'fields' => [
                'nonDeprecated' => [
                    'type' => Type::string(),
                ],
                'deprecated' => [
                    'type' => Type::string(),
                    'deprecationReason' => 'Removed in 1.0'
                ]
            ]
        ]);

        $schema = new Schema($TestType);
        $request = '
      {
        __type(name: "TestType") {
          name
          trueFields: fields(includeDeprecated: true) {
            name
          }
          falseFields: fields(includeDeprecated: false) {
            name
          }
          omittedFields: fields {
            name
          }
        }
      }
        ';

        $expected = [
            'data' => [
                '__type' => [
                    'name' => 'TestType',
                    'trueFields' => [
                        [
                            'name' => 'nonDeprecated',
                        ],
                        [
                            'name' => 'deprecated',
                        ]
                    ],
                    'falseFields' => [
                        [
                            'name' => 'nonDeprecated',
                        ]
                    ],
                    'omittedFields' => [
                        [
                            'name' => 'nonDeprecated',
                        ]
                    ],
                ]
            ]
        ];

        $this->assertEquals($expected, GraphQL::execute($schema, $request));
    }

    public function testIdentifiesDeprecatedEnumValues()
    {
        $TestEnum = new EnumType([
            'name' => 'TestEnum',
            'values' => [
                'NONDEPRECATED' => ['value' => 0],
                'DEPRECATED' => ['value' => 1, 'deprecationReason' => 'Removed in 1.0'],
                'ALSONONDEPRECATED' => ['value' => 2]
            ]
        ]);

        $TestType = new ObjectType([
            'name' => 'TestType',
            'fields' => [
                'testEnum' => [
                    'type' => $TestEnum,
                ],
            ]
        ]);

        $schema = new Schema($TestType);
        $request = '
          {
            __type(name: "TestEnum") {
              name
              enumValues(includeDeprecated: true) {
                name
                isDeprecated,
                deprecationReason
              }
            }
          }
        ';

        $expected = [
            'data' => [
                '__type' => [
                    'name' => 'TestEnum',
                    'enumValues' => [
                        [
                            'name' => 'NONDEPRECATED',
                            'isDeprecated' => false,
                            'deprecationReason' => null
                        ],
                        [
                            'name' => 'DEPRECATED',
                            'isDeprecated' => true,
                            'deprecationReason' => 'Removed in 1.0'
                        ],
                        [
                            'name' => 'ALSONONDEPRECATED',
                            'isDeprecated' => false,
                            'deprecationReason' => null
                        ]
                    ]
                ]
            ]
        ];
        $this->assertEquals($expected, GraphQL::execute($schema, $request));
    }

    public function testRespectsTheIncludeDeprecatedParameterForEnumValues()
    {
        $TestEnum = new EnumType([
            'name' => 'TestEnum',
            'values' => [
                'NONDEPRECATED' => ['value' => 0],
                'DEPRECATED' => ['value' => 1, 'deprecationReason' => 'Removed in 1.0'],
                'ALSONONDEPRECATED' => ['value' => 2]
            ]
        ]);

        $TestType = new ObjectType([
            'name' => 'TestType',
            'fields' => [
                'testEnum' => [
                    'type' => $TestEnum,
                ],
            ]
        ]);

        $schema = new Schema($TestType);
        $request = '
          {
            __type(name: "TestEnum") {
              name
              trueValues: enumValues(includeDeprecated: true) {
                name
              }
              falseValues: enumValues(includeDeprecated: false) {
                name
              }
              omittedValues: enumValues {
                name
              }
            }
          }
        ';
        $expected = [
            'data' => [
                '__type' => [
                    'name' => 'TestEnum',
                    'trueValues' => [
                        ['name' => 'NONDEPRECATED'],
                        ['name' => 'DEPRECATED'],
                        ['name' => 'ALSONONDEPRECATED']
                    ],
                    'falseValues' => [
                        ['name' => 'NONDEPRECATED'],
                        ['name' => 'ALSONONDEPRECATED']
                    ],
                    'omittedValues' => [
                        ['name' => 'NONDEPRECATED'],
                        ['name' => 'ALSONONDEPRECATED']
                    ],
                ]
            ]
        ];
        $this->assertEquals($expected, GraphQL::execute($schema, $request));
    }

    public function testFailsAsExpectedOnThe__typeRootFieldWithoutAnArg()
    {

        $TestType = new ObjectType([
            'name' => 'TestType',
            'fields' => [
                'testField' => [
                    'type' => Type::string(),
                ]
            ]
        ]);

        $schema = new Schema($TestType);
        $request = '
      {
        __type {
          name
        }
      }
    ';
        $expected = [
            'errors' => [
                new FormattedError(Messages::missingArgMessage('__type', 'name', 'String!'), [new SourceLocation(3, 9)])
            ]
        ];
        $this->assertEquals($expected, GraphQL::execute($schema, $request));
    }

    public function testExposesDescriptionsOnTypesAndFields()
    {
        $QueryRoot = new ObjectType([
            'name' => 'QueryRoot',
            'fields' => []
        ]);

        $schema = new Schema($QueryRoot);
        $request = '
      {
        schemaType: __type(name: "__Schema") {
          name,
          description,
          fields {
            name,
            description
          }
        }
      }
        ';
        $expected = [
            'data' => [
                'schemaType' => [
                    'name' => '__Schema',
                    'description' => 'A GraphQL Schema defines the capabilities of a ' .
                        'GraphQL server. It exposes all available types and ' .
                        'directives on the server, as well as the entry ' .
                        'points for query and mutation operations.',
                    'fields' => [
                        [
                            'name' => 'types',
                            'description' => 'A list of all types supported by this server.'
                        ],
                        [
                            'name' => 'queryType',
                            'description' => 'The type that query operations will be rooted at.'
                        ],
                        [
                            'name' => 'mutationType',
                            'description' => 'If this server supports mutation, the type that ' .
                                'mutation operations will be rooted at.'
                        ],
                        [
                            'name' => 'directives',
                            'description' => 'A list of all directives supported by this server.'
                        ]
                    ]
                ]
            ]
        ];
        $this->assertEquals($expected, GraphQL::execute($schema, $request));
    }

    public function testExposesDescriptionsOnEnums()
    {
        $QueryRoot = new ObjectType([
            'name' => 'QueryRoot',
            'fields' => []
        ]);

        $schema = new Schema($QueryRoot);
        $request = '
      {
        typeKindType: __type(name: "__TypeKind") {
          name,
          description,
          enumValues {
            name,
            description
          }
        }
      }
    ';
        $expected = [
            'data' => [
                'typeKindType' => [
                    'name' => '__TypeKind',
                    'description' => 'An enum describing what kind of type a given __Type is',
                    'enumValues' => [
                        [
                            'description' => 'Indicates this type is a scalar.',
                            'name' => 'SCALAR'
                        ],
                        [
                            'description' => 'Indicates this type is an object. ' .
                                '`fields` and `interfaces` are valid fields.',
                            'name' => 'OBJECT'
                        ],
                        [
                            'description' => 'Indicates this type is an interface. ' .
                                '`fields` and `possibleTypes` are valid fields.',
                            'name' => 'INTERFACE'
                        ],
                        [
                            'description' => 'Indicates this type is a union. ' .
                                '`possibleTypes` is a valid field.',
                            'name' => 'UNION'
                        ],
                        [
                            'description' => 'Indicates this type is an enum. ' .
                                '`enumValues` is a valid field.',
                            'name' => 'ENUM'
                        ],
                        [
                            'description' => 'Indicates this type is an input object. ' .
                                '`inputFields` is a valid field.',
                            'name' => 'INPUT_OBJECT'
                        ],
                        [
                            'description' => 'Indicates this type is a list. ' .
                                '`ofType` is a valid field.',
                            'name' => 'LIST'
                        ],
                        [
                            'description' => 'Indicates this type is a non-null. ' .
                                '`ofType` is a valid field.',
                            'name' => 'NON_NULL'
                        ]
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, GraphQL::execute($schema, $request));
    }
}
