<?php declare(strict_types=1);

/**
 * Converts a phpbench --dump-file XML to the customSmallerIsBetter JSON format
 * expected by benchmark-action/github-action-benchmark.
 *
 * Usage: php benchmarks/convert-results.php benchmark.xml > benchmark-results.json
 *
 * stats/@mean is the mean time per revolution in microseconds,
 * computed by phpbench over all non-warm-up iterations.
 */
$xmlFile = $argv[1] ?? null;

if ($xmlFile === null) {
    fwrite(STDERR, "Usage: php benchmarks/convert-results.php <benchmark.xml>\n");
    exit(1);
}

$xml = simplexml_load_file($xmlFile);

if ($xml === false) {
    fwrite(STDERR, "Failed to parse XML file: {$xmlFile}\n");
    exit(1);
}

$results = [];

foreach ($xml->suite as $suite) {
    foreach ($suite->benchmark as $benchmark) {
        $class = (string) $benchmark['class'];
        $shortClass = str_replace(['\\GraphQL\\Benchmarks\\', 'GraphQL\\Benchmarks\\'], '', $class);

        foreach ($benchmark->subject as $subject) {
            $subjectName = (string) $subject['name'];
            $variants = $subject->variant;

            foreach ($variants as $variant) {
                if (isset($variant->errors)) {
                    continue;
                }

                if (! isset($variant->stats)) {
                    continue;
                }

                $meanUs = (float) $variant->stats['mean'];
                $valueMs = round($meanUs / 1000, 3);

                $paramSetName = (string) ($variant->{'parameter-set'}['name'] ?? '0');
                $name = count($variants) > 1
                    ? "{$shortClass}::{$subjectName}#{$paramSetName}"
                    : "{$shortClass}::{$subjectName}";

                $results[] = [
                    'name' => $name,
                    'value' => $valueMs,
                    'unit' => 'ms',
                ];
            }
        }
    }
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
