<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NodeList;
use GraphQL\Type\Definition\Directive;
use GraphQL\Validator\ValidationContext;

/**
 * Known argument names on directives
 *
 * A GraphQL directive is only valid if all supplied arguments are defined by
 * that field.
 */
class KnownArgumentNamesOnDirectives extends ValidationRule
{
    static protected function unknownDirectiveArgMessage(string $argName, string $directionName)
    {
        return 'Unknown argument "' . $argName .'" on directive "@'. $directionName .'".';
    }

    public function getVisitor(ValidationContext $context)
    {
        $directiveArgs = [];
        $schema = $context->getSchema();
        $definedDirectives = $schema !== null ? $schema->getDirectives() : Directive::getInternalDirectives();

        foreach($definedDirectives as $directive) {
            $directiveArgs[$directive->name] = array_map(
                function($arg) {
                    return $arg->name;
                },
                $directive->args
            );
        }

        $astDefinitions = $context->getDocument()->definitions;
        foreach ($astDefinitions as $def) {
            if ($def instanceof DirectiveDefinitionNode) {
                $name = $def->name->value;
                if ($def->arguments !== null) {

                    $arguments = $def->arguments;

                    if ($def->arguments instanceof NodeList) {
                        $arguments = iterator_to_array($def->arguments->getIterator());
                    }

                    $directiveArgs[$name] = array_map(function ($arg) { return $arg->name->value; }, $arguments);
                } else {
                    $directiveArgs[$name] = [];
                }
            }
        }

        return [
            NodeKind::DIRECTIVE => function (DirectiveNode $directiveNode) use ($directiveArgs, $context) {
                $directiveName = $directiveNode->name->value;
                $knownArgs = $directiveArgs[$directiveName] ?? null;

                if ($directiveNode->arguments !== null && $knownArgs) {
                    foreach($directiveNode->arguments as $argNode) {
                        $argName = $argNode->name->value;
                        if (!in_array($argName, $knownArgs)) {
                            $context->reportError(new Error(
                                self::unknownDirectiveArgMessage($argName, $directiveName),
                                [$argNode]
                            ));
                        }
                    }
                }
            },
        ];
    }
}
