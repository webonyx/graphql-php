<?php

declare(strict_types=1);

namespace GraphQL\Tests\Server;

use GraphQL\Error\InvariantViolation;
use GraphQL\Server\Helper;
use GraphQL\Server\OperationParams;
use GraphQL\Server\RequestError;
use GraphQL\Tests\Server\Psr7\PsrRequestStub;
use GraphQL\Tests\Server\Psr7\PsrStreamStub;
use PHPUnit\Framework\TestCase;
use function json_decode;
use function json_encode;

class RequestParsingTest extends TestCase
{
    public function testParsesGraphqlRequest() : void
    {
        $query  = '{my query}';
        $parsed = [
            'raw' => $this->parseRawRequest('application/graphql', $query),
            'psr' => $this->parsePsrRequest('application/graphql', $query),
        ];

        foreach ($parsed as $source => $parsedBody) {
            self::assertValidOperationParams($parsedBody, $query, null, null, null, $source);
            self::assertFalse($parsedBody->isReadOnly(), $source);
        }
    }

    /**
     * @param string $contentType
     * @param string $content
     *
     * @return OperationParams|OperationParams[]
     */
    private function parseRawRequest($contentType, $content, string $method = 'POST')
    {
        $_SERVER['CONTENT_TYPE']   = $contentType;
        $_SERVER['REQUEST_METHOD'] = $method;

        $helper = new Helper();

        return $helper->parseHttpRequest(function () use ($content) {
            return $content;
        });
    }

    /**
     * @param string $contentType
     * @param string $content
     *
     * @return OperationParams|OperationParams[]
     */
    private function parsePsrRequest($contentType, $content, string $method = 'POST')
    {
        $psrRequestBody          = new PsrStreamStub();
        $psrRequestBody->content = $content;

        $psrRequest                          = new PsrRequestStub();
        $psrRequest->headers['content-type'] = [$contentType];
        $psrRequest->method                  = $method;
        $psrRequest->body                    = $psrRequestBody;

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
     * @param OperationParams $params
     * @param string          $query
     * @param string          $queryId
     * @param mixed|null      $variables
     * @param string          $operation
     */
    private static function assertValidOperationParams(
        $params,
        $query,
        $queryId = null,
        $variables = null,
        $operation = null,
        $message = ''
    ) {
        self::assertInstanceOf(OperationParams::class, $params, $message);

        self::assertSame($query, $params->query, $message);
        self::assertSame($queryId, $params->queryId, $message);
        self::assertSame($variables, $params->variables, $message);
        self::assertSame($operation, $params->operation, $message);
    }

    public function testParsesUrlencodedRequest() : void
    {
        $query     = '{my query}';
        $variables = ['test' => 1, 'test2' => 2];
        $operation = 'op';

        $post   = [
            'query'         => $query,
            'variables'     => $variables,
            'operationName' => $operation,
        ];
        $parsed = [
            'raw' => $this->parseRawFormUrlencodedRequest($post),
            'psr' => $this->parsePsrFormUrlEncodedRequest($post),
        ];

        foreach ($parsed as $method => $parsedBody) {
            self::assertValidOperationParams($parsedBody, $query, null, $variables, $operation, $method);
            self::assertFalse($parsedBody->isReadOnly(), $method);
        }
    }

    /**
     * @param mixed[] $postValue
     * @return OperationParams|OperationParams[]
     */
    private function parseRawFormUrlencodedRequest($postValue)
    {
        $_SERVER['CONTENT_TYPE']   = 'application/x-www-form-urlencoded';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST                     = $postValue;

        $helper = new Helper();

        return $helper->parseHttpRequest(function () {
            throw new InvariantViolation("Shouldn't read from php://input for urlencoded request");
        });
    }

    /**
     * @param mixed[] $postValue
     * @return OperationParams[]|OperationParams
     */
    private function parsePsrFormUrlEncodedRequest($postValue)
    {
        $psrRequest                          = new PsrRequestStub();
        $psrRequest->headers['content-type'] = ['application/x-www-form-urlencoded'];
        $psrRequest->method                  = 'POST';
        $psrRequest->parsedBody              = $postValue;

        $helper = new Helper();

        return $helper->parsePsrRequest($psrRequest);
    }

    public function testParsesGetRequest() : void
    {
        $query     = '{my query}';
        $variables = ['test' => 1, 'test2' => 2];
        $operation = 'op';

        $get    = [
            'query'         => $query,
            'variables'     => $variables,
            'operationName' => $operation,
        ];
        $parsed = [
            'raw' => $this->parseRawGetRequest($get),
            'psr' => $this->parsePsrGetRequest($get),
        ];

        foreach ($parsed as $method => $parsedBody) {
            self::assertValidOperationParams($parsedBody, $query, null, $variables, $operation, $method);
            self::assertTrue($parsedBody->isReadonly(), $method);
        }
    }

    /**
     * @param mixed[] $getValue
     * @return OperationParams
     */
    private function parseRawGetRequest($getValue)
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET                      = $getValue;

        $helper = new Helper();

        return $helper->parseHttpRequest(function () {
            throw new InvariantViolation("Shouldn't read from php://input for urlencoded request");
        });
    }

