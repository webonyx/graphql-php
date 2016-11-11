<?php

namespace GraphQL\Language;

use GraphQL\Language\AST\Location;

class Source
{
    /**
     * @var string
     */
    protected $body;

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
        $this->body = $body;
        $this->length = mb_strlen($body, 'UTF-8');
        $this->name = $name ?: 'GraphQL';
    }

    /**
     * @param Location $position
     *
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

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param string $body
     *
     * @return Source
     */
    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }
}
