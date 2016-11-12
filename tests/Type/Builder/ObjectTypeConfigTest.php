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

        $config = ObjectTypeConfig::create()
            ->name('TypeName')
            ->addField('field1', Type::string(), null, 'description field1', $args)
            ->addField('field2', Type::nonNull(Type::string()))
        ;

        $this->assertEquals(
            [
                'name' => 'TypeName',
                'fields' =>  [
                    [
                        'name' => 'field1',
                        'type' => Type::string(),
                        'description' => 'description field1',
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
                    ],
                    [
                        'name' => 'field2',
                        'type' => Type::nonNull(Type::string()),
                    ],
                ],
            ],
            $config->build()
        );
    }
}
