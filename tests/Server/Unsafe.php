<?php declare(strict_types=1);

namespace GraphQL\Tests\Server;

use GraphQL\Error\ClientAware;

class Unsafe extends \Exception implements ClientAware
{
    public function isClientSafe(): bool
    {
        return false;
    }
}
