<?php

namespace GraphQL\Error;

use Exception;

/**
 * Caused if a user passes a wrong type.
 */
class TypeError extends Exception implements ClientAware
{
    public function isClientSafe(): bool
    {
        return true;
    }

    public function getCategory(): bool
    {
        return 'type';
    }
}