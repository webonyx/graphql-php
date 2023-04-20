<?php declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use GraphQL\Error\ClientAware;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use GraphQL\Error\Warning;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Executor;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\GraphQL;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Language\Visitor;
use GraphQL\Server\Helper;
use GraphQL\Server\OperationParams;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use GraphQL\Utils\AST;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\PhpDoc;
use GraphQL\Utils\SchemaPrinter;
use GraphQL\Validator\DocumentValidator;
use Symfony\Component\VarExporter\Exception\ExceptionInterface;
use Symfony\Component\VarExporter\VarExporter;

const OUTPUT_FILE = __DIR__ . '/docs/class-reference.md';

const ENTRIES = [
    GraphQL::class => [],
    Type::class => [],
    ResolveInfo::class => [],
    DirectiveLocation::class => ['constants' => true],
    SchemaConfig::class => [],
    Schema::class => [],
    Parser::class => [],
    Printer::class => [],
    Visitor::class => [],
    NodeKind::class => ['constants' => true],
    Executor::class => [],
    ExecutionResult::class => [],
    PromiseAdapter::class => [],
    DocumentValidator::class => [],
    Error::class => ['constants' => true],
    Warning::class => ['constants' => true],
    ClientAware::class => [],
    DebugFlag::class => ['constants' => true],
    FormattedError::class => [],
    StandardServer::class => [],
    ServerConfig::class => [],
    Helper::class => [],
    OperationParams::class => [],
    BuildSchema::class => [],
    AST::class => [],
    SchemaPrinter::class => [],
];

/**
 * @param ReflectionClass<object>                               $class
 * @param array{constants?: bool, props?: bool, methods?: bool} $options
 *
 * @throws ExceptionInterface
 */
function renderClass(ReflectionClass $class, array $options): string
{
    $classDocs = PhpDoc::unwrap(PhpDoc::unpad($class->getDocComment()));
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

    return <<<TEMPLATE
```php
{$docBlock}
{$def}
```
TEMPLATE;
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
