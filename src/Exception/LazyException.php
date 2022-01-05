<?php

namespace GraphQL\Exception;

use Exception;

/**
 * Allows lazy calculation of a complex message.
 *
 * @phpstan-import-type MakeString from LazyString
 */
class LazyException extends Exception
{
    /**
     * @param MakeString $makeMessage
     */
    public function __construct(callable $makeMessage)
    {
        parent::__construct(new LazyException($makeMessage));
    }
}
