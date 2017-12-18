<?php

namespace GraphQL\Tests\Type\Builder;

use GraphQL\Type\Builder\ArgConfig;
use GraphQL\Type\Builder\ArgsConfig;
use GraphQL\Type\Builder\Config;
use GraphQL\Type\Builder\FieldConfig;
use GraphQL\Type\Builder\FieldsConfig;
use GraphQL\Type\Builder\ObjectTypeConfig;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

class ObjectTypeConfigTest extends \PHPUnit_Framework_TestCase
{
    private $field1Resolver;

    private $isTypeOf;

    private $interface;

    public function setUp()
    {
        $this->field1Resolver = function () {
            return 'resolve it!';
        };
        $this->isTypeOf = function () {
            return true;
        };
        $this->interface = new InterfaceType(['name' => 'Foo', 'fields' => ['field2' => ['type' => Type::nonNull(Type::string())]]]);
    }

    public function testBuildUsingAddField()
    {
        $args = ArgsConfig::create()
            ->addArg('arg1', Type::boolean(), true, 'description arg1')
            ->addArg('arg2', Type::string(), 'defaultVal', 'description arg2');

        $config = ObjectTypeConfig::create()
            ->name('TypeName')
            ->description('My new Object')
            ->interfaces([$this->interface])
            ->addField('field1', Type::string(), 'description field1', $args, $this->field1Resolver)
            ->addField('field2', Type::nonNull(Type::string()))
            ->addDeprecatedField('deprecatedField', Type::string(), 'This field is deprecated.')
            ->isTypeOf($this->isTypeOf)
            ->resolveField();

        $this->assertConfig($config);
    }

    public function testBuildUsingFields()
    {
        $args = ArgsConfig::create()
            ->addArg('arg1', Type::boolean(), true, 'description arg1')
            ->addArg('arg2', Type::string(), 'defaultVal', 'description arg2');

        $fields = FieldsConfig::create()
            ->addField('field1', Type::string(), 'description field1', $args, $this->field1Resolver)
            ->addField('field2', Type::nonNull(Type::string()))
        ;
        $config = ObjectTypeConfig::create()
            ->name('TypeName')
            ->description('My new Object')
            ->interfaces([$this->interface])
            ->fields($fields)
            ->addDeprecatedField('deprecatedField', Type::string(), 'This field is deprecated.')
            ->isTypeOf($this->isTypeOf)
            ->resolveField();

        $this->assertConfig($config);
    }

    public function testBuildUsingFieldConfig()
    {
        $args = ArgsConfig::create()
            ->addArg('arg1', Type::boolean(), true, 'description arg1')
            ->addArg('arg2', Type::string(), 'defaultVal', 'description arg2');

        $fields = FieldsConfig::create();

        $fields->addFieldConfig(
                FieldConfig::create()
                    ->name('field1')->type(Type::string())->description('description field1')
                    ->addArgs($args)->resolve($this->field1Resolver)
            )
            ->addFieldConfig(FieldConfig::create()->name('field2')->type(Type::nonNull(Type::string())));
        ;
        $config = ObjectTypeConfig::create()
            ->name('TypeName')
            ->description('My new Object')
            ->interfaces([$this->interface])
            ->fields($fields)
            ->addDeprecatedField('deprecatedField', Type::string(), 'This field is deprecated.')
            ->isTypeOf($this->isTypeOf)
            ->resolveField();

        $this->assertConfig($config);
    }

    public function testFieldsAsCallable()
    {
        $fields = function () {
             return [];
        };

        $config = ObjectTypeConfig::create()
            ->name('TypeName')
            ->fields($fields);

        $this->assertEquals(
            [
                'name' => 'TypeName',
                'fields' => $fields,
            ],
            $config->build()
        );
    }

    public function testFieldWithComplexity()
    {
        $complexity = function () {
            return 2000;
        };

        $config = ObjectTypeConfig::create()
            ->name('TypeName')
            ->addFieldConfig(
                FieldConfig::create()
                    ->name('field1')
                    ->addArg(ArgConfig::create()->name('arg1')->type(Type::string()))
                    ->type(Type::string())
                    ->complexity($complexity)
            )
        ;

        $this->assertEquals(
            [
                'name' => 'TypeName',
                'fields' => [
                    [
                        'name' => 'field1',
                        'type' => Type::string(),
                        'args' => [
                            [
                                'name' => 'arg1',
                                'type' => Type::string(),
                            ],
                        ],
                        'complexity' => $complexity
                    ]
                ],
            ],
            $config->build()
        );
    }

    public function testFieldsWithComplexity()
    {
        $complexity = function () {
            return 2000;
        };

        $fields = FieldsConfig::create()
            ->addField('field1', Type::string(), null, null, null, $complexity)
            ;

        $this->assertEquals(
            [
                [
                    'name' => 'field1',
                    'type' => Type::string(),
                    'complexity' => $complexity
                ]
            ],
            $fields->build()
        );
    }

    private function assertConfig(Config $config)
    {
        $this->assertEquals(
            [
                'name' => 'TypeName',
                'description' => 'My new Object',
                'interfaces' => [$this->interface],
                'fields' => [
                    [
                        'name' => 'field1',
                        'type' => Type::string(),
                        'description' => 'description field1',
                        'resolve' => $this->field1Resolver,
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
                    [
                        'name' => 'deprecatedField',
                        'type' => Type::string(),
                        'deprecationReason' => 'This field is deprecated.',
                    ],
                ],
                'isTypeOf' => $this->isTypeOf,
                'resolveField' => null,
            ],
            $config->build()
        );
    }
}
