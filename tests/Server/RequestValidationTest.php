<?php
namespace GraphQL\Tests\Server;

use GraphQL\Error\InvariantViolation;
use GraphQL\Error\UserError;
use GraphQL\Server\Helper;
use GraphQL\Server\OperationParams;

class RequestValidationTest extends \PHPUnit_Framework_TestCase
{
    public function testSimpleRequestShouldValidate()
    {
        $query = '{my q}';
        $variables = ['a' => 'b', 'c' => 'd'];
        $operation = 'op';

        $parsedBody = OperationParams::create([
            'query' => $query,
            'variables' => $variables,
            'operation' => $operation,
        ]);

        $this->assertValid($parsedBody);
    }

    public function testRequestWithQueryIdShouldValidate()
    {
        $queryId = 'some-query-id';
        $variables = ['a' => 'b', 'c' => 'd'];
        $operation = 'op';

        $parsedBody = OperationParams::create([
            'queryId' => $queryId,
            'variables' => $variables,
            'operation' => $operation,
        ]);

        $this->assertValid($parsedBody);
    }

    public function testBatchRequestShouldValidate()
    {
        $query = '{my q}';
        $queryId = 'some-query-id';
        $variables = ['a' => 'b', 'c' => 'd'];
        $operation = 'op';

        $parsedBody = [
            OperationParams::create([
                'query' => $query,
                'variables' => $variables,
                'operation' => $operation,
            ]),
            OperationParams::create([
                'queryId' => $queryId,
                'variables' => [],
                'operation' => null
            ]),
        ];

        $this->assertValid($parsedBody);
    }

    public function testThrowsOnInvalidRequest()
    {
        $parsedBody = 'str';
        $this->assertInternalError(
            $parsedBody,
            'GraphQL Server: Parsed http request must be an instance of GraphQL\Server\OperationParams or array of such instances, but got "str"'
        );

        $parsedBody = ['str'];
        $this->assertInternalError(
            $parsedBody,
            'GraphQL Server: Parsed http request must be an instance of GraphQL\Server\OperationParams or array of such instances, but got invalid array where entry at position 0 is "str"'
        );
    }

    public function testRequiresQueryOrQueryId()
    {
        $parsedBody = OperationParams::create([
            'variables' => ['foo' => 'bar'],
            'operation' => 'op',
        ]);

        $this->assertInputError(
            $parsedBody,
            'GraphQL Request must include at least one of those two parameters: "query" or "queryId"'
        );
    }

    public function testFailsWhenBothQueryAndQueryIdArePresent()
    {
        $parsedBody = OperationParams::create([
            'query' => '{my query}',
            'queryId' => 'my-query-id',
        ]);

        $this->assertInputError(
            $parsedBody,
            'GraphQL Request parameters "query" and "queryId" are mutually exclusive'
        );
    }

    public function testFailsWhenQueryParameterIsNotString()
    {
        $parsedBody = OperationParams::create([
            'query' => ['t' => '{my query}']
        ]);

        $this->assertInputError(
            $parsedBody,
            'GraphQL Request parameter "query" must be string, but got object with first key: "t"'
        );
    }

    public function testFailsWhenQueryIdParameterIsNotString()
    {
        $parsedBody = OperationParams::create([
            'queryId' => ['t' => '{my query}']
        ]);

        $this->assertInputError(
            $parsedBody,
            'GraphQL Request parameter "queryId" must be string, but got object with first key: "t"'
        );
    }

    public function testFailsWhenOperationParameterIsNotString()
    {
        $parsedBody = OperationParams::create([
            'query' => '{my query}',
            'operation' => []
        ]);

        $this->assertInputError(
            $parsedBody,
            'GraphQL Request parameter "operation" must be string, but got array(0)'
        );
    }

    public function testFailsWhenVariablesParameterIsNotObject()
    {
        $parsedBody = OperationParams::create([
            'query' => '{my query}',
            'variables' => 'test'
        ]);

        $this->assertInputError($parsedBody, 'GraphQL Request parameter "variables" must be object, but got "test"');
    }

    private function assertValid($parsedRequest)
    {
        $helper = new Helper();
        $helper->assertValidRequest($parsedRequest);
    }

    private function assertInputError($parsedRequest, $expectedMessage)
    {
        try {
            $helper = new Helper();
            $helper->assertValidRequest($parsedRequest);
            $this->fail('Expected exception not thrown');
        } catch (UserError $e) {
            $this->assertEquals($expectedMessage, $e->getMessage());
        }
    }

    private function assertInternalError($parsedRequest, $expectedMessage)
    {
        try {
            $helper = new Helper();
            $helper->assertValidRequest($parsedRequest);
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            $this->assertEquals($expectedMessage, $e->getMessage());
        }
    }
}
