<?php declare(strict_types=1);

namespace GraphQL\Error;

/**
 * Caused by GraphQL clients and can safely be displayed.
 */
class UserError extends \RuntimeException implements ClientAware
{
    public function isClientSafe(): bool
    {
        return true;
    }
}
