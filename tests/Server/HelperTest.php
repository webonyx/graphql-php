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
        $dataArray = [
            'data' => [
                'user' => 'Петя'
            ]
        ];
        
        $expected = json_encode($dataArray, JSON_UNESCAPED_UNICODE);

        $helper = new Helper();
        $helper->sendResponse($dataArray);

        $this->expectOutputString($expected);
    }
}
