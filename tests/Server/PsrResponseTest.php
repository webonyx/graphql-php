<?php

declare(strict_types=1);

namespace GraphQL\Tests\Server;

use GraphQL\Executor\ExecutionResult;
use GraphQL\Server\Helper;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;

use function json_encode;

final class PsrResponseTest extends TestCase
{
    public function testConvertsResultToPsrResponse(): void
    {
        $result      = new ExecutionResult(['key' => 'value']);
        $stream      = Stream::create();
        $psrResponse = new Response();

        $helper = new Helper();

        $resp = $helper->toPsrResponse($result, $psrResponse, $stream);
        self::assertSame(json_encode($result), (string) $resp->getBody());
        self::assertSame(['Content-Type' => ['application/json']], $resp->getHeaders());
    }
}
