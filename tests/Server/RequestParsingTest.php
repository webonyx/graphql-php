<?php
namespace GraphQL\Tests\Server;

use GraphQL\Error\UserError;
use GraphQL\Server\Helper;
use GraphQL\Server\OperationParams;

/**
 * @backupGlobals enabled
 */
class RequestParsingTest extends \PHPUnit_Framework_TestCase
{
    public function testParsesSimpleGraphqlRequest()
    {
        $query = '{my query}';
        list ($parsedBody, $isReadonly) = $this->parseRawRequest('application/graphql', $query);

        $this->assertSame(['query' => $query], $parsedBody);
        $this->assertFalse($isReadonly);
    }

    public function testParsesSimpleUrlencodedRequest()
    {
        $query = '{my query}';
        $variables = ['test' => 1, 'test2' => 2];
        $operation = 'op';

        $post = [
            'query' => $query,
            'variables' => $variables,
            'operation' => $operation
        ];

        list ($parsedBody, $isReadonly) = $this->parseFormUrlencodedRequest($post);
        $this->assertSame($post, $parsedBody);
        $this->assertFalse($isReadonly);
    }

    public function testParsesSimpleGETRequest()
    {
        $query = '{my query}';
        $variables = ['test' => 1, 'test2' => 2];
        $operation = 'op';

        $get = [
            'query' => $query,
            'variables' => $variables,
            'operation' => $operation
        ];

        list ($parsedBody, $isReadonly) = $this->parseGetRequest($get);
        $this->assertSame($get, $parsedBody);
        $this->assertTrue($isReadonly);
    }

    public function testParsesSimpleJSONRequest()
    {
        $query = '{my query}';
        $variables = ['test' => 1, 'test2' => 2];
        $operation = 'op';

        $body = [
            'query' => $query,
            'variables' => $variables,
            'operation' => $operation
        ];

        list ($parsedBody, $isReadonly) = $this->parseRawRequest('application/json', json_encode($body));
        $this->assertEquals($body, $parsedBody);
        $this->assertFalse($isReadonly);
    }

    public function testParsesBatchJSONRequest()
    {
        $body = [
            [
                'query' => '{my query}',
                'variables' => ['test' => 1, 'test2' => 2],
                'operation' => 'op'
            ],
            [
                'queryId' => 'my-query-id',
                'variables' => ['test' => 1, 'test2' => 2],
                'operation' => 'op2'
            ],
        ];

        list ($parsedBody, $isReadonly) = $this->parseRawRequest('application/json', json_encode($body));
        $this->assertEquals($body, $parsedBody);
        $this->assertFalse($isReadonly);
    }

    public function testFailsParsingInvalidJsonRequest()
    {
        $body = 'not really{} a json';

        $this->setExpectedException(UserError::class, 'Could not parse JSON: Syntax error');
        $this->parseRawRequest('application/json', $body);
    }

    public function testFailsParsingNonArrayOrObjectJsonRequest()
    {
        $body = '"str"';

        $this->setExpectedException(UserError::class, 'GraphQL Server expects JSON object or array, but got "str"');
        $this->parseRawRequest('application/json', $body);
    }

    public function testFailsParsingInvalidGetRequest()
    {
        $this->setExpectedException(UserError::class, 'Cannot execute GET request without "query" or "queryId" parameter');
        $this->parseGetRequest([]);
    }

    public function testFailsParsingInvalidContentType()
    {
        $this->setExpectedException(UserError::class, 'Unexpected content type: "not-supported-content-type"');
        $this->parseRawRequest('not-supported-content-type', 'test');
    }

    public function testFailsWithMissingContentType()
    {
        $this->setExpectedException(UserError::class, 'Missing "Content-Type" header');
        $this->parseRawRequest(null, 'test');
    }

    public function testFailsOnMethodsOtherThanPostOrGet()
    {
        $this->setExpectedException(UserError::class, 'HTTP Method "PUT" is not supported');
        $this->parseRawRequest(null, 'test', "PUT");
    }

    public function testSimpleRequestShouldPass()
    {
        $query = '{my q}';
        $variables = ['a' => 'b', 'c' => 'd'];
        $operation = 'op';

        $parsedBody = [
            'query' => $query,
            'variables' => $variables,
            'operation' => $operation,
        ];

        $helper = new Helper();
        $params = $helper->toOperationParams($parsedBody, false);
        $this->assertValidOperationParams($params, $query, null, $variables, $operation);
    }

