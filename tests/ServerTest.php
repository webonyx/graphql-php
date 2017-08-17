<?php
namespace GraphQL\Tests;

use GraphQL\Error\Debug;
use GraphQL\Error\FormattedError;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\UserError;
use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Schema;
use GraphQL\Server;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\EagerResolution;
use GraphQL\Validator\DocumentValidator;

class ServerTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaults()
    {
        $server = @new Server();
        $this->assertEquals(null, $server->getQueryType());
        $this->assertEquals(null, $server->getMutationType());
        $this->assertEquals(null, $server->getSubscriptionType());
        $this->assertEquals(Directive::getInternalDirectives(), $server->getDirectives());
        $this->assertEquals([], $server->getTypes());
        $this->assertEquals(null, $server->getTypeResolutionStrategy());

        $this->assertEquals(null, $server->getContext());
        $this->assertEquals(null, $server->getRootValue());
        $this->assertEquals(0, $server->getDebug());

        $this->assertEquals(['GraphQL\Error\FormattedError', 'createFromException'], $server->getExceptionFormatter());
        $this->assertEquals(['GraphQL\Error\FormattedError', 'createFromPHPError'], $server->getPhpErrorFormatter());
        $this->assertEquals(null, $server->getPromiseAdapter());
        $this->assertEquals('Unexpected Error', $server->getUnexpectedErrorMessage());
        $this->assertEquals(500, $server->getUnexpectedErrorStatus());
        $this->assertEquals(DocumentValidator::allRules(), $server->getValidationRules());

        try {
            $server->getSchema();
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals('Schema query must be Object Type but got: NULL', $e->getMessage());
        }
    }

    public function testSchemaDefinition()
    {
        $mutationType = $queryType = $subscriptionType = new ObjectType(['name' => 'A', 'fields' => ['a' => Type::string()]]);

        $schema = new Schema([
            'query' => $queryType
        ]);

        try {
            Server::create()
                ->setQueryType($queryType)
                ->setSchema($schema);
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals(
                'Cannot set Schema on Server: Query Type is already set '.
                '(GraphQL\Server::setQueryType is mutually exclusive with GraphQL\Server::setSchema)',
                $e->getMessage()
            );
        }

        try {
            Server::create()
                ->setMutationType($mutationType)
                ->setSchema($schema);
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals(
                'Cannot set Schema on Server: Mutation Type is already set '.
                '(GraphQL\Server::setMutationType is mutually exclusive with GraphQL\Server::setSchema)',
                $e->getMessage()
            );
        }

        try {
            Server::create()
                ->setSubscriptionType($subscriptionType)
                ->setSchema($schema);
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals(
                'Cannot set Schema on Server: Subscription Type is already set '.
                '(GraphQL\Server::setSubscriptionType is mutually exclusive with GraphQL\Server::setSchema)',
                $e->getMessage()
            );
        }

        try {
            Server::create()
                ->setDirectives(Directive::getInternalDirectives())
                ->setSchema($schema);
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals(
                'Cannot set Schema on Server: Directives are already set '.
                '(GraphQL\Server::setDirectives is mutually exclusive with GraphQL\Server::setSchema)',
                $e->getMessage()
            );
        }

        try {
            Server::create()
                ->addTypes([$queryType, $mutationType])
                ->setSchema($schema);
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals(
                'Cannot set Schema on Server: Additional types are already set '.
                '(GraphQL\Server::addTypes is mutually exclusive with GraphQL\Server::setSchema)',
                $e->getMessage()
            );
        }


        try {
            Server::create()
                ->setTypeResolutionStrategy(new EagerResolution([$queryType, $mutationType]))
                ->setSchema($schema);
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals(
                'Cannot set Schema on Server: Type Resolution Strategy is already set '.
                '(GraphQL\Server::setTypeResolutionStrategy is mutually exclusive with GraphQL\Server::setSchema)',
                $e->getMessage()
            );
        }

        try {
            Server::create()
                ->setSchema($schema)
                ->setQueryType($queryType);
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals(
                'Cannot set Query Type on Server: Schema is already set '.
                '(GraphQL\Server::setQueryType is mutually exclusive with GraphQL\Server::setSchema)',
                $e->getMessage()
            );
        }

        try {
            Server::create()
                ->setSchema($schema)
                ->setMutationType($mutationType);
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals(
                'Cannot set Mutation Type on Server: Schema is already set '.
                '(GraphQL\Server::setMutationType is mutually exclusive with GraphQL\Server::setSchema)',
                $e->getMessage()
            );
        }

        try {
            Server::create()
                ->setSchema($schema)
                ->setSubscriptionType($subscriptionType);
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals(
                'Cannot set Subscription Type on Server: Schema is already set '.
                '(GraphQL\Server::setSubscriptionType is mutually exclusive with GraphQL\Server::setSchema)',
                $e->getMessage()
            );
        }

        try {
            Server::create()
                ->setSchema($schema)
                ->setDirectives([]);
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals(
                'Cannot set Directives on Server: Schema is already set '.
                '(GraphQL\Server::setDirectives is mutually exclusive with GraphQL\Server::setSchema)',
                $e->getMessage()
            );
        }

        try {
            Server::create()
                ->setSchema($schema)
                ->addTypes([$queryType, $mutationType]);
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals(
                'Cannot set Types on Server: Schema is already set '.
                '(GraphQL\Server::addTypes is mutually exclusive with GraphQL\Server::setSchema)',
                $e->getMessage()
            );
        }
        // But empty types should work (as they don't change anything):
        Server::create()
            ->setSchema($schema)
            ->addTypes([]);

        try {
            Server::create()
                ->setSchema($schema)
                ->setTypeResolutionStrategy(new EagerResolution([$queryType, $mutationType]));
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals(
                'Cannot set Type Resolution Strategy on Server: Schema is already set '.
                '(GraphQL\Server::setTypeResolutionStrategy is mutually exclusive with GraphQL\Server::setSchema)',
                $e->getMessage()
            );
        }

        try {
            Server::create()
                ->setSchema($schema)
                ->setSchema(new Schema(['query' => $queryType]));
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals(
                'Cannot set Schema on Server: Different schema is already set',
                $e->getMessage()
            );
        }


        // This should not throw:
        $server = Server::create()
            ->setSchema($schema);

        $this->assertSame($schema, $server->getSchema());

        $server = Server::create()
            ->setQueryType($queryType);
        $this->assertSame($queryType, $server->getQueryType());
        $this->assertSame($queryType, $server->getSchema()->getQueryType());

        $server = Server::create()
            ->setQueryType($queryType)
            ->setMutationType($mutationType);

        $this->assertSame($mutationType, $server->getMutationType());
        $this->assertSame($mutationType, $server->getSchema()->getMutationType());

        $server = Server::create()
            ->setQueryType($queryType)
            ->setSubscriptionType($subscriptionType);

        $this->assertSame($subscriptionType, $server->getSubscriptionType());
        $this->assertSame($subscriptionType, $server->getSchema()->getSubscriptionType());

        $server = Server::create()
            ->setQueryType($queryType)
            ->addTypes($types = [$queryType, $subscriptionType]);

        $this->assertSame($types, $server->getTypes());
        $server->addTypes([$mutationType]);
        $this->assertSame(array_merge($types, [$mutationType]), $server->getTypes());

        $server = Server::create()
            ->setDirectives($directives = []);

        $this->assertSame($directives, $server->getDirectives());
    }

    public function testParse()
    {
        $server = Server::create();
        $ast = $server->parse('{q}');
        $this->assertInstanceOf('GraphQL\Language\AST\DocumentNode', $ast);

        try {
            $server->parse('{q');
            $this->fail('Expected exception not thrown');
        } catch (\GraphQL\Error\SyntaxError $e) {
            $this->assertContains('{q', $e->getMessage());
        }
    }

    public function testValidate()
    {
        $server = Server::create()
            ->setQueryType(new ObjectType(['name' => 'Q', 'fields' => ['a' => Type::string()]]));

        $ast = $server->parse('{q}');
        $errors = $server->validate($ast);

        $this->assertInternalType('array', $errors);
        $this->assertNotEmpty($errors);

        try {
            $server = Server::create();
            $server->validate($ast);
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals(
                'Cannot validate, schema contains errors: Schema query must be Object Type but got: NULL',
                $e->getMessage()
            );
        }
    }

    public function testPromiseAdapter()
    {
        $adapter1 = new SyncPromiseAdapter();
        $adapter2 = new SyncPromiseAdapter();

        $server = Server::create()
            ->setPromiseAdapter($adapter1);

        $this->assertSame($adapter1, $server->getPromiseAdapter());
        $server->setPromiseAdapter($adapter1);

        try {
            $server->setPromiseAdapter($adapter2);
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals(
                'Cannot set promise adapter: Different adapter is already set',
                $e->getMessage()
            );
        }
    }

    public function testValidationRules()
    {
        $rules = [];
        $server = Server::create()
            ->setValidationRules($rules);

        $this->assertSame($rules, $server->getValidationRules());
    }

    public function testExecuteQuery()
    {
        $called = false;
        $queryType = new ObjectType([
            'name' => 'Q',
            'fields' => [
                'field' => [
                    'type' => Type::string(),
                    'resolve' => function($value, $args, $context, ResolveInfo $info) use (&$called) {
                        $called = true;
                        $this->assertEquals(null, $context);
                        $this->assertEquals(null, $value);
                        $this->assertEquals(null, $info->rootValue);
                        return 'ok';
                    }
                ]
            ]
        ]);

        $server = Server::create()
            ->setQueryType($queryType);

        $result = $server->executeQuery('{field}');
        $this->assertEquals(true, $called);
        $this->assertInstanceOf('GraphQL\Executor\ExecutionResult', $result);
        $this->assertEquals(['data' => ['field' => 'ok']], $result->toArray());

        $called = false;
        $contextValue = new \stdClass();
        $rootValue = new \stdClass();

        $queryType = new ObjectType([
            'name' => 'QueryType',
            'fields' => [
                'field' => [
                    'type' => Type::string(),
                    'resolve' => function($value, $args, $context, ResolveInfo $info) use (&$called, $contextValue, $rootValue) {
                        $called = true;
                        $this->assertSame($rootValue, $value);
                        $this->assertSame($contextValue, $context);
                        $this->assertEquals($rootValue, $info->rootValue);
                        return 'ok';
                    }
                ]
            ]
        ]);

        $server = Server::create()
            ->setQueryType($queryType)
            ->setRootValue($rootValue)
            ->setContext($contextValue);

        $result = $server->executeQuery('{field}');
        $this->assertEquals(true, $called);
        $this->assertInstanceOf('GraphQL\Executor\ExecutionResult', $result);
        $this->assertEquals(['data' => ['field' => 'ok']], $result->toArray());
    }

    public function testDebugPhpErrors()
    {
        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'err' => [
                    'type' => Type::string(),
                    'resolve' => function() {
                        trigger_error('notice', E_USER_NOTICE);
                        return 'err';
                    }
                ]
            ]
        ]);

        $server = Server::create()
            ->setDebug(0)
            ->setQueryType($queryType);

        $prevEnabled = \PHPUnit_Framework_Error_Notice::$enabled;
        \PHPUnit_Framework_Error_Notice::$enabled = false;
        $result = @$server->executeQuery('{err}');

        $expected = [
            'data' => ['err' => 'err']
        ];
        $this->assertEquals($expected, $result->toArray());

        $server->setDebug(Server::DEBUG_PHP_ERRORS);
        $result = @$server->executeQuery('{err}');

        $expected = [
            'data' => ['err' => 'err'],
            'extensions' => [
                'phpErrors' => [
                    [
                        'message' => 'notice',
                        'severity' => 1024,
                        // 'trace' => [...]
                    ]
                ]
            ]
        ];

        $this->assertArraySubset($expected, $result->toArray());

        $server->setPhpErrorFormatter(function(\ErrorException $e) {
            return ['test' => $e->getMessage()];
        });

        $result = $server->executeQuery('{err}');
        $expected = [
            'data' => ['err' => 'err'],
            'extensions' => [
                'phpErrors' => [
                    [
                        'test' => 'notice'
                    ]
                ]
            ]
        ];
        $this->assertEquals($expected, $result->toArray());

        \PHPUnit_Framework_Error_Notice::$enabled = $prevEnabled;
    }

    public function testDebugExceptions()
    {
        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'withException' => [
                    'type' => Type::string(),
                    'resolve' => function() {
                        throw new UserError("Error");
                    }
                ]
            ]
        ]);

        $server = Server::create()
            ->setDebug(0)
            ->setQueryType($queryType);

        $result = $server->executeQuery('{withException}');
        $expected = [
            'data' => [
                'withException' => null
            ],
            'errors' => [[
                'message' => 'Error',
                'path' => ['withException'],
                'locations' => [[
                    'line' => 1,
                    'column' => 2
                ]],
            ]]
        ];
        $this->assertArraySubset($expected, $result->toArray());

        $server->setDebug(Server::DEBUG_EXCEPTIONS);
        $server->setExceptionFormatter(function($e) {
            $debug = Debug::INCLUDE_TRACE;
            return FormattedError::createFromException($e, $debug);
        });
        $result = $server->executeQuery('{withException}');

        $expected['errors'][0]['exception'] = ['message' => 'Error', 'trace' => []];
        $this->assertArraySubset($expected, $result->toArray());

        $server->setExceptionFormatter(function(\Exception $e) {
            return ['test' => $e->getMessage()];
        });

        $result = $server->executeQuery('{withException}');
        $expected['errors'][0]['exception'] = ['test' => 'Error'];
        $this->assertArraySubset($expected, $result->toArray());
    }

    public function testHandleRequest()
    {
        $mock = $this->getMockBuilder('GraphQL\Server')
            ->setMethods(['readInput', 'produceOutput'])
            ->getMock()
        ;

        $mock->method('readInput')
            ->will($this->returnValue(json_encode(['query' => '{err}'])));

        $output = null;
        $mock->method('produceOutput')
            ->will($this->returnCallback(function($a1, $a2) use (&$output) {
                $output = func_get_args();
            }));

        /** @var $mock Server */
        $mock->handleRequest();

        $this->assertInternalType('array', $output);
        $this->assertArraySubset(['errors' => [['message' => 'Unexpected Error']]], $output[0]);
        $this->assertEquals(500, $output[1]);

        $output = null;
        $mock->setUnexpectedErrorMessage($newErr = 'Hey! Something went wrong!');
        $mock->setUnexpectedErrorStatus(501);
        $mock->handleRequest();

        $this->assertInternalType('array', $output);
        $this->assertEquals(['errors' => [['message' => $newErr]]], $output[0]);
        $this->assertEquals(501, $output[1]);

        $mock->setQueryType(new ObjectType([
            'name' => 'Query',
            'fields' => [
                'test' => [
                    'type' => Type::string(),
                    'resolve' => function() {
                        return 'ok';
                    }
                ]
            ]
        ]));

        $_REQUEST = ['query' => '{err}'];
        $output = null;
        $mock->handleRequest();
        $this->assertInternalType('array', $output);

        $expectedOutput = [
            ['errors' => [[
                'message' => 'Cannot query field "err" on type "Query".',
                'locations' => [[
                    'line' => 1,
                    'column' => 2
                ]],
                'category' => 'graphql',
            ]]],
            200
        ];

        $this->assertEquals($expectedOutput, $output);

        $output = null;
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_REQUEST = [];
        $mock->handleRequest();

        $this->assertInternalType('array', $output);
        $this->assertEquals($expectedOutput, $output);
    }
}