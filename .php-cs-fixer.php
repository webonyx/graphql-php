<?php declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->notPath('vendor')
    ->in(__DIR__)
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

$phpVersion = phpversion();

if (version_compare($phpVersion, '8.0', '<')) {
    $finder->notPath([
        'src/Type/Definition/Deprecated.php',
        'src/Type/Definition/Description.php',
    ]);
}

if (version_compare($phpVersion, '8.1', '<')) {
    $finder->notPath([
        'src/Type/Definition/PhpEnumType.php',
        'tests/Type/PhpEnumType',
    ]);
}

return MLL\PhpCsFixerConfig\risky($finder, [
    'no_superfluous_phpdoc_tags' => [
        'allow_mixed' => true,
    ],
    'phpdoc_align' => [
        'align' => 'left',
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
])->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect());
