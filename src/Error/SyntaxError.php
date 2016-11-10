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
        $syntaxError =
            "Syntax Error {$source->name} ({$location->line}:{$location->column}) $description\n\n" .
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
        $prevLineNum = (string) ($line - 1);
        $lineNum = (string) $line;
        $nextLineNum = (string) ($line + 1);
        $padLen = mb_strlen($nextLineNum, 'UTF-8');

        $unicodeChars = json_decode('"\u2028\u2029"'); // Quick hack to get js-compatible representation of these chars
        $lines = preg_split('/\r\n|[\n\r' . $unicodeChars . ']/su', $source->body);

        $lpad = function($len, $str) {
            return str_pad($str, $len - mb_strlen($str, 'UTF-8') + 1, ' ', STR_PAD_LEFT);
        };

        return
            ($line >= 2 ? $lpad($padLen, $prevLineNum) . ': ' . $lines[$line - 2] . "\n" : '') .
            ($lpad($padLen, $lineNum) . ': ' . $lines[$line - 1] . "\n") .
            (str_repeat(' ', 1 + $padLen + $location->column) . "^\n") .
            ($line < count($lines) ? $lpad($padLen, $nextLineNum) . ': ' . $lines[$line] . "\n" : '');
    }
}
