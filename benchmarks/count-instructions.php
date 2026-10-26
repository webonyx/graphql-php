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

/**
 * @return array{int, int} Revs run and instructions counted
 */
function countInstructions(string $runSubject, string $checkout, string $subject, string $revs): array
{
    [$stdout, $stderr] = run([
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
        $revs,
    ]);
    if (preg_match('/I\s+refs:\s+([\d,]+)/', $stderr, $match) !== 1) {
        throw new RuntimeException("No instruction count for {$subject}:\n{$stderr}");
    }

    return [(int) $stdout, (int) str_replace(',', '', $match[1])];
}

[$subjectList] = run([PHP_BINARY, $runSubject, $checkout, 'list']);

$instructionsPerRev = [];
foreach (explode("\n", trim($subjectList)) as $subject) {
    [$warmupRevs, $warmupInstructions] = countInstructions($runSubject, $checkout, $subject, '1');
    [$measuredRevs, $measuredInstructions] = countInstructions($runSubject, $checkout, $subject, 'annotated');
    $instructionsPerRev[$subject] = intdiv($measuredInstructions - $warmupInstructions, $measuredRevs - $warmupRevs);
    fwrite(STDERR, "{$subject}: {$instructionsPerRev[$subject]}\n");
}

echo json_encode($instructionsPerRev, JSON_PRETTY_PRINT) . "\n";
