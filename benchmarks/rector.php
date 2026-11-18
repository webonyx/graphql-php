<?php declare(strict_types=1);

return Rector\Config\RectorConfig::configure()
    ->withPaths([__DIR__])
    ->withSkip([__DIR__ . '/vendor'])
    ->withSets([
        Rector\Set\ValueObject\SetList::CODE_QUALITY,
        Rector\Set\ValueObject\SetList::DEAD_CODE,
    ]);
