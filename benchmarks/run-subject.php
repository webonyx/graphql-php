<?php declare(strict_types=1);

/**
 * Runs a PHPBench subject outside of PHPBench, so count-instructions.php can measure it under Valgrind.
 *
 * Usage: php benchmarks/run-subject.php <checkout> <Class::method> <revs>
 *
 * Supports only the @Revs and @BeforeMethods docblock annotations.
 */
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

foreach ([$method->getDocComment(), $method->getDeclaringClass()->getDocComment()] as $docComment) {
    if (is_string($docComment) && preg_match('/@BeforeMethods\((.+)\)/', $docComment, $beforeMethodsAnnotation) === 1) {
        preg_match_all('/"(\w+)"/', $beforeMethodsAnnotation[1], $beforeMethods);
        foreach ($beforeMethods[1] as $beforeMethod) {
            (new ReflectionMethod($bench, $beforeMethod))->invoke($bench);
        }

        break;
    }
}

$benchmark = $method->getClosure($bench);
for ($rev = 0; $rev < (int) $revs; ++$rev) {
    $benchmark();
}
