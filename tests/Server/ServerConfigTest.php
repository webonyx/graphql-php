<?php
namespace GraphQL\Tests\Server;

use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Type\Schema;
use GraphQL\Server\ServerConfig;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class ServerConfigTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaults()
    {
        $config = ServerConfig::create();
        $this->assertEquals(null, $config->getSchema());
        $this->assertEquals(null, $config->getContext());
        $this->assertEquals(null, $config->getRootValue());
        $this->assertEquals(null, $config->getErrorFormatter());
        $this->assertEquals(null, $config->getErrorsHandler());
        $this->assertEquals(null, $config->getPromiseAdapter());
        $this->assertEquals(null, $config->getValidationRules());
        $this->assertEquals(null, $config->getFieldResolver());
        $this->assertEquals(null, $config->getPersistentQueryLoader());
        $this->assertEquals(false, $config->getDebug());
        $this->assertEquals(false, $config->getQueryBatching());
    }

    public function testAllowsSettingSchema()
    {
        $schema = new Schema(['query' => new ObjectType(['name' => 'a', 'fields' => []])]);
        $config = ServerConfig::create()
            ->setSchema($schema);

        $this->assertSame($schema, $config->getSchema());

        $schema2 = new Schema(['query' => new ObjectType(['name' => 'a', 'fields' => []])]);
        $config->setSchema($schema2);
        $this->assertSame($schema2, $config->getSchema());
    }

    public function testAllowsSettingContext()
    {
        $config = ServerConfig::create();

        $context = [];
        $config->setContext($context);
        $this->assertSame($context, $config->getContext());

        $context2 = new \stdClass();
        $config->setContext($context2);
        $this->assertSame($context2, $config->getContext());
    }

    public function testAllowsSettingRootValue()
    {
        $config = ServerConfig::create();

        $rootValue = [];
        $config->setRootValue($rootValue);
        $this->assertSame($rootValue, $config->getRootValue());

        $context2 = new \stdClass();
        $config->setRootValue($context2);
        $this->assertSame($context2, $config->getRootValue());
    }

    public function testAllowsSettingErrorFormatter()
    {
        $config = ServerConfig::create();

        $formatter = function() {};
        $config->setErrorFormatter($formatter);
        $this->assertSame($formatter, $config->getErrorFormatter());

        $formatter = 'date'; // test for callable
        $config->setErrorFormatter($formatter);
        $this->assertSame($formatter, $config->getErrorFormatter());
    }

    public function testAllowsSettingErrorsHandler()
    {
        $config = ServerConfig::create();

        $handler = function() {};
        $config->setErrorsHandler($handler);
        $this->assertSame($handler, $config->getErrorsHandler());

        $handler = 'date'; // test for callable
        $config->setErrorsHandler($handler);
        $this->assertSame($handler, $config->getErrorsHandler());
    }

    public function testAllowsSettingPromiseAdapter()
    {
        $config = ServerConfig::create();

        $adapter1 = new SyncPromiseAdapter();
        $config->setPromiseAdapter($adapter1);
        $this->assertSame($adapter1, $config->getPromiseAdapter());

        $adapter2 = new SyncPromiseAdapter();
        $config->setPromiseAdapter($adapter2);
        $this->assertSame($adapter2, $config->getPromiseAdapter());
    }

    public function testAllowsSettingValidationRules()
    {
        $config = ServerConfig::create();

        $rules = [];
        $config->setValidationRules($rules);
        $this->assertSame($rules, $config->getValidationRules());

        $rules = [function() {}];
        $config->setValidationRules($rules);
        $this->assertSame($rules, $config->getValidationRules());

        $rules = function() {return [function() {}];};
        $config->setValidationRules($rules);
        $this->assertSame($rules, $config->getValidationRules());
    }

    public function testAllowsSettingDefaultFieldResolver()
    {
        $config = ServerConfig::create();

        $resolver = function() {};
        $config->setFieldResolver($resolver);
        $this->assertSame($resolver, $config->getFieldResolver());

        $resolver = 'date'; // test for callable
        $config->setFieldResolver($resolver);
        $this->assertSame($resolver, $config->getFieldResolver());
    }

    public function testAllowsSettingPersistedQueryLoader()
    {
        $config = ServerConfig::create();

        $loader = function() {};
        $config->setPersistentQueryLoader($loader);
        $this->assertSame($loader, $config->getPersistentQueryLoader());

        $loader = 'date'; // test for callable
        $config->setPersistentQueryLoader($loader);
        $this->assertSame($loader, $config->getPersistentQueryLoader());
    }

    public function testAllowsSettingCatchPhpErrors()
    {
        $config = ServerConfig::create();

        $config->setDebug(true);
        $this->assertSame(true, $config->getDebug());

        $config->setDebug(false);
        $this->assertSame(false, $config->getDebug());
    }

    public function testAcceptsArray()
    {
        $arr = [
            'schema' => new \GraphQL\Type\Schema([
                'query' => new ObjectType(['name' => 't', 'fields' => ['a' => Type::string()]])
            ]),
            'context' => new \stdClass(),
            'rootValue' => new \stdClass(),
            'errorFormatter' => function() {},
            'promiseAdapter' => new SyncPromiseAdapter(),
            'validationRules' => [function() {}],
            'fieldResolver' => function() {},
            'persistentQueryLoader' => function() {},
            'debug' => true,
            'queryBatching' => true,
        ];

        $config = ServerConfig::create($arr);

        $this->assertSame($arr['schema'], $config->getSchema());
        $this->assertSame($arr['context'], $config->getContext());
        $this->assertSame($arr['rootValue'], $config->getRootValue());
        $this->assertSame($arr['errorFormatter'], $config->getErrorFormatter());
        $this->assertSame($arr['promiseAdapter'], $config->getPromiseAdapter());
        $this->assertSame($arr['validationRules'], $config->getValidationRules());
        $this->assertSame($arr['fieldResolver'], $config->getFieldResolver());
        $this->assertSame($arr['persistentQueryLoader'], $config->getPersistentQueryLoader());
        $this->assertSame(true, $config->getDebug());
        $this->assertSame(true, $config->getQueryBatching());
    }

    public function testThrowsOnInvalidArrayKey()
    {
        $arr = [
            'missingKey' => 'value'
        ];

        $this->setExpectedException(
            InvariantViolation::class,
            'Unknown server config option "missingKey"'
        );

        ServerConfig::create($arr);
    }

    public function testInvalidValidationRules()
    {
        $rules = new \stdClass();
        $config = ServerConfig::create();

        $this->setExpectedException(
            InvariantViolation::class,
            'Server config expects array of validation rules or callable returning such array, but got instance of stdClass'
        );

        $config->setValidationRules($rules);
    }
}
