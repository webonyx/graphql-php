<?php declare(strict_types=1);

namespace GraphQL\Tests\Server;

use PHPUnit\Framework\TestCase;
use GraphQL\Server\Helper;

final class HelperTest extends TestCase {

    /**
     * @runInSeparateProcess
     */
    public function testSendResponseWithUtf8Support(): void
    {
        $helper = new Helper();

        $this->expectOutputString('{"data":{"name":"Петя"}}');
        $helper->sendResponse([
            'data' => [
                'name' => 'Петя'
            ]
        ]);
    }
}
