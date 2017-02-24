<?php
namespace GraphQL\Language;

use GraphQL\Utils;

class Source
{
    /**
     * @var string
     */
    public $body;

    /**
     * @var int
     */
    public $length;

    /**
     * @var string
     */
    public $name;

    public function __construct($body, $name = null)
    {
        Utils::invariant(
            is_string($body),
            'GraphQL query body is expected to be string, but got ' . Utils::getVariableType($body)
        );

        $this->body = $body;
        $this->length = mb_strlen($body, 'UTF-8');
        $this->name = $name ?: 'GraphQL';
    }

    /**
     * @param $position
     * @return SourceLocation
     */
    public function getLocation($position)
    {
        $line = 1;
        $column = $position + 1;

        $utfChars = json_decode('"\u2028\u2029"');
        $lineRegexp = '/\r\n|[\n\r'.$utfChars.']/su';
        $matches = [];
        preg_match_all($lineRegexp, mb_substr($this->body, 0, $position, 'UTF-8'), $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $index => $match) {
            $line += 1;
            $column = $position + 1 - ($match[1] + mb_strlen($match[0], 'UTF-8'));
        }

        return new SourceLocation($line, $column);
    }
}
