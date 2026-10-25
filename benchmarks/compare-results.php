<?php declare(strict_types=1);

/**
 * Compares phpbench --dump-file XMLs of base and head, run in turns on the same machine.
 *
 * Usage: php benchmarks/compare-results.php --base=base-1.xml --head=head-1.xml [--base=base-2.xml --head=head-2.xml ...] [--threshold=1.25]
 *
 * Prints a Markdown table and exits with 1 when the median head/base ratio
 * of the passes exceeds the threshold for any benchmark.
 */

use GraphQL\Benchmarks\Utils\BenchmarkResults;

require_once __DIR__ . '/../vendor/autoload.php';

$options = getopt('', ['base:', 'head:', 'threshold:']);
$baseFiles = (array) ($options['base'] ?? []);
$headFiles = (array) ($options['head'] ?? []);
$threshold = (float) ($options['threshold'] ?? 1.25);

if ($baseFiles === [] || count($baseFiles) !== count($headFiles)) {
    fwrite(STDERR, "Usage: php benchmarks/compare-results.php --base=<base.xml> --head=<head.xml> [--base=... --head=...] [--threshold=1.25]\n");
    exit(1);
}

/**
 * @param array<float> $numbers
 */
function median(array $numbers): float
{
    sort($numbers);
    $middle = intdiv(count($numbers), 2);

    return count($numbers) % 2 === 1
        ? $numbers[$middle]
        : ($numbers[$middle - 1] + $numbers[$middle]) / 2;
}

$basePasses = array_map([BenchmarkResults::class, 'read'], $baseFiles);
$headPasses = array_map([BenchmarkResults::class, 'read'], $headFiles);

$names = array_keys(array_intersect_key(...$basePasses, ...$headPasses));

$comparisons = [];
foreach ($names as $name) {
    $baseModes = [];
    $headModes = [];
    $ratios = [];
    foreach ($basePasses as $pass => $baseResults) {
        $baseMode = $baseResults[$name]['mode'];
        $headMode = $headPasses[$pass][$name]['mode'];
        $baseModes[] = $baseMode;
        $headModes[] = $headMode;
        $ratios[] = $baseMode > 0 ? $headMode / $baseMode : 1.0;
    }

    $comparisons[$name] = [
        'base' => median($baseModes),
        'head' => median($headModes),
        'ratio' => median($ratios),
        'ratios' => $ratios,
    ];
}

uasort($comparisons, static fn (array $a, array $b): int => $b['ratio'] <=> $a['ratio']);

$regressions = array_filter($comparisons, static fn (array $comparison): bool => $comparison['ratio'] > $threshold);

$passCount = count($basePasses);
echo $regressions === []
    ? "### No benchmark regressed by more than {$threshold}x\n\n"
    : '### ' . count($regressions) . " benchmark(s) regressed by more than {$threshold}x\n\n";
echo "Mode time per revolution, median of {$passCount} passes that ran base and head in turns on the same runner.\n\n";
echo "| Benchmark | Base (µs) | Head (µs) | Ratio | Ratios per pass |\n";
echo "| --- | ---: | ---: | ---: | --- |\n";
foreach ($comparisons as $name => $comparison) {
    $marker = isset($regressions[$name]) ? ' :warning:' : '';
    $ratiosPerPass = implode(', ', array_map(static fn (float $ratio): string => sprintf('%.2f', $ratio), $comparison['ratios']));
    printf(
        "| %s | %.3f | %.3f | %.2f%s | %s |\n",
        $name,
        $comparison['base'],
        $comparison['head'],
        $comparison['ratio'],
        $marker,
        $ratiosPerPass
    );
}

exit($regressions === [] ? 0 : 1);
