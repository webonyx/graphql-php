<?php

declare(strict_types=1);

namespace GraphQL\Language;

use GraphQL\Utils\Utils;

use function array_slice;
use function count;
use function implode;
use function mb_strlen;
use function mb_substr;
use function preg_split;
use function str_replace;
use function strpos;

class BlockString
{
    /**
     * Produces the value of a block string from its parsed raw value, similar to
     * CoffeeScript's block string, Python's docstring trim or Ruby's strip_heredoc.
     *
     * This implements the GraphQL spec's BlockStringValue() static algorithm.
     */
    public static function dedentValue(string $rawString): string
    {
        // Expand a block string's raw value into independent lines.
        $lines = preg_split("/\\r\\n|[\\n\\r]/", $rawString);

        // Remove common indentation from all lines but first.
        $commonIndent = self::getIndentation($rawString);
        $linesLength  = count($lines);

        if ($commonIndent > 0) {
            for ($i = 1; $i < $linesLength; $i++) {
                $line      = $lines[$i];
                $lines[$i] = mb_substr($line, $commonIndent);
            }
        }

        // Remove leading and trailing blank lines.
        $startLine = 0;
        while ($startLine < $linesLength && self::isBlank($lines[$startLine])) {
            ++$startLine;
        }

        $endLine = $linesLength;
        while ($endLine > $startLine && self::isBlank($lines[$endLine - 1])) {
            --$endLine;
        }

        // Return a string of the lines joined with U+000A.
        return implode("\n", array_slice($lines, $startLine, $endLine - $startLine));
    }

    private static function isBlank(string $str): bool
    {
        $strLength = mb_strlen($str);
        for ($i = 0; $i < $strLength; ++$i) {
            if ($str[$i] !== ' ' && $str[$i] !== '\t') {
                return false;
            }
        }

        return true;
    }

    public static function getIndentation(string $value): int
    {
        $isFirstLine  = true;
        $isEmptyLine  = true;
        $indent       = 0;
        $commonIndent = null;
        $valueLength  = mb_strlen($value);

        for ($i = 0; $i < $valueLength; ++$i) {
            switch (Utils::charCodeAt($value, $i)) {
                case 13: //  \r
                    if (Utils::charCodeAt($value, $i + 1) === 10) {
                        ++$i; // skip \r\n as one symbol
                    }
                // falls through
                case 10: //  \n
                    $isFirstLine = false;
                    $isEmptyLine = true;
                    $indent      = 0;
                    break;
                case 9: //   \t
                case 32: //  <space>
                    ++$indent;
                    break;
                default:
                    if (
                        $isEmptyLine &&
                        ! $isFirstLine &&
                        ($commonIndent === null || $indent < $commonIndent)
                    ) {
                        $commonIndent = $indent;
                    }

                    $isEmptyLine = false;
            }
        }

        return $commonIndent ?? 0;
    }

    /**
     * Print a block string in the indented block form by adding a leading and
     * trailing blank line. However, if a block string starts with whitespace and is
     * a single-line, adding a leading blank line would strip that whitespace.
     */
    public static function print(
        string $value,
        string $indentation = '',
        bool $preferMultipleLines = false
    ): string {
        $valueLength          = mb_strlen($value);
        $isSingleLine         = strpos($value, "\n") === false;
        $hasLeadingSpace      = $value[0] === ' ' || $value[0] === '\t';
        $hasTrailingQuote     = $value[$valueLength - 1] === '"';
        $hasTrailingSlash     = $value[$valueLength - 1] === '\\';
        $printAsMultipleLines =
            ! $isSingleLine
            || $hasTrailingQuote
            || $hasTrailingSlash
            || $preferMultipleLines;

        $result = '';
        // Format a multi-line block quote to account for leading space.
        if (
            $printAsMultipleLines
            && ! ($isSingleLine && $hasLeadingSpace)
        ) {
            $result .= "\n" . $indentation;
        }

        $result .= $indentation !== ''
            ? str_replace("\n", "\n" . $indentation, $value)
            : $value;
        if ($printAsMultipleLines) {
            $result .= "\n";
        }

        return '"""'
            . str_replace('"""', '\\"""', $result)
            . '"""';
    }
}
