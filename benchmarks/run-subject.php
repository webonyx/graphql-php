<?php declare(strict_types=1);

/**
 * Runs PHPBench subjects outside of PHPBench, so count-instructions.php can measure them under Valgrind.
 *
 * Usage:
 *   php benchmarks/run-subject.php <checkout> list
 *   php benchmarks/run-subject.php <checkout> <Class::method> <revs|annotated>
 *
 * Revs "annotated" runs 1 more than a tenth of the @Revs of the subject.
 * Prints the number of revs it ran.
 */
$checkout = $argv[1] ?? null;
$subject = $argv[2] ?? null;
if ($checkout === null || $subject === null) {
    fwrite(STDERR, "Usage: php benchmarks/run-subject.php <checkout> list|<Class::method> [<revs|annotated>]\n");
    exit(1);
}

require_once "{$checkout}/vendor/autoload.php";

function annotation(string $name, ReflectionMethod $method): ?string
{
    foreach ([$method->getDocComment(), $method->getDeclaringClass()->getDocComment()] as $docComment) {
        if (is_string($docComment) && preg_match("/@{$name}\\((.+)\\)/", $docComment, $match) === 1) {
            return $match[1];
        }
    }

    return null;
}

if ($subject === 'list') {
    $files = glob("{$checkout}/benchmarks/*Bench.php");
    if ($files === false) {
        throw new RuntimeException("Failed to list benchmarks in {$checkout}.");
    }

    foreach ($files as $file) {
        $className = 'GraphQL\\Benchmarks\\' . basename($file, '.php');
        if (! class_exists($className)) {
            throw new RuntimeException("Failed to load benchmark class: {$className}.");
        }

        $class = new ReflectionClass($className);
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (strpos($method->getName(), 'bench') === 0) {
                echo "{$class->getShortName()}::{$method->getName()}\n";
            }
        }
    }

    exit(0);
}

[$shortClass, $methodName] = explode('::', $subject);
$method = new ReflectionMethod("GraphQL\\Benchmarks\\{$shortClass}", $methodName);
$revs = ($argv[3] ?? 'annotated') === 'annotated'
    ? 1 + (int) ceil((int) (annotation('Revs', $method) ?? 1) / 10)
    : (int) $argv[3];

$bench = $method->getDeclaringClass()->newInstance();
preg_match_all('/"(\w+)"/', annotation('BeforeMethods', $method) ?? '', $beforeMethods);
foreach ($beforeMethods[1] as $beforeMethod) {
    (new ReflectionMethod($bench, $beforeMethod))->invoke($bench);
}

$benchmark = $method->getClosure($bench);
for ($rev = 0; $rev < $revs; ++$rev) {
    $benchmark();
}

echo $revs;
