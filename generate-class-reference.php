<?php declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use GraphQL\Utils\PhpDoc;
use Symfony\Component\VarExporter\Exception\ExceptionInterface;
use Symfony\Component\VarExporter\VarExporter;

const OUTPUT_FILE = __DIR__ . '/docs/class-reference.md';

const ENTRIES = [
    GraphQL\GraphQL::class => [],
    GraphQL\Type\Definition\Type::class => [],
    GraphQL\Type\Definition\ResolveInfo::class => [],
    GraphQL\Language\DirectiveLocation::class => ['constants' => true],
    GraphQL\Type\SchemaConfig::class => [],
    GraphQL\Type\Schema::class => [],
    GraphQL\Language\Parser::class => [],
    GraphQL\Language\Printer::class => [],
    GraphQL\Language\Visitor::class => [],
    GraphQL\Language\AST\NodeKind::class => ['constants' => true],
    GraphQL\Executor\Executor::class => [],
    GraphQL\Executor\ScopedContext::class => [],
    GraphQL\Executor\ExecutionResult::class => [],
    GraphQL\Executor\Promise\PromiseAdapter::class => [],
    GraphQL\Deferred::class => [],
    GraphQL\Executor\Promise\Adapter\SyncPromiseQueue::class => [],
    GraphQL\Validator\DocumentValidator::class => [],
    GraphQL\Error\Error::class => ['constants' => true],
    GraphQL\Error\Warning::class => ['constants' => true],
    GraphQL\Error\ClientAware::class => [],
    GraphQL\Error\DebugFlag::class => ['constants' => true],
    GraphQL\Error\FormattedError::class => [],
    GraphQL\Server\StandardServer::class => [],
    GraphQL\Server\ServerConfig::class => [],
    GraphQL\Server\Helper::class => [],
    GraphQL\Server\OperationParams::class => [],
    GraphQL\Utils\BuildSchema::class => [],
    GraphQL\Utils\AST::class => [],
    GraphQL\Utils\SchemaPrinter::class => [],
];

function wrapPhpstanTypes(?string $docs): ?string
{
    if ($docs === null) {
        return null;
    }

    $lines = explode("\n", $docs);
    $result = [];
    $block = [];
    $braceDepth = 0;

    foreach ($lines as $line) {
        if (str_starts_with($line, '@phpstan-') || ($block !== [] && $braceDepth > 0)) {
            $block[] = $line;
            $braceDepth += substr_count($line, '{') - substr_count($line, '}');
        } else {
            if ($block !== []) {
                $result[] = '```php';
                array_push($result, ...$block);
                $result[] = '```';
                $block = [];
                $braceDepth = 0;
            }
            $result[] = $line;
        }
    }

    if ($block !== []) {
        $result[] = '```php';
        array_push($result, ...$block);
        $result[] = '```';
    }

    return implode("\n", $result);
}

/**
 * @param ReflectionClass<covariant object> $class
 * @param array{constants?: bool, props?: bool, methods?: bool} $options
 *
 * @throws ExceptionInterface
 * @throws ReflectionException
 */
function renderClass(ReflectionClass $class, array $options): string
{
    $classDocs = wrapPhpstanTypes(PhpDoc::unwrap(PhpDoc::unpad($class->getDocComment())));
    $content = '';
    $className = $class->getName();

    if ($options['constants'] ?? false) {
        $constants = [];
        foreach ($class->getConstants(/* TODO enable with PHP 8: ReflectionClassConstant::IS_PUBLIC */) as $name => $value) {
            $constants[] = "const {$name} = " . VarExporter::export($value) . ';';
        }

        if ($constants !== []) {
            $constants = "```php\n" . implode("\n", $constants) . "\n```";
            $content .= "### {$className} Constants\n\n{$constants}\n\n";
        }
    }

    if ($options['props'] ?? true) {
        $props = [];
        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (isApi($property)) {
                $props[] = renderProp($property);
            }
        }

        if ($props !== []) {
            $props = "```php\n" . implode("\n\n", $props) . "\n```";
            $content .= "### {$className} Props\n\n{$props}\n\n";
        }
    }

    if ($options['methods'] ?? true) {
        $methods = [];
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (isApi($method)) {
                $methods[] = renderMethod($method);
            }
        }

        if ($methods !== []) {
            $methods = implode("\n\n", $methods);
            $content .= "### {$className} Methods\n\n{$methods}\n\n";
        }
    }

    return <<<TEMPLATE
    ## {$className}
    
    {$classDocs}
    
    {$content}
    TEMPLATE;
}

/**
 * @throws ExceptionInterface
 * @throws ReflectionException
 */
function renderMethod(ReflectionMethod $method): string
{
    $args = array_map(
        static function (ReflectionParameter $p): string {
            $type = ltrim($p->getType() . ' ');
            $def = $type . '$' . $p->getName();

            if ($p->isDefaultValueAvailable()) {
                $val = $p->isDefaultValueConstant()
                    ? $p->getDefaultValueConstantName()
                    : $p->getDefaultValue();
                $def .= ' = ' . VarExporter::export($val);
            }

            return $def;
        },
        $method->getParameters()
    );
    $argsStr = implode(', ', $args);
    if (strlen($argsStr) >= 80) {
        $argsStr = "\n    " . implode(",\n    ", $args) . "\n";
    }

    $returnType = $method->getReturnType();
    $def = "function {$method->getName()}({$argsStr})";
    $def = $method->isStatic()
        ? "static {$def}"
        : $def;
    $def = $returnType instanceof ReflectionType
        ? "{$def}: {$returnType}"
        : $def;
    $docBlock = PhpDoc::unpad($method->getDocComment());
    assert($docBlock !== null, 'isApi() guarantees a docblock exists');
    $fence = str_contains($docBlock, '```') ? '````' : '```';

    return "{$fence}php\n{$docBlock}\n{$def}\n{$fence}";
}

function renderProp(ReflectionProperty $prop): string
{
    $signature = implode(' ', Reflection::getModifierNames($prop->getModifiers())) . ' $' . $prop->getName() . ';';

    return PhpDoc::unpad($prop->getDocComment()) . "\n" . $signature;
}

/**
 * @param ReflectionProperty|ReflectionMethod $reflector
 */
function isApi(Reflector $reflector): bool
{
    $comment = $reflector->getDocComment();
    if ($comment === false) {
        return false;
    }

    return preg_match('~[\r\n ]+\* @api~', $comment) === 1;
}

file_put_contents(OUTPUT_FILE, '');

foreach (ENTRIES as $className => $options) {
    $rendered = renderClass(new ReflectionClass($className), $options);
    file_put_contents(OUTPUT_FILE, $rendered, FILE_APPEND);
}

$content = file_get_contents(OUTPUT_FILE);
assert($content !== false);
file_put_contents(OUTPUT_FILE, rtrim($content) . "\n");
