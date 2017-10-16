<?php
namespace GraphQL\Tests\Server;

use GraphQL\Executor\ExecutionResult;
use GraphQL\Server\Helper;
use GraphQL\Tests\Server\Psr7\PsrStreamStub;
use GraphQL\Tests\Server\Psr7\PsrResponseStub;

class PsrResponseTest extends \PHPUnit_Framework_TestCase
{
    public function testConvertsResultToPsrResponse()
    {
        $result = new ExecutionResult(['key' => 'value']);
        $stream = new PsrStreamStub();
        $psrResponse = new PsrResponseStub();

        $helper = new Helper();

        /** @var PsrResponseStub $resp */
        $resp = $helper->toPsrResponse($result, $psrResponse, $stream);
        $this->assertSame(json_encode($result), $resp->body->content);
        $this->assertSame(['Content-Type' => ['application/json']], $resp->headers);
    }
}
