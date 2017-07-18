<?php
namespace GraphQL\Error;

/**
 * Interface ClientAware
 *
 * @package GraphQL\Error
 */
interface ClientAware
{
    /**
     * Returns true when exception message is safe to be displayed to client
     *
     * @return bool
     */
    public function isClientSafe();
}
