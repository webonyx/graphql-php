<?php declare(strict_types=1);

namespace GraphQL\Tests\Server;

use GraphQL\Executor\ExecutionResult;
use GraphQL\Server\Helper;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

use function Safe\json_encode;

final class PsrResponseTest extends TestCase
{
    public function testConvertsResultToPsrResponse(): void
    {
        $result = new ExecutionResult(['key' => 'value']);
        $stream = Stream::create();
        $psrResponse = new Response();

        $response = (new Helper())->toPsrResponse($result, $psrResponse, $stream);

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertSame(json_encode($result), (string) $response->getBody());
        self::assertSame(['Content-Type' => ['application/json']], $response->getHeaders());
    }
}
