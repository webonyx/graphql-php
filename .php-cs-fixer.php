<?php declare(strict_types=1);

use function MLL\PhpCsFixerConfig\config;

$finder = PhpCsFixer\Finder::create()
    ->notPath('vendor')
    ->in(__DIR__)
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return config($finder);
