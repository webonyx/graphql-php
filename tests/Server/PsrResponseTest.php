<?php

declare(strict_types=1);

namespace GraphQL\Tests\Server;

use GraphQL\Executor\ExecutionResult;
use GraphQL\Server\Helper;
use GraphQL\Tests\Server\Psr7\PsrResponseStub;
use GraphQL\Tests\Server\Psr7\PsrStreamStub;
use PHPUnit\Framework\TestCase;
use function json_encode;

class PsrResponseTest extends TestCase
{
    public function testConvertsResultToPsrResponse() : void
    {
        $result      = new ExecutionResult(['key' => 'value']);
        $stream      = new PsrStreamStub();
        $psrResponse = new PsrResponseStub();

        $helper = new Helper();

        /** @var PsrResponseStub $resp */
        $resp = $helper->toPsrResponse($result, $psrResponse, $stream);
        self::assertSame(json_encode($result), $resp->body->content);
        self::assertSame(['Content-Type' => ['application/json']], $resp->headers);
    }
}
