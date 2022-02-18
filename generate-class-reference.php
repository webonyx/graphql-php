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
use GraphQL\Utils\SchemaPrinter;
use GraphQL\Validator\DocumentValidator;
use Symfony\Component\VarExporter\VarExporter;

$outputFile = __DIR__ . '/docs/class-reference.md';

$entries = [
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
 */
function renderClass(ReflectionClass $class, array $options): string
{
    $classDocs = unwrapDocblock(unpadDocblock($class->getDocComment()));
    $content = '';
    $className = $class->getName();

    if ($options['constants'] ?? false) {
        $constants = [];
        foreach ($class->getConstants(/* TODO enable with PHP 8 ReflectionClassConstant::IS_PUBLIC */) as $name => $value) {
            $constants[] = "const $name = " . VarExporter::export($value) . ';';
        }

        if (count($constants) > 0) {
            $constants = "```php\n" . implode("\n", $constants) . "\n```";
            $content .= "### $className Constants\n\n$constants\n\n";
        }
    }

    if ($options['props'] ?? true) {
        $props = [];
        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (isApi($property)) {
                $props[] = renderProp($property);
            }
        }

        if (count($props) > 0) {
            $props = "```php\n" . implode("\n\n", $props) . "\n```";
            $content .= "### $className Props\n\n$props\n\n";
        }
    }

    if ($options['methods'] ?? true) {
        $methods = [];
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (isApi($method)) {
                $methods[] = renderMethod($method);
            }
        }

        if (count($methods) > 0) {
            $methods = implode("\n\n", $methods);
            $content .= "### $className Methods\n\n{$methods}\n\n";
        }
    }

    return <<<TEMPLATE
    ## {$className}
    
    {$classDocs}
    
    $content
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
    $def = "function {$method->getName()}($argsStr)";
    $def = $method->isStatic()
        ? "static $def"
        : $def;
    $def = $returnType instanceof ReflectionType
        ? "$def: $returnType"
        : $def;
    $docBlock = unpadDocblock($method->getDocComment());

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

    return unpadDocblock($prop->getDocComment()) . "\n" . $signature;
}

function unwrapDocblock(string $docBlock): string
{
    if ($docBlock === '') {
        return '';
    }

    $content = preg_replace('~([\r\n]) \* (.*)~i', '$1$2', $docBlock); // strip *
    assert(is_string($content), 'regex is statically known to be valid');

    $content = preg_replace('~([\r\n])[\* ]+([\r\n])~i', '$1$2', $content); // strip single-liner *
    assert(is_string($content), 'regex is statically known to be valid');

    $content = substr($content, 3); // strip leading /**
    $content = substr($content, 0, -2); // strip trailing */

    return trim($content);
}

/**
 * @param string|false $docBlock
 */
function unpadDocblock($docBlock): string
{
    if ($docBlock === false) {
        return '';
    }

    $lines = explode("\n", $docBlock);
    $lines = array_map(
        static fn (string $line): string => ' ' . trim($line),
        $lines
    );

    return trim(implode("\n", $lines));
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

file_put_contents($outputFile, '');

foreach ($entries as $className => $options) {
    $rendered = renderClass(new ReflectionClass($className), $options);
    file_put_contents($outputFile, $rendered, FILE_APPEND);
}
