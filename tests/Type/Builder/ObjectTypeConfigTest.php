<?php

namespace GraphQL\Tests\Type\Builder;

use GraphQL\Type\Builder\ArgsConfig;
use GraphQL\Type\Builder\ObjectTypeConfig;
use GraphQL\Type\Definition\Type;

class ObjectTypeConfigTest extends \PHPUnit_Framework_TestCase
{
    public function testBuild()
    {
        $args = ArgsConfig::create()
            ->addArg('arg1', Type::boolean(), true, 'description arg1')
            ->addArg('arg2', Type::string(), 'defaultVal', 'description arg2');

        $field1Resolver = function () {
            return 'resolve it!';
        };
        $isTypeOf = function () {
            return true;
        };

        $config = ObjectTypeConfig::create()
            ->name('TypeName')
            ->addField('field1', Type::string(), 'description field1', $args, $field1Resolver)
            ->addField('field2', Type::nonNull(Type::string()))
            ->addDeprecatedField('deprecatedField', Type::string(), 'This field is deprecated.')
            ->description('My new Object')
            ->isTypeOf($isTypeOf)
            ->resolveField();

        $this->assertEquals(
            [
                'name' => 'TypeName',
                'description' => 'My new Object',
                'fields' => [
                    [
                        'name' => 'field1',
                        'type' => Type::string(),
                        'description' => 'description field1',
                        'resolve' => $field1Resolver,
                        'args' => [
                            [
                                'name' => 'arg1',
                                'type' => Type::boolean(),
                                'description' => 'description arg1',
                                'defaultValue' => true,
                            ],
                            [
                                'name' => 'arg2',
                                'type' => Type::string(),
                                'description' => 'description arg2',
                                'defaultValue' => 'defaultVal',
                            ],
                        ],
                        'complexity' => null,
                    ],
                    [
                        'name' => 'field2',
                        'type' => Type::nonNull(Type::string()),
                        'description' => null,
                        'resolve' => null,
                        'complexity' => null,
                    ],
                    [
                        'name' => 'deprecatedField',
                        'type' => Type::string(),
                        'description' => null,
                        'resolve' => null,
                        'complexity' => null,
                        'deprecationReason' => 'This field is deprecated.',
                    ],
                ],
                'isTypeOf' => $isTypeOf,
                'resolveField' => null,
            ],
            $config->build()
        );
    }
}
