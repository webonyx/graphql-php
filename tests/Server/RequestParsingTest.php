<?php
namespace GraphQL\Tests\Server;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Server\Helper;
use GraphQL\Server\OperationParams;
use GraphQL\Server\RequestError;
use GraphQL\Tests\Server\Psr7\PsrRequestStub;
use GraphQL\Tests\Server\Psr7\PsrStreamStub;

class RequestParsingTest extends \PHPUnit_Framework_TestCase
{
    public function testParsesGraphqlRequest()
    {
        $query = '{my query}';
        $parsed = [
            'raw' => $this->parseRawRequest('application/graphql', $query),
            'psr' => $this->parsePsrRequest('application/graphql', $query)
        ];

        foreach ($parsed as $source => $parsedBody) {
            $this->assertValidOperationParams($parsedBody, $query, null, null, null, $source);
            $this->assertFalse($parsedBody->isReadOnly(), $source);
        }
    }

    public function testParsesUrlencodedRequest()
    {
        $query = '{my query}';
        $variables = ['test' => 1, 'test2' => 2];
        $operation = 'op';

        $post = [
            'query' => $query,
            'variables' => $variables,
            'operationName' => $operation
        ];
        $parsed = [
            'raw' => $this->parseRawFormUrlencodedRequest($post),
            'psr' => $this->parsePsrFormUrlEncodedRequest($post)
        ];

        foreach ($parsed as $method => $parsedBody) {
            $this->assertValidOperationParams($parsedBody, $query, null, $variables, $operation, $method);
            $this->assertFalse($parsedBody->isReadOnly(), $method);
        }
    }

    public function testParsesGetRequest()
    {
        $query = '{my query}';
        $variables = ['test' => 1, 'test2' => 2];
        $operation = 'op';

        $get = [
            'query' => $query,
            'variables' => $variables,
            'operationName' => $operation
        ];
        $parsed = [
            'raw' => $this->parseRawGetRequest($get),
            'psr' => $this->parsePsrGetRequest($get)
        ];

        foreach ($parsed as $method => $parsedBody) {
            $this->assertValidOperationParams($parsedBody, $query, null, $variables, $operation, $method);
            $this->assertTrue($parsedBody->isReadonly(), $method);
        }
    }

    public function testParsesMultipartFormdataRequest()
    {
        $query = '{my query}';
        $variables = ['test' => 1, 'test2' => 2];
        $operation = 'op';

        $post = [
            'query' => $query,
            'variables' => $variables,
            'operationName' => $operation
        ];
        $parsed = [
            'raw' => $this->parseRawMultipartFormdataRequest($post),
            'psr' => $this->parsePsrMultipartFormdataRequest($post)
        ];

        foreach ($parsed as $method => $parsedBody) {
            $this->assertValidOperationParams($parsedBody, $query, null, $variables, $operation, $method);
            $this->assertFalse($parsedBody->isReadOnly(), $method);
        }
    }

    public function testParsesJSONRequest()
    {
        $query = '{my query}';
        $variables = ['test' => 1, 'test2' => 2];
        $operation = 'op';

        $body = [
            'query' => $query,
            'variables' => $variables,
            'operationName' => $operation
        ];
        $parsed = [
            'raw' => $this->parseRawRequest('application/json', json_encode($body)),
            'psr' => $this->parsePsrRequest('application/json', json_encode($body))
        ];
        foreach ($parsed as $method => $parsedBody) {
            $this->assertValidOperationParams($parsedBody, $query, null, $variables, $operation, $method);
            $this->assertFalse($parsedBody->isReadOnly(), $method);
        }
    }

    public function testParsesVariablesAsJSON()
    {
        $query = '{my query}';
        $variables = ['test' => 1, 'test2' => 2];
        $operation = 'op';

        $body = [
            'query' => $query,
            'variables' => json_encode($variables),
            'operationName' => $operation
        ];
        $parsed = [
            'raw' => $this->parseRawRequest('application/json', json_encode($body)),
            'psr' => $this->parsePsrRequest('application/json', json_encode($body))
        ];
        foreach ($parsed as $method => $parsedBody) {
            $this->assertValidOperationParams($parsedBody, $query, null, $variables, $operation, $method);
            $this->assertFalse($parsedBody->isReadOnly(), $method);
        }
    }

