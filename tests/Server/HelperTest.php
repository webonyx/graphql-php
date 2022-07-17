<?php declare(strict_types=1);

namespace GraphQL\Tests\Server;

use PHPUnit\Framework\TestCase;
use GraphQL\Server\Helper;
use GraphQL\Executor\ExecutionResult;

final class HelperTest extends TestCase {

    /**
     * @runInSeparateProcess
     */
    public function testSendResponseWithUtf8Support(): void
    {
        $helper = new Helper();
        $result = new ExecutionResult([
            'data' => [
                'name' => 'Петя'
            ]
        ]);

        $this->expectOutputString('{"data":{"name":"Петя"}}');
        $helper->sendResponse($result);
    }
}
