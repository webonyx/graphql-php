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

    /**
     * Returns string describing error category.
     *
     * Value "graphql" is reserved for errors produced by query parsing or validation, do not use it.
     *
     * @return string
     */
    public function getCategory();
}