    /**
     * @param mixed[] $getValue
     * @return OperationParams[]|OperationParams
     */
    private function parsePsrGetRequest($getValue)
    {
        $psrRequest              = new PsrRequestStub();
        $psrRequest->method      = 'GET';
        $psrRequest->queryParams = $getValue;

        $helper = new Helper();

        return $helper->parsePsrRequest($psrRequest);
    }

    public function testParsesMultipartFormdataRequest() : void
    {
        $query     = '{my query}';
        $variables = ['test' => 1, 'test2' => 2];
        $operation = 'op';

        $post   = [
            'query'         => $query,
            'variables'     => $variables,
            'operationName' => $operation,
        ];
        $parsed = [
            'raw' => $this->parseRawMultipartFormdataRequest($post),
            'psr' => $this->parsePsrMultipartFormdataRequest($post),
        ];

        foreach ($parsed as $method => $parsedBody) {
            self::assertValidOperationParams($parsedBody, $query, null, $variables, $operation, $method);
            self::assertFalse($parsedBody->isReadOnly(), $method);
        }
    }

    /**
     * @param mixed[] $postValue
     * @return OperationParams|OperationParams[]
     */
    private function parseRawMultipartFormDataRequest($postValue)
    {
        $_SERVER['CONTENT_TYPE']   = 'multipart/form-data; boundary=----FormBoundary';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST                     = $postValue;

        $helper = new Helper();

        return $helper->parseHttpRequest(function () {
            throw new InvariantViolation("Shouldn't read from php://input for multipart/form-data request");
        });
    }

    /**
     * @param mixed[] $postValue
     * @return OperationParams|OperationParams[]
     */
    private function parsePsrMultipartFormDataRequest($postValue)
    {
        $psrRequest                          = new PsrRequestStub();
        $psrRequest->headers['content-type'] = ['multipart/form-data; boundary=----FormBoundary'];
        $psrRequest->method                  = 'POST';
        $psrRequest->parsedBody              = $postValue;

        $helper = new Helper();

        return $helper->parsePsrRequest($psrRequest);
    }

    public function testParsesJSONRequest() : void
    {
        $query     = '{my query}';
        $variables = ['test' => 1, 'test2' => 2];
        $operation = 'op';

        $body   = [
            'query'         => $query,
            'variables'     => $variables,
            'operationName' => $operation,
        ];
        $parsed = [
            'raw' => $this->parseRawRequest('application/json', json_encode($body)),
            'psr' => $this->parsePsrRequest('application/json', json_encode($body)),
        ];
        foreach ($parsed as $method => $parsedBody) {
            self::assertValidOperationParams($parsedBody, $query, null, $variables, $operation, $method);
            self::assertFalse($parsedBody->isReadOnly(), $method);
        }
    }

    public function testParsesVariablesAsJSON() : void
    {
        $query     = '{my query}';
        $variables = ['test' => 1, 'test2' => 2];
        $operation = 'op';

        $body   = [
            'query'         => $query,
            'variables'     => json_encode($variables),
            'operationName' => $operation,
        ];
        $parsed = [
            'raw' => $this->parseRawRequest('application/json', json_encode($body)),
            'psr' => $this->parsePsrRequest('application/json', json_encode($body)),
        ];
        foreach ($parsed as $method => $parsedBody) {
            self::assertValidOperationParams($parsedBody, $query, null, $variables, $operation, $method);
            self::assertFalse($parsedBody->isReadOnly(), $method);
        }
    }

    public function testIgnoresInvalidVariablesJson() : void
    {
        $query     = '{my query}';
        $variables = '"some invalid json';
        $operation = 'op';

        $body   = [
            'query'         => $query,
            'variables'     => $variables,
            'operationName' => $operation,
        ];
        $parsed = [
            'raw' => $this->parseRawRequest('application/json', json_encode($body)),
            'psr' => $this->parsePsrRequest('application/json', json_encode($body)),
        ];
        foreach ($parsed as $method => $parsedBody) {
            self::assertValidOperationParams($parsedBody, $query, null, $variables, $operation, $method);
            self::assertFalse($parsedBody->isReadOnly(), $method);
        }
    }

