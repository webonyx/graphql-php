<?php
namespace GraphQL\Language;

class Exception extends \Exception
{
    /**
     * @var Source
     */
    public $source;

    /**
     * @var number
     */
    public $position;

    public $location;

    /**
     * @param Source $source
     * @param $position
     * @param $description
     * @return Exception
     */
    public static function create(Source $source, $position, $description)
    {
        $location = $source->getLocation($position);
        $syntaxError = new self(
            "Syntax Error {$source->name} ({$location->line}:{$location->column}) $description\n\n" .
            self::highlightSourceAtLocation($source, $location)
        );
        $syntaxError->source = $source;
        $syntaxError->position = $position;
        $syntaxError->location = $location;

        return $syntaxError;
    }

    public static function highlightSourceAtLocation(Source $source, SourceLocation $location)
    {
        $line = $location->line;
        $prevLineNum = (string)($line - 1);
        $lineNum = (string)$line;
        $nextLineNum = (string)($line + 1);
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
