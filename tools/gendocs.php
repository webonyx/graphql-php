<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

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
use GraphQL\Utils\Utils;
use GraphQL\Validator\DocumentValidator;

$outputFile = __DIR__ . '/../docs/reference.md';

$entries = [
    GraphQL::class,
    Type::class,
    ResolveInfo::class,
    DirectiveLocation::class => ['constants' => true],
    SchemaConfig::class,
    Schema::class,
    Parser::class,
    Printer::class,
    Visitor::class,
    NodeKind::class => ['constants' => true],
    Executor::class,
    ExecutionResult::class,
    PromiseAdapter::class,
    DocumentValidator::class,
    Error::class           => ['constants' => true, 'methods' => true, 'props' => true],
    Warning::class         => ['constants' => true, 'methods' => true],
    ClientAware::class,
    DebugFlag::class       => ['constants' => true],
    FormattedError::class,
    StandardServer::class,
    ServerConfig::class,
    Helper::class,
    OperationParams::class,
    BuildSchema::class,
    AST::class,
    SchemaPrinter::class,
];

function renderClassMethod(ReflectionMethod $method)
{
    $args    = Utils::map($method->getParameters(), static function (ReflectionParameter $p) {
        $type = ltrim($p->getType() . ' ');
        $def  = $type . '$' . $p->getName();

        if ($p->isDefaultValueAvailable()) {
            $val  = $p->isDefaultValueConstant()
                ? $p->getDefaultValueConstantName()
                : $p->getDefaultValue();
            $def .= ' = ' . Utils::printSafeJson($val);
        }

        return $def;
    });
    $argsStr = implode(', ', $args);
    if (strlen($argsStr) >= 80) {
        $argsStr = "\n    " . implode(",\n    ", $args) . "\n";
    }

    $returnType = $method->getReturnType();
    $def        = "function {$method->getName()}($argsStr)";
    $def        = $method->isStatic() ? "static $def" : $def;
    $def        = $returnType ? "$def: $returnType" : $def;
    $docBlock   = unpadDocblock($method->getDocComment());

    return <<<TEMPLATE
```php
{$docBlock}
{$def}
```
TEMPLATE;
}

function renderConstant($value, $name)
{
    return "const $name = " . Utils::printSafeJson($value) . ';';
}

function renderProp(ReflectionProperty $prop)
{
    $signature = implode(' ', Reflection::getModifierNames($prop->getModifiers())) . ' $' . $prop->getName() . ';';

    return unpadDocblock($prop->getDocComment()) . "\n" . $signature;
}

function renderClass(ReflectionClass $class, $options)
{
    $classDocs = unwrapDocblock(unpadDocblock($class->getDocComment()));
    $content   = '';
    $label     = $class->isInterface() ? 'Interface' : 'Class';

    if (! empty($options['constants'])) {
        $constants = $class->getConstants();
        $constants = Utils::map($constants, 'renderConstant');
        if (! empty($constants)) {
            $constants = "```php\n" . implode("\n", $constants) . "\n```";
            $content  .= "**$label Constants:** \n$constants\n\n";
        }
    }

    if (! empty($options['props'])) {
        $props = $class->getProperties(ReflectionProperty::IS_PUBLIC);
        $props = Utils::filter($props, 'isApi');
        if (! empty($props)) {
            $props    = Utils::map($props, 'renderProp');
            $props    = "```php\n" . implode("\n\n", $props) . "\n```";
            $content .= "**$label Props:** \n$props\n\n";
        }
    }

    if (! empty($options['methods'])) {
        $methods = $class->getMethods(ReflectionMethod::IS_PUBLIC);
        $methods = Utils::filter($methods, 'isApi');
        if (! empty($methods)) {
            $renderedMethods = Utils::map($methods, 'renderClassMethod');
            $renderedMethods = implode("\n\n", $renderedMethods);
            $content        .= "**$label Methods:** \n{$renderedMethods}\n";
        }
    }

    return <<<TEMPLATE
# {$class->getName()}
{$classDocs}

$content
TEMPLATE;
}

function unwrapDocblock($docBlock, $stripAnnotations = true)
{
    $content = preg_replace('~([\r\n]) \* (.*)~i', '$1$2', $docBlock); // strip *
    $content = preg_replace('~([\r\n])[\* ]+([\r\n])~i', '$1$2', $content); // strip single-liner *
    $content = substr($content, 3); // strip leading /**
    $content = substr($content, 0, -2); // strip trailing */
    if ($stripAnnotations) {
        $content = preg_replace('~([\r\n])@.*([\r\n])~i', '$1', $content); // strip annotations
    }

    return trim($content);
}

function unpadDocblock($docBlock)
{
    $lines = explode("\n", $docBlock);
    $lines = Utils::map($lines, static function ($line) {
        return ' ' . trim($line);
    });

    return trim(implode("\n", $lines));
}

function isApi($entry)
{
    /** @var ReflectionProperty|ReflectionMethod $entry */
    return preg_match('~[\r\n ]+\* @api~', $entry->getDocComment());
}

file_put_contents($outputFile, '');

foreach ($entries as $className => $options) {
    $className = is_int($className) ? $options : $className;
    $options   = is_array($options) ? $options : ['methods' => true, 'props' => true];

    $rendered = renderClass(new ReflectionClass($className), $options);
    file_put_contents($outputFile, $rendered, FILE_APPEND);
}
