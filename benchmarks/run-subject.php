<?php declare(strict_types=1);

// Runs a PHPBench subject without PHPBench, so count-instructions.php can measure it under Valgrind.
$checkout = $argv[1] ?? null;
$subject = $argv[2] ?? null;
$revs = $argv[3] ?? null;
if ($checkout === null || $subject === null || $revs === null) {
    fwrite(STDERR, "Usage: php benchmarks/run-subject.php <checkout> <Class::method> <revs>\n");
    exit(1);
}

require_once "{$checkout}/vendor/autoload.php";

[$shortClass, $methodName] = explode('::', $subject);
$method = new ReflectionMethod("GraphQL\\Benchmarks\\{$shortClass}", $methodName);
$bench = $method->getDeclaringClass()->newInstance();

mt_srand(1);
$docComments = [$method->getDocComment(), $method->getDeclaringClass()->getDocComment()];
foreach ($docComments as $docComment) {
    if (is_string($docComment) && preg_match('/@BeforeMethods\((.+)\)/', $docComment, $beforeMethodsAnnotation) === 1) {
        preg_match_all('/"(\w+)"/', $beforeMethodsAnnotation[1], $beforeMethods);
        if ($beforeMethods[1] === []) {
            throw new RuntimeException("Found no method names in @BeforeMethods: {$subject}.");
        }

        foreach ($beforeMethods[1] as $beforeMethodName) {
            $beforeMethod = new ReflectionMethod($bench, $beforeMethodName);
            $beforeMethod->invoke($bench);
        }

        break;
    }
}

$benchmark = $method->getClosure($bench);
for ($rev = 0; $rev < (int) $revs; ++$rev) {
    $benchmark();
}
