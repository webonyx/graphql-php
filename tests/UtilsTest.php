<?php

declare(strict_types=1);

namespace GraphQL\Tests;

use GraphQL\Utils\Utils;
use function mb_check_encoding;
use PHPUnit\Framework\TestCase;

final class UtilsTest extends TestCase
{
    /**
     * @dataProvider chrUtf8DataProvider
     */
    public function testChrUtf8Generation(int $input, string $expected): void
    {
        $result = Utils::chr($input);

        self::assertTrue(mb_check_encoding($result, 'UTF-8'));
        self::assertEquals($expected, $result);
    }

    /**
     * @return iterable<array{input: int, expected: string}>
     */
    public function chrUtf8DataProvider(): iterable
    {
        yield 'alphabet' => [
            'input' => 0x0061,
            'expected' => 'a',
        ];

        yield 'numeric' => [
            'input' => 0x0030,
            'expected' => '0',
        ];

        yield 'between 128 and 256' => [
            'input' => 0x00E9,
            'expected' => 'é',
        ];

        yield 'emoji' => [
            'input' => 0x231A,
            'expected' => '⌚',
        ];
    }

    public function testPrintSafeJson(): void
    {
        self::assertJsonStringEqualsJsonString(
            /** @lang JSON */
            '{"foo":1}',
            Utils::printSafeJson((object) ['foo' => 1])
        );
    }
}
