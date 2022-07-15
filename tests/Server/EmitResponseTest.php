<?php declare(strict_types=1);

namespace GraphQL\Tests\Server;

use PHPUnit\Framework\TestCase;

final class EmitResponseTest extends TestCase {

    public function testJsonEncodeWithUtf8Support(): void
    {
        $dataArray = [
            'data' => [
                'user' => 'Петя'
            ]
        ];
        
        $response = json_encode($dataArray);

        $expected = json_encode($dataArray, JSON_UNESCAPED_UNICODE);

        self::assertSame($expected, $response);
    }
}
