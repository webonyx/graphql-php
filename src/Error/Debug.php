<?php
namespace GraphQL\Error;

/**
 * Collection of flags for [error debugging](error-handling.md#debugging-tools).
 */
class Debug
{
    const INCLUDE_DEBUG_MESSAGE = 1;
    const INCLUDE_TRACE = 2;
    const RETHROW_INTERNAL_EXCEPTIONS = 4;
}
