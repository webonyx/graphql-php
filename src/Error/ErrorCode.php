<?php

declare(strict_types=1);

namespace GraphQL\Error;

use Exception;
use function sprintf;

class ErrorCode
{
    const ERR_UNKNOWN_DIRECTIVE    = 'unknownDirective';
    const ERR_CANT_SPREAD_FRAGMENT = 'cantSpreadFragment';

    /** @var array<string,string> */
    protected static $messages = [
        self::ERR_UNKNOWN_DIRECTIVE => 'Unknown directive "%s".',
        self::ERR_CANT_SPREAD_FRAGMENT => 'Fragment "%s" cannot be spread here as objects of type "%s" can never be of type "%s".',
    ];

    /** @var string */
    private $code;

    /** @var mixed[] */
    private $args;

    /**
     * @param mixed[] $args
     */
    public function __construct(string $code, array $args = [])
    {
        if (! isset(static::$messages[$code])) {
            throw new Exception('Unknown code: ' . $code);
        }
        $this->code = $code;
        $this->args = $args;
    }

    public function getFormattedMessage() : string
    {
        return sprintf(static::$messages[$this->code], ...$this->args);
    }

    public function getMessage() : string
    {
        return static::$messages[$this->code];
    }

    /**
     * @return mixed[] array
     */
    public function getArgs() : array
    {
        return $this->args;
    }

    public function getCode() : string
    {
        return $this->code;
    }
}
