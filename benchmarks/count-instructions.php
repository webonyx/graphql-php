<?php declare(strict_types=1);

/**
 * Counts the instructions per revolution of every PHPBench subject with Valgrind.
 *
 * Usage: php benchmarks/count-instructions.php <checkout> > instructions.json
 *
 * Runs each subject once with 1 rev, once with 1 + a tenth of its @Revs, and divides the difference,
 * so bootstrap, setup and first-run costs cancel out.
 */
$checkout = $argv[1] ?? null;
if ($checkout === null) {
    fwrite(STDERR, "Usage: php benchmarks/count-instructions.php <checkout>\n");
    exit(1);
}

require_once "{$checkout}/vendor/autoload.php";

$runSubject = __DIR__ . '/run-subject.php';

/**
 * @param list<string> $command
 *
 * @return array{string, string}
 */
function run(array $command): array
{
    $process = proc_open(
        implode(' ', array_map('escapeshellarg', $command)),
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (! is_resource($process)) {
        throw new RuntimeException('Failed to start: ' . implode(' ', $command) . '.');
    }

    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        throw new RuntimeException("Exit code {$exitCode} from " . implode(' ', $command) . ":\n{$stdout}{$stderr}");
    }

    return [$stdout, $stderr];
}

function countInstructions(string $runSubject, string $checkout, string $subject, int $revs): int
{
    [, $stderr] = run([
        'valgrind',
        '--tool=cachegrind',
        '--cache-sim=no',
        '--cachegrind-out-file=/dev/null',
        PHP_BINARY,
        '-d',
        'zend.assertions=-1',
        '-d',
        'opcache.enable_cli=0',
        $runSubject,
        $checkout,
        $subject,
        (string) $revs,
    ]);
    if (preg_match('/I\s+refs:\s+([\d,]+)/', $stderr, $match) !== 1) {
        throw new RuntimeException("No instruction count for {$subject}:\n{$stderr}");
    }

    return (int) str_replace(',', '', $match[1]);
}

function annotatedRevs(ReflectionMethod $method, string $subject): int
{
    foreach ([$method->getDocComment(), $method->getDeclaringClass()->getDocComment()] as $docComment) {
        if (is_string($docComment) && preg_match('/@Revs\((.+)\)/', $docComment, $match) === 1) {
            if (preg_match('/^\d+$/', $match[1]) !== 1) {
                throw new RuntimeException("Only a single integer is supported in @Revs: {$subject}.");
            }

            return (int) $match[1];
        }
    }

    return 1;
}

$files = glob("{$checkout}/benchmarks/*Bench.php");
if ($files === false) {
    throw new RuntimeException("Failed to list benchmarks in {$checkout}.");
}

$instructionsPerRev = [];
foreach ($files as $file) {
    $className = 'GraphQL\\Benchmarks\\' . basename($file, '.php');
    if (! class_exists($className)) {
        throw new RuntimeException("Failed to load benchmark class: {$className}.");
    }

    $class = new ReflectionClass($className);
    foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if (strpos($method->getName(), 'bench') !== 0) {
            continue;
        }

        $subject = "{$class->getShortName()}::{$method->getName()}";
        $extraRevs = (int) ceil(annotatedRevs($method, $subject) / 10);
        $warmupInstructions = countInstructions($runSubject, $checkout, $subject, 1);
        $measuredInstructions = countInstructions($runSubject, $checkout, $subject, 1 + $extraRevs);
        $instructionsPerRev[$subject] = intdiv($measuredInstructions - $warmupInstructions, $extraRevs);
        fwrite(STDERR, "{$subject}: {$instructionsPerRev[$subject]}\n");
    }
}

echo json_encode($instructionsPerRev, JSON_PRETTY_PRINT) . "\n";
