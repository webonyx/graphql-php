<?php

declare(strict_types=1);

namespace GraphQL\Tests\Type;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GraphQL\GraphQL;
use GraphQL\Language\SourceLocation;
use GraphQL\Tests\ErrorHelper;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;
use GraphQL\Validator\Rules\ProvidedRequiredArguments;
use PHPUnit\Framework\TestCase;

use function json_encode;
use function sprintf;

class IntrospectionTest extends TestCase
{
    use ArraySubsetAsserts;

    /**
     * @see it('executes an introspection query')
     */
    public function testExecutesAnIntrospectionQuery(): void
    {
        $emptySchema = new Schema([]);

        $request  = Introspection::getIntrospectionQuery([
            'descriptions' => false,
            'directiveIsRepeatable' => true,
        ]);
        $expected = [
            'data' =>
                [
                    '__schema' =>
                        [
                            'mutationType'     => null,
                            'subscriptionType' => null,
                            'queryType'        => null,
                            'types'            =>
                                [
                                    [
                                        'kind'          => 'SCALAR',
                                        'name'          => 'String',
                                        'fields'        => null,
                                        'inputFields'   => null,
                                        'interfaces'    => null,
                                        'enumValues'    => null,
                                        'possibleTypes' => null,
                                    ],
                                    [
                                        'kind'          => 'SCALAR',
                                        'name'          => 'ID',
                                        'fields'        => null,
                                        'inputFields'   => null,
                                        'interfaces'    => null,
                                        'enumValues'    => null,
                                        'possibleTypes' => null,
                                    ],
                                    [
                                        'kind'          => 'SCALAR',
                                        'name'          => 'Float',
                                        'fields'        => null,
                                        'inputFields'   => null,
                                        'interfaces'    => null,
                                        'enumValues'    => null,
                                        'possibleTypes' => null,
                                    ],
                                    [
                                        'kind'          => 'SCALAR',
                                        'name'          => 'Int',
                                        'fields'        => null,
                                        'inputFields'   => null,
                                        'interfaces'    => null,
                                        'enumValues'    => null,
                                        'possibleTypes' => null,
                                    ],
                                    [
                                        'kind'          => 'SCALAR',
                                        'name'          => 'Boolean',
                                        'fields'        => null,
                                        'inputFields'   => null,
                                        'interfaces'    => null,
                                        'enumValues'    => null,
                                        'possibleTypes' => null,
                                    ],
                                    [
                                        'kind'          => 'OBJECT',
                                        'name'          => '__Schema',
                                        'fields'        =>
                                            [
                                                0 => [
                                                    'name'              => 'types',
                                                    'args'              =>
                                                            [],
                                                    'type'              =>
                                                            [
                                                                'kind'   => 'NON_NULL',
                                                                'name'   => null,
                                                                'ofType' =>
                                                                    [
                                                                        'kind'   => 'LIST',
                                                                        'name'   => null,
                                                                        'ofType' =>
                                                                            [
                                                                                'kind'   => 'NON_NULL',
                                                                                'name'   => null,
                                                                                'ofType' =>
                                                                                    [
                                                                                        'kind' => 'OBJECT',
                                                                                        'name' => '__Type',
                                                                                    ],
                                                                            ],
                                                                    ],
                                                            ],
                                                    'isDeprecated'      => false,
                                                    'deprecationReason' => null,
                                                ],
                                                1 => [
                                                    'name'              => 'queryType',
                                                    'args'              =>
                                                            [],
                                                    'type'              =>
                                                            [
                                                                'kind'   => 'NON_NULL',
                                                                'name'   => null,
                                                                'ofType' =>
                                                                    [
                                                                        'kind' => 'OBJECT',
                                                                        'name' => '__Type',
                                                                    ],
                                                            ],
                                                    'isDeprecated'      => false,
                                                    'deprecationReason' => null,
                                                ],
                                                [
                                                    'name'              => 'mutationType',
                                                    'args'              =>
                                                        [],
                                                    'type'              =>
                                                        [
                                                            'kind' => 'OBJECT',
                                                            'name' => '__Type',
                                                        ],
                                                    'isDeprecated'      => false,
                                                    'deprecationReason' => null,
                                                ],
                                                [
                                                    'name'              => 'subscriptionType',
                                                    'args'              =>
                                                        [],
                                                    'type'              =>
                                                        [
                                                            'kind' => 'OBJECT',
                                                            'name' => '__Type',
                                                        ],
                                                    'isDeprecated'      => false,
                                                    'deprecationReason' => null,
                                                ],
                                                [
                                                    'name'              => 'directives',
                                                    'args'              =>
                                                        [],
                                                    'type'              =>
                                                        [
                                                            'kind'   => 'NON_NULL',
                                                            'name'   => null,
                                                            'ofType' =>
                                                                [
                                                                    'kind'   => 'LIST',
                                                                    'name'   => null,
                                                                    'ofType' =>
                                                                        [
                                                                            'kind'   => 'NON_NULL',
                                                                            'name'   => null,
                                                                            'ofType' =>
                                                                                [
                                                                                    'kind' => 'OBJECT',
                                                                                    'name' => '__Directive',
                                                                                ],
                                                                        ],
                                                                ],
                                                        ],
                                                    'isDeprecated'      => false,
                                                    'deprecationReason' => null,
                                                ],
                                            ],
                                        'inputFields'   => null,
                                        'interfaces'    =>
                                            [],
                                        'enumValues'    => null,
                                        'possibleTypes' => null,
                                    ],
                                    [
                                        'kind'          => 'OBJECT',
                                        'name'          => '__Type',
                                        'fields'        =>
                                            [
                                                0 =>
                                                    [
                                                        'name'              => 'kind',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind'   => 'NON_NULL',
                                                                'name'   => null,
                                                                'ofType' =>
                                                                    [
                                                                        'kind' => 'ENUM',
                                                                        'name' => '__TypeKind',
                                                                    ],
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                1 =>
                                                    [
                                                        'name'              => 'name',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind' => 'SCALAR',
                                                                'name' => 'String',
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                2 =>
                                                    [
                                                        'name'              => 'description',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind' => 'SCALAR',
                                                                'name' => 'String',
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                3 =>
                                                    [
                                                        'name'              => 'fields',
                                                        'args'              =>
                                                            [
                                                                0 =>
                                                                    [
                                                                        'name'         => 'includeDeprecated',
                                                                        'type'         =>
                                                                            [
                                                                                'kind' => 'SCALAR',
                                                                                'name' => 'Boolean',
                                                                            ],
                                                                        'defaultValue' => 'false',
                                                                    ],
                                                            ],
                                                        'type'              =>
                                                            [
                                                                'kind'   => 'LIST',
                                                                'name'   => null,
                                                                'ofType' =>
                                                                    [
                                                                        'kind'   => 'NON_NULL',
                                                                        'name'   => null,
                                                                        'ofType' =>
                                                                            [
                                                                                'kind' => 'OBJECT',
                                                                                'name' => '__Field',
                                                                            ],
                                                                    ],
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                4 =>
                                                    [
                                                        'name'              => 'interfaces',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind'   => 'LIST',
                                                                'name'   => null,
                                                                'ofType' =>
                                                                    [
                                                                        'kind'   => 'NON_NULL',
                                                                        'name'   => null,
                                                                        'ofType' =>
                                                                            [
                                                                                'kind' => 'OBJECT',
                                                                                'name' => '__Type',
                                                                            ],
                                                                    ],
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                5 =>
                                                    [
                                                        'name'              => 'possibleTypes',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind'   => 'LIST',
                                                                'name'   => null,
                                                                'ofType' =>
                                                                    [
                                                                        'kind'   => 'NON_NULL',
                                                                        'name'   => null,
                                                                        'ofType' =>
                                                                            [
                                                                                'kind' => 'OBJECT',
                                                                                'name' => '__Type',
                                                                            ],
                                                                    ],
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                6 =>
                                                    [
                                                        'name'              => 'enumValues',
                                                        'args'              =>
                                                            [
                                                                0 =>
                                                                    [
                                                                        'name'         => 'includeDeprecated',
                                                                        'type'         =>
                                                                            [
                                                                                'kind' => 'SCALAR',
                                                                                'name' => 'Boolean',
                                                                            ],
                                                                        'defaultValue' => 'false',
                                                                    ],
                                                            ],
                                                        'type'              =>
                                                            [
                                                                'kind'   => 'LIST',
                                                                'name'   => null,
                                                                'ofType' =>
                                                                    [
                                                                        'kind'   => 'NON_NULL',
                                                                        'name'   => null,
                                                                        'ofType' =>
                                                                            [
                                                                                'kind' => 'OBJECT',
                                                                                'name' => '__EnumValue',
                                                                            ],
                                                                    ],
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                7 =>
                                                    [
                                                        'name'              => 'inputFields',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind'   => 'LIST',
                                                                'name'   => null,
                                                                'ofType' =>
                                                                    [
                                                                        'kind'   => 'NON_NULL',
                                                                        'name'   => null,
                                                                        'ofType' =>
                                                                            [
                                                                                'kind' => 'OBJECT',
                                                                                'name' => '__InputValue',
                                                                            ],
                                                                    ],
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                8 =>
                                                    [
                                                        'name'              => 'ofType',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind' => 'OBJECT',
                                                                'name' => '__Type',
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                            ],
                                        'inputFields'   => null,
                                        'interfaces'    =>
                                            [],
                                        'enumValues'    => null,
                                        'possibleTypes' => null,
                                    ],
                                    [
                                        'kind'          => 'ENUM',
                                        'name'          => '__TypeKind',
                                        'fields'        => null,
                                        'inputFields'   => null,
                                        'interfaces'    => null,
                                        'enumValues'    =>
                                            [
                                                0 =>
                                                    [
                                                        'name'              => 'SCALAR',
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                1 =>
                                                    [
                                                        'name'              => 'OBJECT',
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                2 =>
                                                    [
                                                        'name'              => 'INTERFACE',
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                3 =>
                                                    [
                                                        'name'              => 'UNION',
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                4 =>
                                                    [
                                                        'name'              => 'ENUM',
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                5 =>
                                                    [
                                                        'name'              => 'INPUT_OBJECT',
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                6 =>
                                                    [
                                                        'name'              => 'LIST',
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                7 =>
                                                    [
                                                        'name'              => 'NON_NULL',
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                            ],
                                        'possibleTypes' => null,
                                    ],
                                    [
                                        'kind'          => 'OBJECT',
                                        'name'          => '__Field',
                                        'fields'        =>
                                            [
                                                0 =>
                                                    [
                                                        'name'              => 'name',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind'   => 'NON_NULL',
                                                                'name'   => null,
                                                                'ofType' =>
                                                                    [
                                                                        'kind' => 'SCALAR',
                                                                        'name' => 'String',
                                                                    ],
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                1 =>
                                                    [
                                                        'name'              => 'description',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind' => 'SCALAR',
                                                                'name' => 'String',
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                2 =>
                                                    [
                                                        'name'              => 'args',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind'   => 'NON_NULL',
                                                                'name'   => null,
                                                                'ofType' =>
                                                                    [
                                                                        'kind'   => 'LIST',
                                                                        'name'   => null,
                                                                        'ofType' =>
                                                                            [
                                                                                'kind'   => 'NON_NULL',
                                                                                'name'   => null,
                                                                                'ofType' =>
                                                                                    [
                                                                                        'kind' => 'OBJECT',
                                                                                        'name' => '__InputValue',
                                                                                    ],
                                                                            ],
                                                                    ],
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                3 =>
                                                    [
                                                        'name'              => 'type',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind'   => 'NON_NULL',
                                                                'name'   => null,
                                                                'ofType' =>
                                                                    [
                                                                        'kind' => 'OBJECT',
                                                                        'name' => '__Type',
                                                                    ],
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                4 =>
                                                    [
                                                        'name'              => 'isDeprecated',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind'   => 'NON_NULL',
                                                                'name'   => null,
                                                                'ofType' =>
                                                                    [
                                                                        'kind' => 'SCALAR',
                                                                        'name' => 'Boolean',
                                                                    ],
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                5 =>
                                                    [
                                                        'name'              => 'deprecationReason',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind' => 'SCALAR',
                                                                'name' => 'String',
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                            ],
                                        'inputFields'   => null,
                                        'interfaces'    =>
                                            [],
                                        'enumValues'    => null,
                                        'possibleTypes' => null,
                                    ],
                                    [
                                        'kind'          => 'OBJECT',
                                        'name'          => '__InputValue',
                                        'fields'        =>
                                            [
                                                0 =>
                                                    [
                                                        'name'              => 'name',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind'   => 'NON_NULL',
                                                                'name'   => null,
                                                                'ofType' =>
                                                                    [
                                                                        'kind' => 'SCALAR',
                                                                        'name' => 'String',
                                                                    ],
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                1 =>
                                                    [
                                                        'name'              => 'description',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind' => 'SCALAR',
                                                                'name' => 'String',
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                2 =>
                                                    [
                                                        'name'              => 'type',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind'   => 'NON_NULL',
                                                                'name'   => null,
                                                                'ofType' =>
                                                                    [
                                                                        'kind' => 'OBJECT',
                                                                        'name' => '__Type',
                                                                    ],
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                3 =>
                                                    [
                                                        'name'              => 'defaultValue',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind' => 'SCALAR',
                                                                'name' => 'String',
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                            ],
                                        'inputFields'   => null,
                                        'interfaces'    =>
                                            [],
                                        'enumValues'    => null,
                                        'possibleTypes' => null,
                                    ],
                                    [
                                        'kind'          => 'OBJECT',
                                        'name'          => '__EnumValue',
                                        'fields'        =>
                                            [
                                                0 =>
                                                    [
                                                        'name'              => 'name',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind'   => 'NON_NULL',
                                                                'name'   => null,
                                                                'ofType' =>
                                                                    [
                                                                        'kind' => 'SCALAR',
                                                                        'name' => 'String',
                                                                    ],
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                1 =>
                                                    [
                                                        'name'              => 'description',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind' => 'SCALAR',
                                                                'name' => 'String',
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                2 =>
                                                    [
                                                        'name'              => 'isDeprecated',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind'   => 'NON_NULL',
                                                                'name'   => null,
                                                                'ofType' =>
                                                                    [
                                                                        'kind' => 'SCALAR',
                                                                        'name' => 'Boolean',
                                                                    ],
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                3 =>
                                                    [
                                                        'name'              => 'deprecationReason',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind' => 'SCALAR',
                                                                'name' => 'String',
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                            ],
                                        'inputFields'   => null,
                                        'interfaces'    =>
                                            [],
                                        'enumValues'    => null,
                                        'possibleTypes' => null,
                                    ],
                                    [
                                        'kind'          => 'OBJECT',
                                        'name'          => '__Directive',
                                        'fields'        =>
                                            [
                                                0 =>
                                                    [
                                                        'name'              => 'name',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind'   => 'NON_NULL',
                                                                'name'   => null,
                                                                'ofType' =>
                                                                    [
                                                                        'kind' => 'SCALAR',
                                                                        'name' => 'String',
                                                                    ],
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                1 =>
                                                    [
                                                        'name'              => 'description',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind' => 'SCALAR',
                                                                'name' => 'String',
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                2 =>
                                                    [
                                                        'name'              => 'args',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind'   => 'NON_NULL',
                                                                'name'   => null,
                                                                'ofType' =>
                                                                    [
                                                                        'kind'   => 'LIST',
                                                                        'name'   => null,
                                                                        'ofType' =>
                                                                            [
                                                                                'kind'   => 'NON_NULL',
                                                                                'name'   => null,
                                                                                'ofType' =>
                                                                                    [
                                                                                        'kind' => 'OBJECT',
                                                                                        'name' => '__InputValue',
                                                                                    ],
                                                                            ],
                                                                    ],
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                3 =>
                                                    [
                                                        'name'              => 'isRepeatable',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind' => 'NON_NULL',
                                                                'name' => null,
                                                                'ofType' => [
                                                                    'kind' => 'SCALAR',
                                                                    'name' => 'Boolean',
                                                                    'ofType' => null,
                                                                ],
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                                4 =>
                                                    [
                                                        'name'              => 'locations',
                                                        'args'              =>
                                                            [],
                                                        'type'              =>
                                                            [
                                                                'kind'   => 'NON_NULL',
                                                                'name'   => null,
                                                                'ofType' =>
                                                                    [
                                                                        'kind'   => 'LIST',
                                                                        'name'   => null,
                                                                        'ofType' =>
                                                                            [
                                                                                'kind'   => 'NON_NULL',
                                                                                'name'   => null,
                                                                                'ofType' =>
                                                                                    [
                                                                                        'kind' => 'ENUM',
                                                                                        'name' => '__DirectiveLocation',
                                                                                    ],
                                                                            ],
                                                                    ],
                                                            ],
                                                        'isDeprecated'      => false,
                                                        'deprecationReason' => null,
                                                    ],
                                            ],
                                        'inputFields'   => null,
                                        'interfaces'    =>
                                            [],
                                        'enumValues'    => null,
                                        'possibleTypes' => null,
                                    ],
                                    [
                                        'kind'          => 'ENUM',
                                        'name'          => '__DirectiveLocation',
                                        'fields'        => null,
                                        'inputFields'   => null,
                                        'interfaces'    => null,
                                        'enumValues'    =>
                                            [
                                                0 => [
                                                    'name'              => 'QUERY',
                                                    'isDeprecated'      => false,
                                                    'deprecationReason' => null,
                                                ],
                                                1 => [
                                                    'name'              => 'MUTATION',
                                                    'isDeprecated'      => false,
                                                    'deprecationReason' => null,
                                                ],
                                                2 => [
                                                    'name'              => 'SUBSCRIPTION',
                                                    'isDeprecated'      => false,
                                                    'deprecationReason' => null,
                                                ],
                                                3 => [
                                                    'name'              => 'FIELD',
                                                    'isDeprecated'      => false,
                                                    'deprecationReason' => null,
                                                ],
                                                4 => [
                                                    'name'              => 'FRAGMENT_DEFINITION',
                                                    'isDeprecated'      => false,
                                                    'deprecationReason' => null,
                                                ],
                                                5 => [
                                                    'name'              => 'FRAGMENT_SPREAD',
                                                    'isDeprecated'      => false,
                                                    'deprecationReason' => null,
                                                ],
                                                6 => [
                                                    'name'              => 'INLINE_FRAGMENT',
                                                    'isDeprecated'      => false,
                                                    'deprecationReason' => null,
                                                ],
                                                7 => [
                                                    'name'              => 'VARIABLE_DEFINITION',
                                                    'isDeprecated'      => false,
                                                    'deprecationReason' => null,
                                                ],
                                                [
                                                    'name'              => 'SCHEMA',
                                                    'isDeprecated'      => false,
                                                    'deprecationReason' => null,
                                                ],
                                                [
                                                    'name'              => 'SCALAR',
                                                    'isDeprecated'      => false,
                                                    'deprecationReason' => null,
                                                ],
                                                [
                                                    'name'              => 'OBJECT',
                                                    'isDeprecated'      => false,
                                                    'deprecationReason' => null,
                                                ],
                                                [
                                                    'name'              => 'FIELD_DEFINITION',
                                                    'isDeprecated'      => false,
                                                    'deprecationReason' => null,
                                                ],
                                                [
                                                    'name'              => 'ARGUMENT_DEFINITION',
                                                    'isDeprecated'      => false,
                                                    'deprecationReason' => null,
                                                ],
                                                [
                                                    'name'              => 'INTERFACE',
                                                    'isDeprecated'      => false,
                                                    'deprecationReason' => null,
                                                ],
                                                [
                                                    'name'              => 'UNION',
                                                    'isDeprecated'      => false,
                                                    'deprecationReason' => null,
                                                ],
                                                [
                                                    'name'              => 'ENUM',
                                                    'isDeprecated'      => false,
                                                    'deprecationReason' => null,
                                                ],
                                                [
                                                    'name'              => 'ENUM_VALUE',
                                                    'isDeprecated'      => false,
                                                    'deprecationReason' => null,
                                                ],
                                                [
                                                    'name'              => 'INPUT_OBJECT',
                                                    'isDeprecated'      => false,
                                                    'deprecationReason' => null,
                                                ],
                                                [
                                                    'name'              => 'INPUT_FIELD_DEFINITION',
                                                    'isDeprecated'      => false,
                                                    'deprecationReason' => null,
                                                ],
                                            ],
                                        'possibleTypes' => null,
                                    ],
                                ],
                            'directives'       =>
                                [
                                    0 =>
                                        [
                                            'name'      => 'include',
                                            'isRepeatable' => false,
                                            'args'      =>
                                                [
                                                    0 =>
                                                        [
                                                            'defaultValue' => null,
                                                            'name'         => 'if',
                                                            'type'         =>
                                                                [
                                                                    'kind'   => 'NON_NULL',
                                                                    'name'   => null,
                                                                    'ofType' =>
                                                                        [
                                                                            'kind' => 'SCALAR',
                                                                            'name' => 'Boolean',
                                                                        ],
                                                                ],
                                                        ],
                                                ],
                                            'locations' =>
                                                [
                                                    0 => 'FIELD',
                                                    1 => 'FRAGMENT_SPREAD',
                                                    2 => 'INLINE_FRAGMENT',
                                                ],
                                        ],
                                    1 =>
                                        [
                                            'name'      => 'skip',
                                            'isRepeatable' => false,
                                            'args'      =>
                                                [
                                                    0 =>
                                                        [
                                                            'defaultValue' => null,
                                                            'name'         => 'if',
                                                            'type'         =>
                                                                [
                                                                    'kind'   => 'NON_NULL',
                                                                    'name'   => null,
                                                                    'ofType' =>
                                                                        [
                                                                            'kind' => 'SCALAR',
                                                                            'name' => 'Boolean',
                                                                        ],
                                                                ],
                                                        ],
                                                ],
                                            'locations' =>
                                                [
                                                    0 => 'FIELD',
                                                    1 => 'FRAGMENT_SPREAD',
                                                    2 => 'INLINE_FRAGMENT',
                                                ],
                                        ],
                                    2 =>
                                        [
                                            'name'      => 'deprecated',
                                            'isRepeatable' => false,
                                            'args'      =>
                                                [
                                                    0 =>
                                                        [
                                                            'defaultValue' => '"No longer supported"',
                                                            'name'         => 'reason',
                                                            'type'         =>
                                                                [
                                                                    'kind'   => 'SCALAR',
                                                                    'name'   => 'String',
                                                                    'ofType' => null,
                                                                ],
                                                        ],
                                                ],
                                            'locations' =>
                                                [
                                                    0 => 'FIELD_DEFINITION',
                                                    1 => 'ENUM_VALUE',
                                                ],
                                        ],
                                ],
                        ],
                ],
        ];

        $actual = GraphQL::executeQuery($emptySchema, $request)->toArray();

        // self::assertEquals($expected, $actual);
        self::assertArraySubset($expected, $actual);
    }

    /**
     * @see it('introspects on input object')
     */
    public function testIntrospectsOnInputObject(): void
    {
        $TestInputObject = new InputObjectType([
            'name'   => 'TestInputObject',
            'fields' => [
                'a' => ['type' => Type::string(), 'defaultValue' => "tes\t de\fault"],
                'b' => ['type' => Type::listOf(Type::string())],
                'c' => ['type' => Type::string(), 'defaultValue' => null],
            ],
        ]);

        $TestType = new ObjectType([
            'name'   => 'TestType',
            'fields' => [
                'field' => [
                    'type'    => Type::string(),
                    'args'    => ['complex' => ['type' => $TestInputObject]],
                    'resolve' => static function ($testType, $args) {
                        return json_encode($args['complex']);
                    },
                ],
            ],
        ]);

        $schema  = new Schema(['query' => $TestType]);
        $request = '
          {
            __type(name: "TestInputObject") {
              kind
              name
              inputFields {
                name
                type { ...TypeRef }
                defaultValue
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
            'kind'        => 'INPUT_OBJECT',
            'name'        => 'TestInputObject',
            'inputFields' => [
                [
                    'name'         => 'a',
                    'type'         => [
                        'kind'   => 'SCALAR',
                        'name'   => 'String',
                        'ofType' => null,
                    ],
                    'defaultValue' => '"tes\t de\fault"',
                ],
                [
                    'name'         => 'b',
                    'type'         => [
                        'kind'   => 'LIST',
                        'name'   => null,
                        'ofType' => ['kind' => 'SCALAR', 'name' => 'String', 'ofType' => null],
                    ],
                    'defaultValue' => null,
                ],
                [
                    'name'         => 'c',
                    'type'         => [
                        'kind'   => 'SCALAR',
                        'name'   => 'String',
                        'ofType' => null,
                    ],
                    'defaultValue' => 'null',// defaultValue was set (even if it was set to null)
                ],
            ],
        ];

        $result = GraphQL::executeQuery($schema, $request)->toArray();
        $result = $result['data']['__type'];
        self::assertEquals($expectedFragment, $result);
    }

    /**
     * @see it('supports the __type root field')
     */
    public function testSupportsTheTypeRootField(): void
    {
        $TestType = new ObjectType([
            'name'   => 'TestType',
            'fields' => [
                'testField' => [
                    'type' => Type::string(),
                ],
            ],
        ]);

        $schema  = new Schema(['query' => $TestType]);
        $request = '
          {
            __type(name: "TestType") {
              name
            }
          }
        ';

        $expected = [
            'data' => [
                '__type' => ['name' => 'TestType'],
            ],
        ];

        self::assertEquals($expected, GraphQL::executeQuery($schema, $request)->toArray());
    }

    /**
     * @see it('identifies deprecated fields')
     */
    public function testIdentifiesDeprecatedFields(): void
    {
        $TestType = new ObjectType([
            'name'   => 'TestType',
            'fields' => [
                'nonDeprecated' => [
                    'type' => Type::string(),
                ],
                'deprecated'    => [
                    'type'              => Type::string(),
                    'deprecationReason' => 'Removed in 1.0',
                ],
            ],
        ]);

        $schema  = new Schema(['query' => $TestType]);
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
                    'name'   => 'TestType',
                    'fields' => [
                        [
                            'name'              => 'nonDeprecated',
                            'isDeprecated'      => false,
                            'deprecationReason' => null,
                        ],
                        [
                            'name'              => 'deprecated',
                            'isDeprecated'      => true,
                            'deprecationReason' => 'Removed in 1.0',
                        ],
                    ],
                ],
            ],
        ];
        self::assertEquals($expected, GraphQL::executeQuery($schema, $request)->toArray());
    }

    /**
     * @see it('respects the includeDeprecated parameter for fields')
     */
    public function testRespectsTheIncludeDeprecatedParameterForFields(): void
    {
        $TestType = new ObjectType([
            'name'   => 'TestType',
            'fields' => [
                'nonDeprecated' => [
                    'type' => Type::string(),
                ],
                'deprecated'    => [
                    'type'              => Type::string(),
                    'deprecationReason' => 'Removed in 1.0',
                ],
            ],
        ]);

        $schema  = new Schema(['query' => $TestType]);
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
                    'name'          => 'TestType',
                    'trueFields'    => [
                        ['name' => 'nonDeprecated'],
                        ['name' => 'deprecated'],
                    ],
                    'falseFields'   => [
                        ['name' => 'nonDeprecated'],
                    ],
                    'omittedFields' => [
                        ['name' => 'nonDeprecated'],
                    ],
                ],
            ],
        ];

        self::assertEquals($expected, GraphQL::executeQuery($schema, $request)->toArray());
    }

    /**
     * @see it('identifies deprecated enum values')
     */
    public function testIdentifiesDeprecatedEnumValues(): void
    {
        $TestEnum = new EnumType([
            'name'   => 'TestEnum',
            'values' => [
                'NONDEPRECATED'     => ['value' => 0],
                'DEPRECATED'        => ['value' => 1, 'deprecationReason' => 'Removed in 1.0'],
                'ALSONONDEPRECATED' => ['value' => 2],
            ],
        ]);

        $TestType = new ObjectType([
            'name'   => 'TestType',
            'fields' => [
                'testEnum' => ['type' => $TestEnum],
            ],
        ]);

        $schema  = new Schema(['query' => $TestType]);
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
                    'name'       => 'TestEnum',
                    'enumValues' => [
                        [
                            'name'              => 'NONDEPRECATED',
                            'isDeprecated'      => false,
                            'deprecationReason' => null,
                        ],
                        [
                            'name'              => 'DEPRECATED',
                            'isDeprecated'      => true,
                            'deprecationReason' => 'Removed in 1.0',
                        ],
                        [
                            'name'              => 'ALSONONDEPRECATED',
                            'isDeprecated'      => false,
                            'deprecationReason' => null,
                        ],
                    ],
                ],
            ],
        ];
        self::assertEquals($expected, GraphQL::executeQuery($schema, $request)->toArray());
    }

    /**
     * @see it('respects the includeDeprecated parameter for enum values')
     */
    public function testRespectsTheIncludeDeprecatedParameterForEnumValues(): void
    {
        $TestEnum = new EnumType([
            'name'   => 'TestEnum',
            'values' => [
                'NONDEPRECATED'     => ['value' => 0],
                'DEPRECATED'        => ['value' => 1, 'deprecationReason' => 'Removed in 1.0'],
                'ALSONONDEPRECATED' => ['value' => 2],
            ],
        ]);

        $TestType = new ObjectType([
            'name'   => 'TestType',
            'fields' => [
                'testEnum' => ['type' => $TestEnum],
            ],
        ]);

        $schema   = new Schema(['query' => $TestType]);
        $request  = '
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
                    'name'          => 'TestEnum',
                    'trueValues'    => [
                        ['name' => 'NONDEPRECATED'],
                        ['name' => 'DEPRECATED'],
                        ['name' => 'ALSONONDEPRECATED'],
                    ],
                    'falseValues'   => [
                        ['name' => 'NONDEPRECATED'],
                        ['name' => 'ALSONONDEPRECATED'],
                    ],
                    'omittedValues' => [
                        ['name' => 'NONDEPRECATED'],
                        ['name' => 'ALSONONDEPRECATED'],
                    ],
                ],
            ],
        ];
        self::assertEquals($expected, GraphQL::executeQuery($schema, $request)->toArray());
    }

    /**
     * @see it('fails as expected on the __type root field without an arg')
     */
    public function testFailsAsExpectedOnTheTypeRootFieldWithoutAnArg(): void
    {
        $TestType = new ObjectType([
            'name'   => 'TestType',
            'fields' => [
                'testField' => [
                    'type' => Type::string(),
                ],
            ],
        ]);

        $schema   = new Schema(['query' => $TestType]);
        $request  = '
      {
        __type {
          name
        }
      }
    ';
        $expected = [
            'errors' => [
                ErrorHelper::create(
                    ProvidedRequiredArguments::missingFieldArgMessage('__type', 'name', 'String!'),
                    [new SourceLocation(3, 9)]
                ),
            ],
        ];
        self::assertArraySubset($expected, GraphQL::executeQuery($schema, $request)->toArray());
    }

    /**
     * @see it('exposes descriptions on types and fields')
     */
    public function testExposesDescriptionsOnTypesAndFields(): void
    {
        $QueryRoot = new ObjectType([
            'name'   => 'QueryRoot',
            'fields' => ['a' => Type::string()],
        ]);

        $schema   = new Schema(['query' => $QueryRoot]);
        $request  = '
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
                    'name'        => '__Schema',
                    'description' => 'A GraphQL Schema defines the capabilities of a ' .
                        'GraphQL server. It exposes all available types and ' .
                        'directives on the server, as well as the entry ' .
                        'points for query, mutation, and subscription operations.',
                    'fields'      => [
                        [
                            'name'        => 'types',
                            'description' => 'A list of all types supported by this server.',
                        ],
                        [
                            'name'        => 'queryType',
                            'description' => 'The type that query operations will be rooted at.',
                        ],
                        [
                            'name'        => 'mutationType',
                            'description' => 'If this server supports mutation, the type that ' .
                                'mutation operations will be rooted at.',
                        ],
                        [
                            'name'        => 'subscriptionType',
                            'description' => 'If this server support subscription, the type that subscription operations will be rooted at.',
                        ],
                        [
                            'name'        => 'directives',
                            'description' => 'A list of all directives supported by this server.',
                        ],
                    ],
                ],
            ],
        ];
        self::assertEquals($expected, GraphQL::executeQuery($schema, $request)->toArray());
    }

    /**
     * @see it('exposes descriptions on enums')
     */
    public function testExposesDescriptionsOnEnums(): void
    {
        $QueryRoot = new ObjectType([
            'name'   => 'QueryRoot',
            'fields' => ['a' => Type::string()],
        ]);

        $schema   = new Schema(['query' => $QueryRoot]);
        $request  = '
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
                    'name'        => '__TypeKind',
                    'description' => 'An enum describing what kind of type a given `__Type` is.',
                    'enumValues'  => [
                        [
                            'description' => 'Indicates this type is a scalar.',
                            'name'        => 'SCALAR',
                        ],
                        [
                            'description' => 'Indicates this type is an object. ' .
                                '`fields` and `interfaces` are valid fields.',
                            'name'        => 'OBJECT',
                        ],
                        [
                            'description' => 'Indicates this type is an interface. ' .
                                '`fields`, `interfaces`, and `possibleTypes` are valid fields.',
                            'name'        => 'INTERFACE',
                        ],
                        [
                            'description' => 'Indicates this type is a union. ' .
                                '`possibleTypes` is a valid field.',
                            'name'        => 'UNION',
                        ],
                        [
                            'description' => 'Indicates this type is an enum. ' .
                                '`enumValues` is a valid field.',
                            'name'        => 'ENUM',
                        ],
                        [
                            'description' => 'Indicates this type is an input object. ' .
                                '`inputFields` is a valid field.',
                            'name'        => 'INPUT_OBJECT',
                        ],
                        [
                            'description' => 'Indicates this type is a list. ' .
                                '`ofType` is a valid field.',
                            'name'        => 'LIST',
                        ],
                        [
                            'description' => 'Indicates this type is a non-null. ' .
                                '`ofType` is a valid field.',
                            'name'        => 'NON_NULL',
                        ],
                    ],
                ],
            ],
        ];

        self::assertEquals($expected, GraphQL::executeQuery($schema, $request)->toArray());
    }

    /**
     * @see it('executes an introspection query without calling global fieldResolver')
     */
    public function testExecutesAnIntrospectionQueryWithoutCallingGlobalFieldResolver(): void
    {
        $QueryRoot = new ObjectType([
            'name' => 'QueryRoot',
            'fields' => [
                'onlyField' => [ 'type' => Type::string() ],
            ],
        ]);

        $schema = new Schema(['query' => $QueryRoot]);
        $source = Introspection::getIntrospectionQuery(['directiveIsRepeatable' => true]);

        $calledForFields = [];
        /* istanbul ignore next */
        $fieldResolver = static function ($value, $_1, $_2, $info) use (&$calledForFields) {
            $calledForFields[sprintf('%s::%s', $info->parentType->name, $info->fieldName)] = true;

            return $value;
        };

        GraphQL::executeQuery($schema, $source, null, null, null, null, $fieldResolver);
        self::assertEmpty($calledForFields);
    }
}
