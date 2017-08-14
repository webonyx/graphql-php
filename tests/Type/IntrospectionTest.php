<?php
namespace GraphQL\Tests\Type;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Schema;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Introspection;
use GraphQL\Validator\Rules\ProvidedNonNullArguments;

class IntrospectionTest extends \PHPUnit_Framework_TestCase
{
    // Describe: Introspection

    /**
     * @it executes an introspection query
     */
    function testExecutesAnIntrospectionQuery()
    {
        $emptySchema = new Schema([
            'query' => new ObjectType([
                'name' => 'QueryRoot',
                'fields' => ['a' => Type::string()]
            ])
        ]);

        $request = Introspection::getIntrospectionQuery(false);
        $expected = array (
            'data' =>
                array (
                    '__schema' =>
                        array (
                            'mutationType' => NULL,
                            'subscriptionType' => NULL,
                            'queryType' =>
                                array (
                                    'name' => 'QueryRoot',
                                ),
                            'types' =>
                                array (
                                    array (
                                        'kind' => 'SCALAR',
                                        'name' => 'ID',
                                        'fields' => NULL,
                                        'inputFields' => NULL,
                                        'interfaces' => NULL,
                                        'enumValues' => NULL,
                                        'possibleTypes' => NULL,
                                    ),
                                    array (
                                        'kind' => 'SCALAR',
                                        'name' => 'String',
                                        'fields' => NULL,
                                        'inputFields' => NULL,
                                        'interfaces' => NULL,
                                        'enumValues' => NULL,
                                        'possibleTypes' => NULL,
                                    ),
                                    array (
                                        'kind' => 'SCALAR',
                                        'name' => 'Float',
                                        'fields' => NULL,
                                        'inputFields' => NULL,
                                        'interfaces' => NULL,
                                        'enumValues' => NULL,
                                        'possibleTypes' => NULL,
                                    ),
                                    array (
                                        'kind' => 'SCALAR',
                                        'name' => 'Int',
                                        'fields' => NULL,
                                        'inputFields' => NULL,
                                        'interfaces' => NULL,
                                        'enumValues' => NULL,
                                        'possibleTypes' => NULL,
                                    ),
                                    array (
                                        'kind' => 'SCALAR',
                                        'name' => 'Boolean',
                                        'fields' => NULL,
                                        'inputFields' => NULL,
                                        'interfaces' => NULL,
                                        'enumValues' => NULL,
                                        'possibleTypes' => NULL,
                                    ),
                                    array (
                                        'kind' => 'OBJECT',
                                        'name' => '__Schema',
                                        'fields' =>
                                            array (
                                                0 =>
                                                    array (
                                                        'name' => 'types',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'NON_NULL',
                                                                'name' => NULL,
                                                                'ofType' =>
                                                                    array (
                                                                        'kind' => 'LIST',
                                                                        'name' => NULL,
                                                                        'ofType' =>
                                                                            array (
                                                                                'kind' => 'NON_NULL',
                                                                                'name' => NULL,
                                                                                'ofType' =>
                                                                                    array (
                                                                                        'kind' => 'OBJECT',
                                                                                        'name' => '__Type'
                                                                                    ),
                                                                            ),
                                                                    ),
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                1 =>
                                                    array (
                                                        'name' => 'queryType',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'NON_NULL',
                                                                'name' => NULL,
                                                                'ofType' =>
                                                                    array (
                                                                        'kind' => 'OBJECT',
                                                                        'name' => '__Type',
                                                                    ),
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                array (
                                                    'name' => 'mutationType',
                                                    'args' =>
                                                        array (
                                                        ),
                                                    'type' =>
                                                        array (
                                                            'kind' => 'OBJECT',
                                                            'name' => '__Type',
                                                        ),
                                                    'isDeprecated' => false,
                                                    'deprecationReason' => NULL,
                                                ),
                                                array (
                                                    'name' => 'subscriptionType',
                                                    'args' =>
                                                        array (
                                                        ),
                                                    'type' =>
                                                        array (
                                                            'kind' => 'OBJECT',
                                                            'name' => '__Type',
                                                        ),
                                                    'isDeprecated' => false,
                                                    'deprecationReason' => NULL,
                                                ),
                                                array (
                                                    'name' => 'directives',
                                                    'args' =>
                                                        array (
                                                        ),
                                                    'type' =>
                                                        array (
                                                            'kind' => 'NON_NULL',
                                                            'name' => NULL,
                                                            'ofType' =>
                                                                array (
                                                                    'kind' => 'LIST',
                                                                    'name' => NULL,
                                                                    'ofType' =>
                                                                        array (
                                                                            'kind' => 'NON_NULL',
                                                                            'name' => NULL,
                                                                            'ofType' =>
                                                                                array (
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
                                        'inputFields' => NULL,
                                        'interfaces' =>
                                            array (
                                            ),
                                        'enumValues' => NULL,
                                        'possibleTypes' => NULL,
                                    ),
                                    array (
                                        'kind' => 'OBJECT',
                                        'name' => '__Type',
                                        'fields' =>
                                            array (
                                                0 =>
                                                    array (
                                                        'name' => 'kind',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'NON_NULL',
                                                                'name' => NULL,
                                                                'ofType' =>
                                                                    array (
                                                                        'kind' => 'ENUM',
                                                                        'name' => '__TypeKind',
                                                                    ),
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                1 =>
                                                    array (
                                                        'name' => 'name',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'SCALAR',
                                                                'name' => 'String',
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                2 =>
                                                    array (
                                                        'name' => 'description',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'SCALAR',
                                                                'name' => 'String',
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                3 =>
                                                    array (
                                                        'name' => 'fields',
                                                        'args' =>
                                                            array (
                                                                0 =>
                                                                    array (
                                                                        'name' => 'includeDeprecated',
                                                                        'type' =>
                                                                            array (
                                                                                'kind' => 'SCALAR',
                                                                                'name' => 'Boolean',
                                                                            ),
                                                                        'defaultValue' => 'false',
                                                                    ),
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'LIST',
                                                                'name' => NULL,
                                                                'ofType' =>
                                                                    array (
                                                                        'kind' => 'NON_NULL',
                                                                        'name' => NULL,
                                                                        'ofType' =>
                                                                            array (
                                                                                'kind' => 'OBJECT',
                                                                                'name' => '__Field',
                                                                            ),
                                                                    ),
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                4 =>
                                                    array (
                                                        'name' => 'interfaces',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'LIST',
                                                                'name' => NULL,
                                                                'ofType' =>
                                                                    array (
                                                                        'kind' => 'NON_NULL',
                                                                        'name' => NULL,
                                                                        'ofType' =>
                                                                            array (
                                                                                'kind' => 'OBJECT',
                                                                                'name' => '__Type',
                                                                            ),
                                                                    ),
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                5 =>
                                                    array (
                                                        'name' => 'possibleTypes',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'LIST',
                                                                'name' => NULL,
                                                                'ofType' =>
                                                                    array (
                                                                        'kind' => 'NON_NULL',
                                                                        'name' => NULL,
                                                                        'ofType' =>
                                                                            array (
                                                                                'kind' => 'OBJECT',
                                                                                'name' => '__Type',
                                                                            ),
                                                                    ),
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                6 =>
                                                    array (
                                                        'name' => 'enumValues',
                                                        'args' =>
                                                            array (
                                                                0 =>
                                                                    array (
                                                                        'name' => 'includeDeprecated',
                                                                        'type' =>
                                                                            array (
                                                                                'kind' => 'SCALAR',
                                                                                'name' => 'Boolean',
                                                                            ),
                                                                        'defaultValue' => 'false',
                                                                    ),
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'LIST',
                                                                'name' => NULL,
                                                                'ofType' =>
                                                                    array (
                                                                        'kind' => 'NON_NULL',
                                                                        'name' => NULL,
                                                                        'ofType' =>
                                                                            array (
                                                                                'kind' => 'OBJECT',
                                                                                'name' => '__EnumValue',
                                                                            ),
                                                                    ),
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                7 =>
                                                    array (
                                                        'name' => 'inputFields',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'LIST',
                                                                'name' => NULL,
                                                                'ofType' =>
                                                                    array (
                                                                        'kind' => 'NON_NULL',
                                                                        'name' => NULL,
                                                                        'ofType' =>
                                                                            array (
                                                                                'kind' => 'OBJECT',
                                                                                'name' => '__InputValue',
                                                                            ),
                                                                    ),
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                8 =>
                                                    array (
                                                        'name' => 'ofType',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'OBJECT',
                                                                'name' => '__Type',
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                            ),
                                        'inputFields' => NULL,
                                        'interfaces' =>
                                            array (
                                            ),
                                        'enumValues' => NULL,
                                        'possibleTypes' => NULL,
                                    ),
                                    array (
                                        'kind' => 'ENUM',
                                        'name' => '__TypeKind',
                                        'fields' => NULL,
                                        'inputFields' => NULL,
                                        'interfaces' => NULL,
                                        'enumValues' =>
                                            array (
                                                0 =>
                                                    array (
                                                        'name' => 'SCALAR',
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                1 =>
                                                    array (
                                                        'name' => 'OBJECT',
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                2 =>
                                                    array (
                                                        'name' => 'INTERFACE',
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                3 =>
                                                    array (
                                                        'name' => 'UNION',
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                4 =>
                                                    array (
                                                        'name' => 'ENUM',
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                5 =>
                                                    array (
                                                        'name' => 'INPUT_OBJECT',
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                6 =>
                                                    array (
                                                        'name' => 'LIST',
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                7 =>
                                                    array (
                                                        'name' => 'NON_NULL',
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                            ),
                                        'possibleTypes' => NULL,
                                    ),
                                    array (
                                        'kind' => 'OBJECT',
                                        'name' => '__Field',
                                        'fields' =>
                                            array (
                                                0 =>
                                                    array (
                                                        'name' => 'name',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'NON_NULL',
                                                                'name' => NULL,
                                                                'ofType' =>
                                                                    array (
                                                                        'kind' => 'SCALAR',
                                                                        'name' => 'String',
                                                                    ),
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                1 =>
                                                    array (
                                                        'name' => 'description',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'SCALAR',
                                                                'name' => 'String',
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                2 =>
                                                    array (
                                                        'name' => 'args',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'NON_NULL',
                                                                'name' => NULL,
                                                                'ofType' =>
                                                                    array (
                                                                        'kind' => 'LIST',
                                                                        'name' => NULL,
                                                                        'ofType' =>
                                                                            array (
                                                                                'kind' => 'NON_NULL',
                                                                                'name' => NULL,
                                                                                'ofType' =>
                                                                                    array (
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
                                                    array (
                                                        'name' => 'type',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'NON_NULL',
                                                                'name' => NULL,
                                                                'ofType' =>
                                                                    array (
                                                                        'kind' => 'OBJECT',
                                                                        'name' => '__Type',
                                                                    ),
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                4 =>
                                                    array (
                                                        'name' => 'isDeprecated',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'NON_NULL',
                                                                'name' => NULL,
                                                                'ofType' =>
                                                                    array (
                                                                        'kind' => 'SCALAR',
                                                                        'name' => 'Boolean',
                                                                    ),
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                5 =>
                                                    array (
                                                        'name' => 'deprecationReason',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'SCALAR',
                                                                'name' => 'String',
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                            ),
                                        'inputFields' => NULL,
                                        'interfaces' =>
                                            array (
                                            ),
                                        'enumValues' => NULL,
                                        'possibleTypes' => NULL,
                                    ),
                                    array (
                                        'kind' => 'OBJECT',
                                        'name' => '__InputValue',
                                        'fields' =>
                                            array (
                                                0 =>
                                                    array (
                                                        'name' => 'name',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'NON_NULL',
                                                                'name' => NULL,
                                                                'ofType' =>
                                                                    array (
                                                                        'kind' => 'SCALAR',
                                                                        'name' => 'String',
                                                                    ),
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                1 =>
                                                    array (
                                                        'name' => 'description',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'SCALAR',
                                                                'name' => 'String',
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                2 =>
                                                    array (
                                                        'name' => 'type',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'NON_NULL',
                                                                'name' => NULL,
                                                                'ofType' =>
                                                                    array (
                                                                        'kind' => 'OBJECT',
                                                                        'name' => '__Type',
                                                                    ),
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                3 =>
                                                    array (
                                                        'name' => 'defaultValue',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'SCALAR',
                                                                'name' => 'String',
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                            ),
                                        'inputFields' => NULL,
                                        'interfaces' =>
                                            array (
                                            ),
                                        'enumValues' => NULL,
                                        'possibleTypes' => NULL,
                                    ),
                                    array (
                                        'kind' => 'OBJECT',
                                        'name' => '__EnumValue',
                                        'fields' =>
                                            array (
                                                0 =>
                                                    array (
                                                        'name' => 'name',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'NON_NULL',
                                                                'name' => NULL,
                                                                'ofType' =>
                                                                    array (
                                                                        'kind' => 'SCALAR',
                                                                        'name' => 'String',
                                                                    ),
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                1 =>
                                                    array (
                                                        'name' => 'description',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'SCALAR',
                                                                'name' => 'String',
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                2 =>
                                                    array (
                                                        'name' => 'isDeprecated',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'NON_NULL',
                                                                'name' => NULL,
                                                                'ofType' =>
                                                                    array (
                                                                        'kind' => 'SCALAR',
                                                                        'name' => 'Boolean',
                                                                    ),
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                3 =>
                                                    array (
                                                        'name' => 'deprecationReason',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'SCALAR',
                                                                'name' => 'String',
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                            ),
                                        'inputFields' => NULL,
                                        'interfaces' =>
                                            array (
                                            ),
                                        'enumValues' => NULL,
                                        'possibleTypes' => NULL,
                                    ),
                                    array (
                                        'kind' => 'OBJECT',
                                        'name' => '__Directive',
                                        'fields' =>
                                            array (
                                                0 =>
                                                    array (
                                                        'name' => 'name',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'NON_NULL',
                                                                'name' => NULL,
                                                                'ofType' =>
                                                                    array (
                                                                        'kind' => 'SCALAR',
                                                                        'name' => 'String',
                                                                    ),
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                1 =>
                                                    array (
                                                        'name' => 'description',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'SCALAR',
                                                                'name' => 'String',
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                2 =>
                                                    array (
                                                        'name' => 'locations',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'NON_NULL',
                                                                'name' => NULL,
                                                                'ofType' =>
                                                                    array (
                                                                        'kind' => 'LIST',
                                                                        'name' => NULL,
                                                                        'ofType' =>
                                                                            array (
                                                                                'kind' => 'NON_NULL',
                                                                                'name' => NULL,
                                                                                'ofType' =>
                                                                                    array (
                                                                                        'kind' => 'ENUM',
                                                                                        'name' => '__DirectiveLocation',
                                                                                    ),
                                                                            ),
                                                                    ),
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                3 =>
                                                    array (
                                                        'name' => 'args',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'NON_NULL',
                                                                'name' => NULL,
                                                                'ofType' =>
                                                                    array (
                                                                        'kind' => 'LIST',
                                                                        'name' => NULL,
                                                                        'ofType' =>
                                                                            array (
                                                                                'kind' => 'NON_NULL',
                                                                                'name' => NULL,
                                                                                'ofType' =>
                                                                                    array (
                                                                                        'kind' => 'OBJECT',
                                                                                        'name' => '__InputValue',
                                                                                    ),
                                                                            ),
                                                                    ),
                                                            ),
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => NULL,
                                                    ),
                                                4 =>
                                                    array (
                                                        'name' => 'onOperation',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'NON_NULL',
                                                                'name' => NULL,
                                                                'ofType' =>
                                                                    array (
                                                                        'kind' => 'SCALAR',
                                                                        'name' => 'Boolean',
                                                                    ),
                                                            ),
                                                        'isDeprecated' => true,
                                                        'deprecationReason' => 'Use `locations`.',
                                                    ),
                                                5 =>
                                                    array (
                                                        'name' => 'onFragment',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'NON_NULL',
                                                                'name' => NULL,
                                                                'ofType' =>
                                                                    array (
                                                                        'kind' => 'SCALAR',
                                                                        'name' => 'Boolean',
                                                                    ),
                                                            ),
                                                        'isDeprecated' => true,
                                                        'deprecationReason' => 'Use `locations`.',
                                                    ),
                                                6 =>
                                                    array (
                                                        'name' => 'onField',
                                                        'args' =>
                                                            array (
                                                            ),
                                                        'type' =>
                                                            array (
                                                                'kind' => 'NON_NULL',
                                                                'name' => NULL,
                                                                'ofType' =>
                                                                    array (
                                                                        'kind' => 'SCALAR',
                                                                        'name' => 'Boolean',

                                                                    ),
                                                            ),
                                                        'isDeprecated' => true,
                                                        'deprecationReason' => 'Use `locations`.',
                                                    ),
                                            ),
                                        'inputFields' => NULL,
                                        'interfaces' =>
                                            array (
                                            ),
                                        'enumValues' => NULL,
                                        'possibleTypes' => NULL,
                                    ),
                                    array (
                                        'kind' => 'ENUM',
                                        'name' => '__DirectiveLocation',
                                        'fields' => NULL,
                                        'inputFields' => NULL,
                                        'interfaces' => NULL,
                                        'enumValues' =>
                                            array (
                                                0 =>
                                                    array (
                                                        'name' => 'QUERY',
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => null
                                                    ),
                                                1 =>
                                                    array (
                                                        'name' => 'MUTATION',
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => null
                                                    ),
                                                2 =>
                                                    array (
                                                        'name' => 'SUBSCRIPTION',
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => null
                                                    ),
                                                3 =>
                                                    array (
                                                        'name' => 'FIELD',
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => null
                                                    ),
                                                4 =>
                                                    array (
                                                        'name' => 'FRAGMENT_DEFINITION',
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => null
                                                    ),
                                                5 =>
                                                    array (
                                                        'name' => 'FRAGMENT_SPREAD',
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => null
                                                    ),
                                                6 =>
                                                    array (
                                                        'name' => 'INLINE_FRAGMENT',
                                                        'isDeprecated' => false,
                                                        'deprecationReason' => null
                                                    ),
                                            ),
                                        'possibleTypes' => NULL,
                                    ),
                                    array (
                                        'kind' => 'OBJECT',
                                        'name' => 'QueryRoot',
                                        'inputFields' => NULL,
                                        'interfaces' =>
                                            array (
                                            ),
                                        'enumValues' => NULL,
                                        'possibleTypes' => NULL,
                                        'fields' => array (
                                            array (
                                                'name' => 'a',
                                                'args' => array(),
                                                'type' => array(
                                                    'kind' => 'SCALAR',
                                                    'name' => 'String',
                                                    'ofType' => null
                                                ),
                                                'isDeprecated' => false,
                                                'deprecationReason' => null,
                                            )
                                        )
                                    ),
                                ),
                            'directives' =>
                                array (
                                    0 =>
                                        array (
                                            'name' => 'include',
                                            'locations' =>
                                                array (
                                                    0 => 'FIELD',
                                                    1 => 'FRAGMENT_SPREAD',
                                                    2 => 'INLINE_FRAGMENT',
                                                ),
                                            'args' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'defaultValue' => NULL,
                                                            'name' => 'if',
                                                            'type' =>
                                                                array (
                                                                    'kind' => 'NON_NULL',
                                                                    'name' => NULL,
                                                                    'ofType' =>
                                                                        array (
                                                                            'kind' => 'SCALAR',
                                                                            'name' => 'Boolean',
                                                                        ),
                                                                ),
                                                        ),
                                                ),
                                        ),
                                    1 =>
                                        array (
                                            'name' => 'skip',
                                            'locations' =>
                                                array (
                                                    0 => 'FIELD',
                                                    1 => 'FRAGMENT_SPREAD',
                                                    2 => 'INLINE_FRAGMENT',
                                                ),
                                            'args' =>
                                                array (
                                                    0 =>
                                                        array (
                                                            'defaultValue' => NULL,
                                                            'name' => 'if',
                                                            'type' =>
                                                                array (
                                                                    'kind' => 'NON_NULL',
                                                                    'name' => NULL,
                                                                    'ofType' =>
                                                                        array (
                                                                            'kind' => 'SCALAR',
                                                                            'name' => 'Boolean',
                                                                        ),
                                                                ),
                                                        ),
                                                ),
                                        ),
                                ),
                        ),
                )
        );

        $actual = GraphQL::execute($emptySchema, $request);

        // $this->assertEquals($expected, $actual);
        $this->assertArraySubset($expected, $actual);
    }

    /**
     * @it introspects on input object
     */
    function testIntrospectsOnInputObject()
    {
        $TestInputObject = new InputObjectType([
            'name' => 'TestInputObject',
            'fields' => [
                'a' => ['type' => Type::string(), 'defaultValue' => 'foo'],
                'b' => ['type' => Type::listOf(Type::string())],
                'c' => ['type' => Type::string(), 'defaultValue' => null ]
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

        $schema = new Schema(['query' => $TestType]);
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
                [
                    'name' => 'a',
                    'type' => [
                        'kind' => 'SCALAR',
                        'name' => 'String',
                        'ofType' => null
                    ],
                    'defaultValue' => '"foo"'
                ],
                [
                    'name' => 'b',
                    'type' => [
                        'kind' => 'LIST',
                        'name' => null,
                        'ofType' => ['kind' => 'SCALAR', 'name' => 'String', 'ofType' => null]
                    ],
                    'defaultValue' => null
                ],
                [
                    'name' => 'c',
                    'type' => [
                        'kind' => 'SCALAR',
                        'name' => 'String',
                        'ofType' => null
                    ],
                    'defaultValue' => 'null' // defaultValue was set (even if it was set to null)
                ]
            ]
        ];

        $result = GraphQL::execute($schema, $request);
        $result = $result['data']['__schema']['types'];
        // $this->assertEquals($expectedFragment, $result[1]);
        $this->assertContains($expectedFragment, $result);
    }

    /**
     * @it supports the __type root field
     */
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

        $schema = new Schema(['query' => $TestType]);
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

    /**
     * @it identifies deprecated fields
     */
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

        $schema = new Schema(['query' => $TestType]);
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

    /**
     * @it respects the includeDeprecated parameter for fields
     */
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

        $schema = new Schema(['query' => $TestType]);
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

    /**
     * @it identifies deprecated enum values
     */
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

        $schema = new Schema(['query' => $TestType]);
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

    /**
     * @it respects the includeDeprecated parameter for enum values
     */
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

        $schema = new Schema(['query' => $TestType]);
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

    /**
     * @it fails as expected on the __type root field without an arg
     */
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

        $schema = new Schema(['query' => $TestType]);
        $request = '
      {
        __type {
          name
        }
      }
    ';
        $expected = [
            'errors' => [
                FormattedError::create(
                    ProvidedNonNullArguments::missingFieldArgMessage('__type', 'name', 'String!'), [new SourceLocation(3, 9)]
                )
            ]
        ];
        $this->assertArraySubset($expected, GraphQL::execute($schema, $request));
    }

    /**
     * @it exposes descriptions on types and fields
     */
    public function testExposesDescriptionsOnTypesAndFields()
    {
        $QueryRoot = new ObjectType([
            'name' => 'QueryRoot',
            'fields' => ['a' => Type::string()]
        ]);

        $schema = new Schema(['query' => $QueryRoot]);
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
                        'points for query, mutation, and subscription operations.',
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
                            'name' => 'subscriptionType',
                            'description' => 'If this server support subscription, the type that subscription operations will be rooted at.'
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

    /**
     * @it exposes descriptions on enums
     */
    public function testExposesDescriptionsOnEnums()
    {
        $QueryRoot = new ObjectType([
            'name' => 'QueryRoot',
            'fields' => ['a' => Type::string()]
        ]);

        $schema = new Schema(['query' => $QueryRoot]);
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
                    'description' => 'An enum describing what kind of type a given `__Type` is.',
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
