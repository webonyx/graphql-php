<?php declare(strict_types=1);

return Rector\Config\RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/rector.php',
        __DIR__ . '/tally.php',
    ])
    ->withSets([
        Rector\Set\ValueObject\SetList::CODE_QUALITY,
        Rector\Set\ValueObject\SetList::DEAD_CODE,
    ]);
