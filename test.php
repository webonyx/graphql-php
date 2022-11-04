<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

assert(true, new \GraphQL\Utils\LazyException(function () {
    echo 'foo';

    return 'bar;';
}));
