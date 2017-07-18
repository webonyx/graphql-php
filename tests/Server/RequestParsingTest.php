<?php
namespace GraphQL\Tests\Server;

use GraphQL\Error\InvariantViolation;
use GraphQL\Error\UserError;
use GraphQL\Server\Helper;
use GraphQL\Server\OperationParams;

class RequestParsingTest extends \PHPUnit_Framework_TestCase
{
    public function testParsesGraphqlRequest()
    {
        $query = '{my query}';
        $parsedBody = $this->parseRawRequest('application/graphql', $query);

        $this->assertValidOperationParams($parsedBody, $query);
        $this->assertFalse($parsedBody->isReadOnly());
    }

    public function testParsesUrlencodedRequest()
    {
        $query = '{my query}';
        $variables = ['test' => 1, 'test2' => 2];
        $operation = 'op';

        $post = [
            'query' => $query,
            'variables' => $variables,
            'operation' => $operation
        ];

        $parsedBody = $this->parseFormUrlencodedRequest($post);
        $this->assertValidOperationParams($parsedBody, $query, null, $variables, $operation);
        $this->assertFalse($parsedBody->isReadOnly());
    }

    public function testParsesGetRequest()
    {
        $query = '{my query}';
        $variables = ['test' => 1, 'test2' => 2];
        $operation = 'op';

        $get = [
            'query' => $query,
            'variables' => $variables,
            'operation' => $operation
        ];

        $parsedBody = $this->parseGetRequest($get);
        $this->assertValidOperationParams($parsedBody, $query, null, $variables, $operation);
        $this->assertTrue($parsedBody->isReadonly());
    }

    public function testParsesJSONRequest()
    {
        $query = '{my query}';
        $variables = ['test' => 1, 'test2' => 2];
        $operation = 'op';

        $body = [
            'query' => $query,
            'variables' => $variables,
            'operation' => $operation
        ];
        $parsedBody = $this->parseRawRequest('application/json', json_encode($body));
        $this->assertValidOperationParams($parsedBody, $query, null, $variables, $operation);
        $this->assertFalse($parsedBody->isReadOnly());
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

        $parsedBody = $this->parseRawRequest('application/json', json_encode($body));
        $this->assertInternalType('array', $parsedBody);
        $this->assertCount(2, $parsedBody);

        $this->assertValidOperationParams($parsedBody[0], $body[0]['query'], null, $body[0]['variables'], $body[0]['operation']);
        $this->assertValidOperationParams($parsedBody[1], null, $body[1]['queryId'], $body[1]['variables'], $body[1]['operation']);

        // Batched queries must be read-only (do not allow batched mutations)
        $this->assertTrue($parsedBody[0]->isReadOnly());
        $this->assertTrue($parsedBody[1]->isReadOnly());
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
        return $helper->parseHttpRequest(function() use ($content) {
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
        return $helper->parseHttpRequest(function() {
            throw new InvariantViolation("Shouldn't read from php://input for urlencoded request");
        });
    }

    /**
     * @param $getValue
     * @return OperationParams
     */
    private function parseGetRequest($getValue)
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = $getValue;

        $helper = new Helper();
        return $helper->parseHttpRequest(function() {
            throw new InvariantViolation("Shouldn't read from php://input for urlencoded request");
        });
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
