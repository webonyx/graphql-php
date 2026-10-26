<?php declare(strict_types=1);

$checkout = $argv[1] ?? null;
if ($checkout === null) {
    fwrite(STDERR, "Usage: php benchmarks/count-instructions.php <checkout>\n");
    exit(1);
}

require_once "{$checkout}/vendor/autoload.php";

// Extra revs add up to this many instructions, so the measured work outweighs the noise of setup.
const INSTRUCTION_BUDGET = 20_000_000;

/**
 * @param list<string> $command
 *
 * @return string The standard error output
 */
function runCommand(array $command): string
{
    $process = proc_open($command, [1 => ['file', '/dev/null', 'w'], 2 => ['pipe', 'w']], $pipes);
    $commandLine = implode(' ', $command);
    if (! is_resource($process)) {
        throw new RuntimeException("Failed to start: {$commandLine}.");
    }

    $stderr = (string) stream_get_contents($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        throw new RuntimeException("Exit code {$exitCode} from {$commandLine}:\n{$stderr}");
    }

    return $stderr;
}

function countInstructions(string $checkout, string $subject, int $revs): int
{
    $benchmarksDirectory = __DIR__;
    $stderr = runCommand([
        'valgrind',
        '--tool=cachegrind',
        '--cachegrind-out-file=/dev/null',
        PHP_BINARY,
        '-d',
        'zend.assertions=-1',
        '-d',
        'opcache.enable_cli=0',
        "{$benchmarksDirectory}/run-subject.php",
        $checkout,
        $subject,
        (string) $revs,
    ]);
    if (preg_match('/I\s+refs:\s+([\d,]+)/', $stderr, $match) !== 1) {
        throw new RuntimeException("No instruction count for {$subject}:\n{$stderr}");
    }

    $instructions = str_replace(',', '', $match[1]);

    return (int) $instructions;
}

$files = glob("{$checkout}/benchmarks/*Bench.php");
if ($files === false) {
    throw new RuntimeException("Failed to list benchmarks in {$checkout}.");
}

$instructionsPerRev = [];
foreach ($files as $file) {
    $shortClassName = basename($file, '.php');
    $className = "GraphQL\\Benchmarks\\{$shortClassName}";
    if (! class_exists($className)) {
        throw new RuntimeException("Failed to load benchmark class: {$className}.");
    }

    $class = new ReflectionClass($className);
    foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if (strpos($method->getName(), 'bench') !== 0) {
            continue;
        }

        $subject = "{$class->getShortName()}::{$method->getName()}";
        // The 1-rev run holds bootstrap, setup and first-run costs, so the difference cancels them.
        $baselineInstructions = countInstructions($checkout, $subject, 1);
        $probeInstructions = countInstructions($checkout, $subject, 2);
        $probeRevInstructions = max(1, $probeInstructions - $baselineInstructions);
        $extraRevs = (int) ceil(INSTRUCTION_BUDGET / $probeRevInstructions);
        $measuredInstructions = $extraRevs === 1
            ? $probeInstructions
            : countInstructions($checkout, $subject, 1 + $extraRevs);
        if ($measuredInstructions <= $baselineInstructions) {
            throw new RuntimeException("Expected more instructions with more revs: {$subject}.");
        }

        $instructionsPerRev[$subject] = intdiv($measuredInstructions - $baselineInstructions, $extraRevs);
        fwrite(STDERR, "{$subject}: {$instructionsPerRev[$subject]}\n");
    }
}

$json = json_encode($instructionsPerRev, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
echo "{$json}\n";
