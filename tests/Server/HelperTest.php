<?php declare(strict_types=1);

namespace GraphQL\Tests\Server;

use GraphQL\Executor\ExecutionResult;
use GraphQL\Server\Helper;
use PHPUnit\Framework\TestCase;

final class HelperTest extends TestCase
{
    /** @runInSeparateProcess */
    public function testSendResponseWithUtf8Support(): void
    {
        $helper = new Helper();
        $result = new ExecutionResult([
            'name' => 'Петя',
        ]);

        $this->expectOutputString('{"data":{"name":"Петя"}}');
        $helper->sendResponse($result);
    }
}