    public function testParsesBatchJSONRequest() : void
    {
        $body   = [
            [
                'query'         => '{my query}',
                'variables'     => ['test' => 1, 'test2' => 2],
                'operationName' => 'op',
            ],
            [
                'queryId'       => 'my-query-id',
                'variables'     => ['test' => 1, 'test2' => 2],
                'operationName' => 'op2',
            ],
        ];
        $parsed = [
            'raw' => $this->parseRawRequest('application/json', json_encode($body)),
            'psr' => $this->parsePsrRequest('application/json', json_encode($body)),
        ];
        foreach ($parsed as $method => $parsedBody) {
            self::assertInternalType('array', $parsedBody, $method);
            self::assertCount(2, $parsedBody, $method);
            self::assertValidOperationParams(
                $parsedBody[0],
                $body[0]['query'],
                null,
                $body[0]['variables'],
                $body[0]['operationName'],
                $method
            );
            self::assertValidOperationParams(
                $parsedBody[1],
                null,
                $body[1]['queryId'],
                $body[1]['variables'],
                $body[1]['operationName'],
                $method
            );
        }
    }

    public function testFailsParsingInvalidRawJsonRequestRaw() : void
    {
        $body = 'not really{} a json';

        $this->expectException(RequestError::class);
        $this->expectExceptionMessage('Could not parse JSON: Syntax error');
        $this->parseRawRequest('application/json', $body);
    }

    public function testFailsParsingInvalidRawJsonRequestPsr() : void
    {
        $body = 'not really{} a json';

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage('PSR-7 request is expected to provide parsed body for "application/json" requests but got null');
        $this->parsePsrRequest('application/json', $body);
    }

    public function testFailsParsingNonPreParsedPsrRequest() : void
    {
        try {
            $this->parsePsrRequest('application/json', json_encode(null));
            $this->fail('Expected exception not thrown');
        } catch (InvariantViolation $e) {
            // Expecting parsing exception to be thrown somewhere else:
            self::assertEquals(
                'PSR-7 request is expected to provide parsed body for "application/json" requests but got null',
                $e->getMessage()
            );
        }
    }

    /**
     * There is no equivalent for psr request, because it should throw
     */
    public function testFailsParsingNonArrayOrObjectJsonRequestRaw() : void
    {
        $body = '"str"';

        $this->expectException(RequestError::class);
        $this->expectExceptionMessage('GraphQL Server expects JSON object or array, but got "str"');
        $this->parseRawRequest('application/json', $body);
    }

    public function testFailsParsingNonArrayOrObjectJsonRequestPsr() : void
    {
        $body = '"str"';

        $this->expectException(RequestError::class);
        $this->expectExceptionMessage('GraphQL Server expects JSON object or array, but got "str"');
        $this->parsePsrRequest('application/json', $body);
    }

    public function testFailsParsingInvalidContentTypeRaw() : void
    {
        $contentType = 'not-supported-content-type';
        $body        = 'test';

        $this->expectException(RequestError::class);
        $this->expectExceptionMessage('Unexpected content type: "not-supported-content-type"');
        $this->parseRawRequest($contentType, $body);
    }

    public function testFailsParsingInvalidContentTypePsr() : void
    {
        $contentType = 'not-supported-content-type';
        $body        = 'test';

        $this->expectException(RequestError::class);
        $this->expectExceptionMessage('Unexpected content type: "not-supported-content-type"');
        $this->parseRawRequest($contentType, $body);
    }

    public function testFailsWithMissingContentTypeRaw() : void
    {
        $this->expectException(RequestError::class);
        $this->expectExceptionMessage('Missing "Content-Type" header');
        $this->parseRawRequest(null, 'test');
    }

    public function testFailsWithMissingContentTypePsr() : void
    {
        $this->expectException(RequestError::class);
        $this->expectExceptionMessage('Missing "Content-Type" header');
        $this->parsePsrRequest(null, 'test');
    }

    public function testFailsOnMethodsOtherThanPostOrGetRaw() : void
    {
        $this->expectException(RequestError::class);
        $this->expectExceptionMessage('HTTP Method "PUT" is not supported');
        $this->parseRawRequest('application/json', json_encode([]), 'PUT');
    }

    public function testFailsOnMethodsOtherThanPostOrGetPsr() : void
    {
        $this->expectException(RequestError::class);
        $this->expectExceptionMessage('HTTP Method "PUT" is not supported');
        $this->parsePsrRequest('application/json', json_encode([]), 'PUT');
    }
}
