<?php
namespace GraphQL\Tests;

use GraphQL\Error\InvariantViolation;
use GraphQL\Server;
use GraphQL\Type\Definition\Directive;
use GraphQL\Validator\DocumentValidator;

class ServerTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaults()
    {
        $server = new Server();
        $this->assertEquals(null, $server->getQueryType());
        $this->assertEquals(null, $server->getMutationType());
        $this->assertEquals(null, $server->getSubscriptionType());
        $this->assertEquals(null, $server->getContext());
        $this->assertEquals(null, $server->getRootValue());
        $this->assertEquals(0, $server->getDebug());
        $this->assertEquals(Directive::getInternalDirectives(), $server->getDirectives());
        $this->assertEquals(['GraphQL\Error\FormattedError', 'createFromException'], $server->getExceptionFormatter());
        $this->assertEquals(['GraphQL\Error\FormattedError', 'createFromPHPError'], $server->getPhpErrorFormatter());
        $this->assertEquals(null, $server->getPromiseAdapter());
        $this->assertEquals(null, $server->getTypeResolutionStrategy());
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
}
