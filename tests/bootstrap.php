<?php

declare(strict_types=1);

namespace GraphQL\Tests;

use GraphQL\Executor\Executor;
use GraphQL\Experimental\Executor\CoroutineExecutor;
use function getenv;

require_once __DIR__ . '/../vendor/autoload.php';

if (getenv('EXECUTOR') === 'coroutine') {
    Executor::setImplementationFactory([CoroutineExecutor::class, 'create']);
}
