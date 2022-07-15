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
        $expected = '{"data":{"name":"Петя"}}';
        $this->expectOutputString($expected);

        $helper = new Helper();
        $data = [
            'data' => [
                'name' => 'Петя'
            ]
        ];
        $helper->sendResponse($data);
    }
}
