<?php

declare(strict_types=1);

namespace GraphQL\Tests\TestUtils;

use function preg_match;
use function preg_replace;
use function trim;

class Str
{
    /**
     * Fixes indentation. Also removes leading newlines
     * and trailing spaces and tabs, but keeps trailing newlines.
     *
     * @example
     * $str = Str::dedent('
     *   {
     *     test
     *   }
     * ');
     * $str === "{\n  test\n}\n";
     */
    public static function dedent(string $string): string
    {
        $trimmedString = trim($string, "\n");
        $trimmedString = preg_replace('/[ \t]*$/', '', $trimmedString);
        preg_match('/^[ \t]*/', $trimmedString, $indentMatch);
        $indent = $indentMatch[0];

        return preg_replace('/^' . $indent . '/m', '', $trimmedString);
    }
}
