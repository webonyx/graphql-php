<?php

declare(strict_types=1);

namespace GraphQL\Tests;

use GraphQL\Utils\Utils;
use PHPUnit\Framework\TestCase;

use function mb_check_encoding;

class UtilsTest extends TestCase
{
    /**
     * @param int    $input
     * @param string $expected
     *
     * @dataProvider    chrUtf8DataProvider
     */
    public function testChrUtf8Generation($input, $expected): void
    {
        $result = Utils::chr($input);
        self::assertTrue(mb_check_encoding($result, 'UTF-8'));
        self::assertEquals($expected, $result);
    }

    public function chrUtf8DataProvider()
    {
        return [
            'alphabet' => [
                'input' => 0x0061,
                'expected' => 'a',
            ],
            'numeric' => [
                'input' => 0x0030,
                'expected' => '0',
            ],
            'between 128 and 256' => [
                'input' => 0x00E9,
                'expected' => 'é',
            ],
            'emoji' => [
                'input' => 0x231A,
                'expected' => '⌚',
            ],
        ];
    }
}
