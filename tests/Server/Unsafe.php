<?php

declare(strict_types=1);

namespace GraphQL\Tests\Server;

use Exception;
use GraphQL\Error\ClientAware;

class Unsafe extends Exception implements ClientAware
{
    /**
     * Returns true when exception message is safe to be displayed to a client.
     *
     * @return bool
     *
     * @api
     */
    public function isClientSafe()
    {
        return false;
    }

    /**
     * Returns string describing a category of the error.
     *
     * Value "graphql" is reserved for errors produced by query parsing or validation, do not use it.
     *
     * @return string
     *
     * @api
     */
    public function getCategory()
    {
        return 'unsafe';
    }
}
