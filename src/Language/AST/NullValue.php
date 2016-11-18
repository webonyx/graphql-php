<?php
namespace GraphQL\Language\AST;

class NullValue extends Node implements Value
{
    public $kind = NodeType::NULL;

    public static function getNullValue()
    {
        static $nullValue;
        return $nullValue ?: $nullValue = new \stdClass();
    }
}
