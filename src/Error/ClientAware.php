<?php
namespace GraphQL\Error;

/**
 * This interface is used for [default error formatting](error-handling.md).
 *
 * Only errors implementing this interface (and returning true from `isClientSafe()`)
 * will be formatted with original error message.
 *
 * All other errors will be formatted with generic "Internal server error".
 */
interface ClientAware
{
    /**
     * Returns true when exception message is safe to be displayed to a client.
     *
     * @api
     * @return bool
     */
    public function isClientSafe();

    /**
     * Returns string describing a category of the error.
     *
     * Value "graphql" is reserved for errors produced by query parsing or validation, do not use it.
     *
     * @api
     * @return string
     */
    public function getCategory();
}
