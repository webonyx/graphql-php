<?php

declare(strict_types=1);

namespace GraphQL\Tests\Server;

use GraphQL\Server\Helper;
use GraphQL\Server\OperationParams;
use PHPUnit\Framework\TestCase;

class RequestValidationTest extends TestCase
{
    public function testSimpleRequestShouldValidate() : void
    {
        $query     = '{my q}';
        $variables = ['a' => 'b', 'c' => 'd'];
        $operation = 'op';

        $parsedBody = OperationParams::create([
            'query'         => $query,
            'variables'     => $variables,
            'operationName' => $operation,
        ]);

        self::assertValid($parsedBody);
    }

    private static function assertValid($parsedRequest)
    {
        $helper = new Helper();
        $errors = $helper->validateOperationParams($parsedRequest);
        self::assertEmpty($errors, isset($errors[0]) ? $errors[0]->getMessage() : '');
    }

    public function testRequestWithQueryIdShouldValidate() : void
    {
        $queryId   = 'some-query-id';
        $variables = ['a' => 'b', 'c' => 'd'];
        $operation = 'op';

        $parsedBody = OperationParams::create([
            'queryId'       => $queryId,
            'variables'     => $variables,
            'operationName' => $operation,
        ]);

        self::assertValid($parsedBody);
    }

    public function testRequiresQueryOrQueryId() : void
    {
        $parsedBody = OperationParams::create([
            'variables'     => ['foo' => 'bar'],
            'operationName' => 'op',
        ]);

        $this->assertInputError(
            $parsedBody,
            'GraphQL Request must include at least one of those two parameters: "query" or "queryId"'
        );
    }

    private function assertInputError($parsedRequest, $expectedMessage)
    {
        $helper = new Helper();
        $errors = $helper->validateOperationParams($parsedRequest);
        if (isset($errors[0])) {
            self::assertEquals($expectedMessage, $errors[0]->getMessage());
        } else {
            self::fail('Expected error not returned');
        }
    }

    public function testFailsWhenBothQueryAndQueryIdArePresent() : void
    {
        $parsedBody = OperationParams::create([
            'query'   => '{my query}',
            'queryId' => 'my-query-id',
        ]);

        $this->assertInputError(
            $parsedBody,
            'GraphQL Request parameters "query" and "queryId" are mutually exclusive'
        );
    }

    public function testFailsWhenQueryParameterIsNotString() : void
    {
        $parsedBody = OperationParams::create([
            'query' => ['t' => '{my query}'],
        ]);

        $this->assertInputError(
            $parsedBody,
            'GraphQL Request parameter "query" must be string, a DocumentNode, or an array representation of an AST, but got {"t":"{my query}"}'
        );
    }

    public function testFailsWhenQueryIdParameterIsNotString() : void
    {
        $parsedBody = OperationParams::create([
            'queryId' => ['t' => '{my query}'],
        ]);

        $this->assertInputError(
            $parsedBody,
            'GraphQL Request parameter "queryId" must be string, but got {"t":"{my query}"}'
        );
    }

    public function testFailsWhenOperationParameterIsNotString() : void
    {
        $parsedBody = OperationParams::create([
            'query'         => '{my query}',
            'operationName' => [],
        ]);

        $this->assertInputError(
            $parsedBody,
            'GraphQL Request parameter "operation" must be string, but got []'
        );
    }

    /**
     * @see https://github.com/webonyx/graphql-php/issues/156
     */
    public function testIgnoresNullAndEmptyStringVariables() : void
    {
        $query      = '{my q}';
        $parsedBody = OperationParams::create([
            'query'     => $query,
            'variables' => null,
        ]);
        self::assertValid($parsedBody);

        $variables  = '';
        $parsedBody = OperationParams::create([
            'query'     => $query,
            'variables' => $variables,
        ]);
        self::assertValid($parsedBody);
    }

    public function testFailsWhenVariablesParameterIsNotObject() : void
    {
        $parsedBody = OperationParams::create([
            'query'     => '{my query}',
            'variables' => 0,
        ]);

        $this->assertInputError(
            $parsedBody,
            'GraphQL Request parameter "variables" must be object or JSON string parsed to object, but got 0'
        );
    }
}