    public function testRequestWithQueryIdShouldPass()
    {
        $queryId = 'some-query-id';
        $variables = ['a' => 'b', 'c' => 'd'];
        $operation = 'op';

        $parsedBody = [
            'queryId' => $queryId,
            'variables' => $variables,
            'operation' => $operation,
        ];

        $helper = new Helper();
        $params = $helper->toOperationParams($parsedBody, false);
        $this->assertValidOperationParams($params, null, $queryId, $variables, $operation);
    }

    public function testProducesCorrectOperationParamsForBatchRequest()
    {
        $query = '{my q}';
        $queryId = 'some-query-id';
        $variables = ['a' => 'b', 'c' => 'd'];
        $operation = 'op';

        $parsedBody = [
            [
                'query' => $query,
                'variables' => $variables,
                'operation' => $operation,
            ],
            [
                'queryId' => $queryId,
                'variables' => [],
                'operation' => null
            ],
        ];

        $helper = new Helper();
        $params = $helper->toOperationParams($parsedBody, false);

        $this->assertTrue(is_array($params));
        $this->assertValidOperationParams($params[0], $query, null, $variables, $operation);
        $this->assertValidOperationParams($params[1], null, $queryId, [], null);
    }

    public function testRequiresQueryOrQueryId()
    {
        $parsedBody = [
            'variables' => ['foo' => 'bar'],
            'operation' => 'op',
        ];

        $helper = new Helper();

        $this->setExpectedException(
            UserError::class,
            'GraphQL Request must include at least one of those two parameters: "query" or "queryId"'
        );
        $helper->toOperationParams($parsedBody, false);
    }

    public function testFailsWhenBothQueryAndQueryIdArePresent()
    {
        $parsedBody = [
            'query' => '{my query}',
            'queryId' => 'my-query-id',
        ];

        $helper = new Helper();

        $this->setExpectedException(
            UserError::class,
            'GraphQL Request parameters "query" and "queryId" are mutually exclusive'
        );
        $helper->toOperationParams($parsedBody, false);
    }

    public function testFailsWhenQueryParameterIsNotString()
    {
        $parsedBody = [
            'query' => ['t' => '{my query}']
        ];

        $helper = new Helper();

        $this->setExpectedException(
            UserError::class,
            'GraphQL Request parameter "query" must be string, but got object with first key: "t"'
        );
        $helper->toOperationParams($parsedBody, false);
    }

    public function testFailsWhenQueryIdParameterIsNotString()
    {
        $parsedBody = [
            'queryId' => ['t' => '{my query}']
        ];

        $helper = new Helper();

        $this->setExpectedException(
            UserError::class,
            'GraphQL Request parameter "queryId" must be string, but got object with first key: "t"'
        );
        $helper->toOperationParams($parsedBody, false);
    }

    public function testFailsWhenOperationParameterIsNotString()
    {
        $parsedBody = [
            'query' => '{my query}',
            'operation' => []
        ];

        $helper = new Helper();

        $this->setExpectedException(
            UserError::class,
            'GraphQL Request parameter "operation" must be string, but got array(0)'
        );
        $helper->toOperationParams($parsedBody, false);
    }

    public function testFailsWhenVariablesParameterIsNotObject()
    {
        $parsedBody = [
            'query' => '{my query}',
            'variables' => 'test'
        ];

        $helper = new Helper();

        $this->setExpectedException(
            UserError::class,
            'GraphQL Request parameter "variables" must be object, but got "test"'
        );
        $helper->toOperationParams($parsedBody, false);
    }

    /**
     * @param string $contentType
     * @param string $content
     * @param $method
     *
     * @return OperationParams|OperationParams[]
     */
    private function parseRawRequest($contentType, $content, $method = 'POST')
    {
        $_SERVER['CONTENT_TYPE'] = $contentType;
        $_SERVER['REQUEST_METHOD'] = $method;

        $helper = new Helper();
        return $helper->parseRawBody(function() use ($content) {
            return $content;
        });
    }

    /**
     * @param array $postValue
     * @return OperationParams|OperationParams[]
     */
    private function parseFormUrlencodedRequest($postValue)
    {
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $postValue;

        $helper = new Helper();
        return $helper->parseRawBody();
    }

    /**
     * @param $getValue
     * @return array
     */
    private function parseGetRequest($getValue)
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = $getValue;

        $helper = new Helper();
        return $helper->parseRawBody();
    }

    /**
     * @param OperationParams $params
     * @param string $query
     * @param string $queryId
     * @param array $variables
     * @param string $operation
     */
    private function assertValidOperationParams($params, $query, $queryId = null, $variables = null, $operation = null)
    {
        $this->assertInstanceOf(OperationParams::class, $params);

        $this->assertSame($query, $params->query);
        $this->assertSame($queryId, $params->queryId);
        $this->assertSame($variables, $params->variables);
        $this->assertSame($operation, $params->operation);
    }
}