    public function testIgnoresInvalidVariablesJson()
    {
        $query = '{my query}';
        $variables = '"some invalid json';
        $operation = 'op';

        $body = [
            'query' => $query,
            'variables' => $variables,
            'operationName' => $operation
        ];
        $parsed = [
            'raw' => $this->parseRawRequest('application/json', json_encode($body)),
            'psr' => $this->parsePsrRequest('application/json', json_encode($body)),
        ];
        foreach ($parsed as $method => $parsedBody) {
            $this->assertValidOperationParams($parsedBody, $query, null, $variables, $operation, $method);
            $this->assertFalse($parsedBody->isReadOnly(), $method);
        }
    }

    public function testParsesBatchJSONRequest()
    {
        $body = [
            [
                'query' => '{my query}',
                'variables' => ['test' => 1, 'test2' => 2],
                'operationName' => 'op'
            ],
            [
                'queryId' => 'my-query-id',
                'variables' => ['test' => 1, 'test2' => 2],
                'operationName' => 'op2'
            ],
        ];
        $parsed = [
            'raw' => $this->parseRawRequest('application/json', json_encode($body)),
            'psr' => $this->parsePsrRequest('application/json', json_encode($body))
        ];
        foreach ($parsed as $method => $parsedBody) {
            $this->assertInternalType('array', $parsedBody, $method);
            $this->assertCount(2, $parsedBody, $method);
            $this->assertValidOperationParams($parsedBody[0], $body[0]['query'], null, $body[0]['variables'], $body[0]['operationName'], $method);
            $this->assertValidOperationParams($parsedBody[1], null, $body[1]['queryId'], $body[1]['variables'], $body[1]['operationName'], $method);
        }
    }

    public function testFailsParsingInvalidRawJsonRequestRaw()
    {
        $body = 'not really{} a json';

        $this->setExpectedException(RequestError::class, 'Could not parse JSON: Syntax error');
            $this->parseRawRequest('application/json', $body);
        }

    public function testFailsParsingInvalidRawJsonRequestPsr()
    {
        $body = 'not really{} a json';

        $this->setExpectedException(InvariantViolation::class, 'PSR-7 request is expected to provide parsed body for "application/json" requests but got null');
            $this->parsePsrRequest('application/json', $body);
    }

    public function testFailsParsingNonPreParsedPsrRequest()
    {
        try {
            $this->parsePsrRequest('application/json', json_encode(null));
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            // Expecting parsing exception to be thrown somewhere else:
            $this->assertEquals(
                'PSR-7 request is expected to provide parsed body for "application/json" requests but got null',
                $e->getMessage()
            );
        }
    }

    // There is no equivalent for psr request, because it should throw

    public function testFailsParsingNonArrayOrObjectJsonRequestRaw()
    {
        $body = '"str"';

        $this->setExpectedException(RequestError::class, 'GraphQL Server expects JSON object or array, but got "str"');
            $this->parseRawRequest('application/json', $body);
        }

    public function testFailsParsingNonArrayOrObjectJsonRequestPsr()
    {
        $body = '"str"';

        $this->setExpectedException(RequestError::class, 'GraphQL Server expects JSON object or array, but got "str"');
            $this->parsePsrRequest('application/json', $body);
        }

    public function testFailsParsingInvalidContentTypeRaw()
    {
        $contentType = 'not-supported-content-type';
        $body = 'test';

        $this->setExpectedException(RequestError::class, 'Unexpected content type: "not-supported-content-type"');
        $this->parseRawRequest($contentType, $body);
    }

    public function testFailsParsingInvalidContentTypePsr()
    {
        $contentType = 'not-supported-content-type';
        $body = 'test';

        $this->setExpectedException(RequestError::class, 'Unexpected content type: "not-supported-content-type"');
            $this->parseRawRequest($contentType, $body);
        }

    public function testFailsWithMissingContentTypeRaw()
    {
        $this->setExpectedException(RequestError::class, 'Missing "Content-Type" header');
            $this->parseRawRequest(null, 'test');
        }

