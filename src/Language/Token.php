<?php
namespace GraphQL\Language;

// language/lexer.js

class Token
{
    const EOF = 1;
    const BANG = 2;
    const DOLLAR = 3;
    const PAREN_L = 4;
    const PAREN_R = 5;
    const SPREAD = 6;
    const COLON = 7;
    const EQUALS = 8;
    const AT = 9;
    const BRACKET_L = 10;
    const BRACKET_R = 11;
    const BRACE_L = 12;
    const PIPE = 13;
    const BRACE_R = 14;
    const NAME = 15;
    const VARIABLE = 16;
    const INT = 17;
    const FLOAT = 18;
    const STRING = 19;
    
    public static function getKindDescription($kind)
    {
        $description = array();
        $description[self::EOF] = 'EOF';
        $description[self::BANG] = '!';
        $description[self::DOLLAR] = '$';
        $description[self::PAREN_L] = '(';
        $description[self::PAREN_R] = ')';
        $description[self::SPREAD] = '...';
        $description[self::COLON] = ':';
        $description[self::EQUALS] = '=';
        $description[self::AT] = '@';
        $description[self::BRACKET_L] = '[';
        $description[self::BRACKET_R] = ']';
        $description[self::BRACE_L] = '{';
        $description[self::PIPE] = '|';
        $description[self::BRACE_R] = '}';
        $description[self::NAME] = 'Name';
        $description[self::VARIABLE] = 'Variable';
        $description[self::INT] = 'Int';
        $description[self::FLOAT] = 'Float';
        $description[self::STRING] = 'String';

        return $description[$kind];
    }

    /**
     * @var int
     */
    public $kind;

    /**
     * @var int
     */
    public $start;

    /**
     * @var int
     */
    public $end;

    /**
     * @var string|null
     */
    public $value;

    public function __construct($kind, $start, $end, $value = null)
    {
        $this->kind = $kind;
        $this->start = (int) $start;
        $this->end = (int) $end;
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return self::getKindDescription($this->kind) . ($this->value ? ' "' . $this->value  . '"' : '');
    }
}
