<?php declare(strict_types=1);

namespace GraphQL\Benchmarks\Utils;

class BenchmarkResults
{
    /**
     * Reads a phpbench --dump-file XML into stats per benchmark name.
     *
     * Stats are times per revolution in microseconds, over all non-warm-up iterations.
     *
     * @return array<string, array<string, float>>
     */
    public static function read(string $xmlFile): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($xmlFile, \SimpleXMLElement::class, LIBXML_NONET);

        if ($xml === false) {
            throw new \RuntimeException("Failed to parse XML file: {$xmlFile}.");
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

                        $parameterSet = $variant->{'parameter-set'};
                        $paramSetName = isset($parameterSet['name']) ? (string) $parameterSet['name'] : '0';
                        $name = count($variants) > 1
                            ? "{$shortClass}::{$subjectName}#{$paramSetName}"
                            : "{$shortClass}::{$subjectName}";

                        $stats = [];
                        foreach ($variant->stats->attributes() as $stat => $value) {
                            $stats[$stat] = (float) $value;
                        }

                        $results[$name] = $stats;
                    }
                }
            }
        }

        return $results;
    }
}