    public function testFailsWithMissingContentTypePsr()
    {
        $this->setExpectedException(RequestError::class, 'Missing "Content-Type" header');
            $this->parsePsrRequest(null, 'test');
    }

    public function testFailsOnMethodsOtherThanPostOrGetRaw()
    {
        $this->setExpectedException(RequestError::class, 'HTTP Method "PUT" is not supported');
        $this->parseRawRequest('application/json', json_encode([]), "PUT");
    }

    public function testFailsOnMethodsOtherThanPostOrGetPsr()
    {
        $this->setExpectedException(RequestError::class, 'HTTP Method "PUT" is not supported');
        $this->parsePsrRequest('application/json', json_encode([]), "PUT");
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
     * @param string $contentType
     * @param string $content
     * @param $method
     *
     * @return OperationParams|OperationParams[]
     */
    private function parsePsrRequest($contentType, $content, $method = 'POST')
    {
        $psrRequestBody = new PsrStreamStub();
        $psrRequestBody->content = $content;

        $psrRequest = new PsrRequestStub();
        $psrRequest->headers['content-type'] = [$contentType];
        $psrRequest->method = $method;
        $psrRequest->body = $psrRequestBody;

        if ($contentType === 'application/json') {
            $parsedBody = json_decode($content, true);
            $parsedBody = $parsedBody === false ? null : $parsedBody;
        } else {
            $parsedBody = null;
        }

        $psrRequest->parsedBody = $parsedBody;

        $helper = new Helper();
        return $helper->parsePsrRequest($psrRequest);
    }

    /**
     * @param array $postValue
     * @return OperationParams|OperationParams[]
     */
    private function parseRawFormUrlencodedRequest($postValue)
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
     * @param $postValue
     * @return array|Helper
     */
    private function parsePsrFormUrlEncodedRequest($postValue)
    {
        $psrRequest = new PsrRequestStub();
        $psrRequest->headers['content-type'] = ['application/x-www-form-urlencoded'];
        $psrRequest->method = 'POST';
        $psrRequest->parsedBody = $postValue;

        $helper = new Helper();
        return $helper->parsePsrRequest($psrRequest);
    }

    /**
     * @param array $postValue
     * @return OperationParams|OperationParams[]
     */
    private function parseRawMultipartFormDataRequest($postValue)
    {
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=----FormBoundary';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $postValue;

        $helper = new Helper();
        return $helper->parseHttpRequest(function() {
            throw new InvariantViolation("Shouldn't read from php://input for multipart/form-data request");
        });
    }

    /**
     * @param $postValue
     * @return array|Helper
     */
    private function parsePsrMultipartFormDataRequest($postValue)
    {
        $psrRequest = new PsrRequestStub();
        $psrRequest->headers['content-type'] = ['multipart/form-data; boundary=----FormBoundary'];
        $psrRequest->method = 'POST';
        $psrRequest->parsedBody = $postValue;

        $helper = new Helper();
        return $helper->parsePsrRequest($psrRequest);
    }

    /**
     * @param $getValue
     * @return OperationParams
     */
    private function parseRawGetRequest($getValue)
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = $getValue;

        $helper = new Helper();
        return $helper->parseHttpRequest(function() {
            throw new InvariantViolation("Shouldn't read from php://input for urlencoded request");
        });
    }

    /**
     * @param $getValue
     * @return array|Helper
     */
    private function parsePsrGetRequest($getValue)
    {
        $psrRequest = new PsrRequestStub();
        $psrRequest->method = 'GET';
        $psrRequest->queryParams = $getValue;

        $helper = new Helper();
        return $helper->parsePsrRequest($psrRequest);
    }

    /**
     * @param OperationParams $params
     * @param string $query
     * @param string $queryId
     * @param array $variables
     * @param string $operation
     */
    private function assertValidOperationParams($params, $query, $queryId = null, $variables = null, $operation = null, $message = '')
    {
        $this->assertInstanceOf(OperationParams::class, $params, $message);

        $this->assertSame($query, $params->query, $message);
        $this->assertSame($queryId, $params->queryId, $message);
        $this->assertSame($variables, $params->variables, $message);
        $this->assertSame($operation, $params->operation, $message);
    }
}
