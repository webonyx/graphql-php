<?php
require __DIR__ . '/../vendor/autoload.php';

use GraphQL\Utils\Utils;

$outputFile = __DIR__  . '/../docs/reference.md';

$entries = [
    \GraphQL\GraphQL::class,
    \GraphQL\Type\Definition\Type::class,
    \GraphQL\Type\Definition\ResolveInfo::class,
    \GraphQL\Type\Definition\DirectiveLocation::class => ['constants' => true],
    \GraphQL\Type\SchemaConfig::class,
    \GraphQL\Type\Schema::class,
    \GraphQL\Language\Parser::class,
    \GraphQL\Language\Printer::class,
    \GraphQL\Language\Visitor::class,
    \GraphQL\Language\AST\NodeKind::class => ['constants' => true],
    \GraphQL\Executor\Executor::class,
    \GraphQL\Executor\ExecutionResult::class,
    \GraphQL\Executor\Promise\PromiseAdapter::class,
    \GraphQL\Validator\DocumentValidator::class,
    \GraphQL\Error\Error::class => ['constants' => true, 'methods' => true, 'props' => true],
    \GraphQL\Error\Warning::class => ['constants' => true, 'methods' => true],
    \GraphQL\Error\ClientAware::class,
    \GraphQL\Error\Debug::class => ['constants' => true],
    \GraphQL\Error\FormattedError::class,
    \GraphQL\Server\StandardServer::class,
    \GraphQL\Server\ServerConfig::class,
    \GraphQL\Server\Helper::class,
    \GraphQL\Server\OperationParams::class,
    \GraphQL\Utils\BuildSchema::class,
    \GraphQL\Utils\AST::class,
    \GraphQL\Utils\SchemaPrinter::class
];

function renderClassMethod(ReflectionMethod $method) {
    $args = Utils::map($method->getParameters(), function(ReflectionParameter $p) {
        $type = ltrim($p->getType() . " ");
        $def = $type . '$' . $p->getName();

        if ($p->isDefaultValueAvailable()) {
            $val = $p->isDefaultValueConstant() ? $p->getDefaultValueConstantName() : $p->getDefaultValue();
            $def .= " = " . (is_array($val) ? '[]' : Utils::printSafe($val));
        }

        return $def;
    });
    $argsStr = implode(", ", $args);
    if (strlen($argsStr) >= 80) {
        $argsStr = "\n    " . implode(",\n    ", $args) . "\n";
    }
    $def = "function {$method->getName()}($argsStr)";
    $def = $method->isStatic() ? "static $def" : $def;
    $docBlock = unpadDocblock($method->getDocComment());

    return <<<TEMPLATE
```php
{$docBlock}
{$def}
```
TEMPLATE;
}

function renderConstant($value, $name) {
    return "const $name = " . Utils::printSafe($value) . ";";
}

function renderProp(ReflectionProperty $prop) {
    $signature = implode(" ", Reflection::getModifierNames($prop->getModifiers())) . ' $' . $prop->getName() . ';';
    return unpadDocblock($prop->getDocComment()) . "\n" . $signature;
}

function renderClass(ReflectionClass $class, $options) {
    $classDocs = unwrapDocblock(unpadDocblock($class->getDocComment()));
    $content = '';
    $label = $class->isInterface() ? 'Interface' : 'Class';

    if (!empty($options['constants'])) {
        $constants = $class->getConstants();
        $constants = Utils::map($constants, 'renderConstant');
        if (!empty($constants)) {
            $constants = "```php\n" . implode("\n", $constants) . "\n```";
            $content .= "**$label Constants:** \n$constants\n\n";
        }
    }

    if (!empty($options['props'])) {
        $props = $class->getProperties(ReflectionProperty::IS_PUBLIC);
        $props = Utils::filter($props, 'isApi');
        if (!empty($props)) {
            $props = Utils::map($props, 'renderProp');
            $props = "```php\n" . implode("\n\n", $props) . "\n```";
            $content .= "**$label Props:** \n$props\n\n";
        }
    }

    if (!empty($options['methods'])) {
        $methods = $class->getMethods(ReflectionMethod::IS_PUBLIC);
        $methods = Utils::filter($methods, 'isApi');
        if (!empty($methods)) {
            $renderedMethods = Utils::map($methods, 'renderClassMethod');
            $renderedMethods = implode("\n\n", $renderedMethods);
            $content .= "**$label Methods:** \n{$renderedMethods}\n";
        }
    }

    return <<<TEMPLATE
# {$class->getName()}
{$classDocs}

$content
TEMPLATE;
}

function unwrapDocblock($docBlock, $stripAnnotations = true) {
    $content = preg_replace('~([\r\n]) \* (.*)~i', '$1$2', $docBlock); // strip *
    $content = preg_replace('~([\r\n])[\* ]+([\r\n])~i', '$1$2', $content); // strip single-liner *
    $content = substr($content, 3); // strip leading /**
    $content = substr($content, 0, -2); // strip trailing */
    if ($stripAnnotations) {
        $content = preg_replace('~([\r\n])@.*([\r\n])~i', '$1', $content); // strip annotations
    }
    return trim($content);
}

function unpadDocblock($docBlock) {
    $lines = explode("\n", $docBlock);
    $lines = \GraphQL\Utils\Utils::map($lines, function($line) {
        return ' ' . trim($line);
    });
    return trim(implode("\n", $lines));
}

function isApi($entry) {
    /** @var ReflectionProperty|ReflectionMethod $entry */
    $isApi = preg_match('~[\r\n ]+\* @api~', $entry->getDocComment());
    return $isApi;
}

file_put_contents($outputFile, '');

foreach ($entries as $className => $options) {
    $className = is_int($className) ? $options : $className;
    $options = is_array($options) ? $options : ['methods' => true, 'props' => true];

    $rendered = renderClass(new ReflectionClass($className), $options);
    file_put_contents($outputFile, $rendered, FILE_APPEND);
}
