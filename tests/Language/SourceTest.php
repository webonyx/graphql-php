<?php declare(strict_types=1);

namespace GraphQL\Tests\Language;

use GraphQL\Language\Source;
use GraphQL\Language\SourceLocation;
use PHPUnit\Framework\TestCase;

final class SourceTest extends TestCase
{
    /** @return iterable<array{string, int, SourceLocation}> */
    public static function locations(): iterable
    {
        yield 'single line' => ['ab', 1, new SourceLocation(1, 2)];
        yield 'after ASCII line' => ["ab\ncd", 4, new SourceLocation(2, 2)];
        yield 'after multibyte line' => ["# ä\nxx", 4, new SourceLocation(2, 1)];
        yield 'after several multibyte characters' => ["# äää\nxx", 6, new SourceLocation(2, 1)];
        yield 'multibyte on the same line' => ["ä\nää", 3, new SourceLocation(2, 2)];
        yield 'after CRLF and LF' => ["ä\r\nä\nab", 6, new SourceLocation(3, 2)];
        yield 'after line separator' => ["ä\u{2028}x", 2, new SourceLocation(2, 1)];
    }

    /** @dataProvider locations */
    public function testGetLocation(string $body, int $position, SourceLocation $expected): void
    {
        self::assertEquals($expected, (new Source($body))->getLocation($position));
    }
}
