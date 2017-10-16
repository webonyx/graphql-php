<?php
namespace GraphQL\Error;

use GraphQL\Language\Source;
use GraphQL\Language\SourceLocation;

class SyntaxError extends Error
{
    /**
     * @param Source $source
     * @param int $position
     * @param string $description
     */
    public function __construct(Source $source, $position, $description)
    {
        $location = $source->getLocation($position);
        $line = $location->line + $source->locationOffset->line - 1;
        $columnOffset = self::getColumnOffset($source, $location);
        $column = $location->column + $columnOffset;

        $syntaxError =
            "Syntax Error {$source->name} ({$line}:{$column}) $description\n" .
            "\n".
            self::highlightSourceAtLocation($source, $location);

        parent::__construct($syntaxError, null, $source, [$position]);
    }

    /**
     * @param Source $source
     * @param SourceLocation $location
     * @return string
     */
    public static function highlightSourceAtLocation(Source $source, SourceLocation $location)
    {
        $line = $location->line;
        $lineOffset = $source->locationOffset->line - 1;
        $columnOffset = self::getColumnOffset($source, $location);

        $contextLine = $line + $lineOffset;
        $prevLineNum = (string) ($contextLine - 1);
        $lineNum = (string) $contextLine;
        $nextLineNum = (string) ($contextLine + 1);
        $padLen = mb_strlen($nextLineNum, 'UTF-8');

        $unicodeChars = json_decode('"\u2028\u2029"'); // Quick hack to get js-compatible representation of these chars
        $lines = preg_split('/\r\n|[\n\r' . $unicodeChars . ']/su', $source->body);

        $whitespace = function ($len) {
            return str_repeat(' ', $len);
        };

        $lpad = function ($len, $str) {
            return str_pad($str, $len - mb_strlen($str, 'UTF-8') + 1, ' ', STR_PAD_LEFT);
        };

        $lines[0] = $whitespace($source->locationOffset->column - 1) . $lines[0];

        return
            ($line >= 2 ? $lpad($padLen, $prevLineNum) . ': ' . $lines[$line - 2] . "\n" : '') .
            ($lpad($padLen, $lineNum) . ': ' . $lines[$line - 1] . "\n") .
            ($whitespace(2 + $padLen + $location->column - 1 + $columnOffset) . "^\n") .
            ($line < count($lines) ? $lpad($padLen, $nextLineNum) . ': ' . $lines[$line] . "\n" : '');
    }

    public static function getColumnOffset(Source $source, SourceLocation $location)
    {
        return $location->line === 1 ? $source->locationOffset->column - 1 : 0;
    }

}
