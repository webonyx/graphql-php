<?php declare(strict_types=1);

namespace GraphQL\Server;

use GraphQL\Error\ClientAware;

class RequestError extends \Exception implements ClientAware
{
    public function isClientSafe(): bool
    {
        return true;
    }
}
