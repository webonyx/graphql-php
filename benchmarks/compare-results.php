<?php declare(strict_types=1);

$baseFile = $argv[1] ?? null;
$headFile = $argv[2] ?? null;
if ($baseFile === null || $headFile === null) {
    fwrite(STDERR, "Usage: php benchmarks/compare-results.php <base.json> <head.json>\n");
    exit(1);
}

$threshold = 1.1;

/** @var array<string, int> $base */
$base = json_decode((string) file_get_contents($baseFile), true, 2, JSON_THROW_ON_ERROR);
/** @var array<string, int> $head */
$head = json_decode((string) file_get_contents($headFile), true, 2, JSON_THROW_ON_ERROR);

$ratios = [];
foreach (array_intersect_key($head, $base) as $comparedName => $comparedHeadInstructions) {
    $ratios[$comparedName] = $comparedHeadInstructions / $base[$comparedName];
}
if ($ratios === []) {
    throw new RuntimeException("Found no benchmark in both {$baseFile} and {$headFile}.");
}
arsort($ratios);

$regressions = array_filter($ratios, static fn (float $ratio): bool => $ratio > $threshold);

$regressionCount = count($regressions);
$comparedCount = count($ratios);
echo $regressions === []
    ? "### No benchmark needs more than {$threshold}x the instructions of base\n\n"
    : "### {$regressionCount} of {$comparedCount} benchmarks need more than {$threshold}x the instructions of base\n\n";
echo "Instructions per revolution, counted with Valgrind.\n";
echo "When this PR edits a benchmark, that benchmark's ratio compares two different workloads.\n\n";
echo "| Benchmark | Base | Head | Ratio |\n";
echo "| --- | ---: | ---: | ---: |\n";
foreach ($ratios as $name => $ratio) {
    $marker = isset($regressions[$name]) ? ' :warning:' : '';
    printf("| %s | %s | %s | %.4f%s |\n", $name, number_format($base[$name]), number_format($head[$name]), $ratio, $marker);
}
foreach (array_diff_key($head, $base) as $newName => $newInstructions) {
    printf("| %s | | %s | new |\n", $newName, number_format($newInstructions));
}
foreach (array_diff_key($base, $head) as $removedName => $removedInstructions) {
    printf("| %s | %s | | removed |\n", $removedName, number_format($removedInstructions));
}

exit($regressions === [] ? 0 : 1);
