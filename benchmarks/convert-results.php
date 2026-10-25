<?php declare(strict_types=1);

/**
 * Converts a phpbench --dump-file XML to the customSmallerIsBetter JSON format
 * expected by benchmark-action/github-action-benchmark.
 *
 * Usage: php benchmarks/convert-results.php benchmark.xml > benchmark-results.json
 */

use GraphQL\Benchmarks\Utils\BenchmarkResults;

require_once __DIR__ . '/../vendor/autoload.php';

$xmlFile = $argv[1] ?? null;

if ($xmlFile === null) {
    fwrite(STDERR, "Usage: php benchmarks/convert-results.php <benchmark.xml>\n");
    exit(1);
}

$results = [];
foreach (BenchmarkResults::read($xmlFile) as $name => $stats) {
    $results[] = [
        'name' => $name,
        'value' => round($stats['mean'] / 1000, 3),
        'unit' => 'ms',
    ];
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
