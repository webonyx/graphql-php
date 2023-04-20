<?php declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->notPath('vendor')
    ->in(__DIR__)
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return \MLL\PhpCsFixerConfig\risky($finder, [
    'no_superfluous_phpdoc_tags' => [
        'allow_mixed' => true,
    ],
    'phpdoc_order_by_value' => [
        'annotations' => [
            'throws',
        ],
    ],
    'yoda_style' => [
        'equal' => false,
        'identical' => false,
        'less_and_greater' => false,
    ],
]);
