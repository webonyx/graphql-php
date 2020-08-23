<?php

declare(strict_types=1);

namespace GraphQL\Error;

class ErrorCode
{
    const ERR_UNKNOWN_DIRECTIVE = 'unknownDirective';
    const ERR_CANT_SPREAD_FRAGMENT = 'cantSpreadFragment';

    static $messages = [
        self::ERR_UNKNOWN_DIRECTIVE => 'Unknown directive "%s".',
        self::ERR_CANT_SPREAD_FRAGMENT => 'Fragment "%s" cannot be spread here as objects of type "%s" can never be of type "%s".'
    ];

    private $code;
    private $args;

    function __construct(string $code, array $args = []) {
        if(!isset(static::$messages[$code])) {
            throw Exception("Unknown code: " . $code);
        }
        $this->code = $code;
        $this->args = $args;
    }

    function getFormattedMessage() : string {
        return sprintf(static::$messages[$this->code], ...$this->args);
    }

    function getMessage() : string {
        return static::$messages[$this->code];
    }

    function getArgs() : array {
        return $this->args;
    }

    function getCode() : string {
        return $this->code;
    }
}
