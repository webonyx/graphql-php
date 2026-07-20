<?php declare(strict_types=1);

namespace GraphQL\Tests;

use GraphQL\Utils\Utils;
use PHPUnit\Framework\TestCase;

final class UtilsTest extends TestCase
{
    /** @dataProvider chrUtf8DataProvider */
    public function testChrUtf8Generation(int $input, string $expected): void
    {
        $result = Utils::chr($input);

        self::assertTrue(mb_check_encoding($result, 'UTF-8'));
        self::assertSame($expected, $result);
    }

    /** @return iterable<array{input: int, expected: string}> */
    public static function chrUtf8DataProvider(): iterable
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

    /**
     * @dataProvider printSafeJsonDataProvider
     *
     * @param mixed $value
     */
    public function testPrintSafeJson(string $expected, $value): void
    {
        self::assertSame($expected, Utils::printSafeJson($value));
    }

    /** @return iterable<array{expected: string, value: mixed}> */
    public static function printSafeJsonDataProvider(): iterable
    {
        yield 'stdClass' => [
            'expected' => '{"foo":1}',
            'value' => (object) ['foo' => 1],
        ];

        yield 'invalid stdClass' => [
            'expected' => "O:8:\"stdClass\":1:{s:12:\"invalid utf8\";s:2:\"\xB1\x31\";}",
            'value' => (object) ['invalid utf8' => "\xB1\x31"],
        ];

        yield 'empty string' => [
            'expected' => '(empty string)',
            'value' => '',
        ];
    }
}
