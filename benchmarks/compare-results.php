<?php declare(strict_types=1);

/**
 * Compares the instructions per revolution that count-instructions.php measured on base and head.
 *
 * Usage: php benchmarks/compare-results.php <base.json> <head.json> [<threshold>]
 *
 * Prints a Markdown table and exits with 1 when head needs more than threshold times
 * the instructions of base for any benchmark.
 */
$baseFile = $argv[1] ?? null;
$headFile = $argv[2] ?? null;
$threshold = (float) ($argv[3] ?? 1.1);
if ($baseFile === null || $headFile === null) {
    fwrite(STDERR, "Usage: php benchmarks/compare-results.php <base.json> <head.json> [<threshold>]\n");
    exit(1);
}

/** @var array<string, int> $base */
$base = json_decode((string) file_get_contents($baseFile), true, 2, JSON_THROW_ON_ERROR);
/** @var array<string, int> $head */
$head = json_decode((string) file_get_contents($headFile), true, 2, JSON_THROW_ON_ERROR);

$ratios = [];
foreach (array_intersect_key($head, $base) as $name => $headInstructions) {
    $ratios[$name] = $base[$name] > 0
        ? $headInstructions / $base[$name]
        : 1.0;
}
arsort($ratios);

$regressions = array_filter($ratios, static fn (float $ratio): bool => $ratio > $threshold);

echo $regressions === []
    ? "### No benchmark needs more than {$threshold}x the instructions of base\n\n"
    : '### ' . count($regressions) . " benchmark(s) need more than {$threshold}x the instructions of base\n\n";
echo "Instructions per revolution, counted with Valgrind.\n\n";
echo "| Benchmark | Base | Head | Ratio |\n";
echo "| --- | ---: | ---: | ---: |\n";
foreach ($ratios as $name => $ratio) {
    $marker = isset($regressions[$name]) ? ' :warning:' : '';
    printf("| %s | %s | %s | %.4f%s |\n", $name, number_format($base[$name]), number_format($head[$name]), $ratio, $marker);
}
foreach (array_diff_key($head, $base) as $name => $headInstructions) {
    printf("| %s | | %s | new |\n", $name, number_format($headInstructions));
}

exit($regressions === [] ? 0 : 1);
