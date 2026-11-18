<?php declare(strict_types=1);

return Tally\Configuration::create()
    ->withPaths([__DIR__ . '/src'])
    ->withIniEntries(['zend.assertions=-1']);
