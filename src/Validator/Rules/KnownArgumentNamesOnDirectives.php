<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Type\Definition\Directive;
use GraphQL\Utils\Utils;
use GraphQL\Validator\ASTValidationContext;
use GraphQL\Validator\SDLValidationContext;
use GraphQL\Validator\ValidationContext;

use function in_array;
use function sprintf;

/**
 * Known argument names on directives
 *
 * A GraphQL directive is only valid if all supplied arguments are defined by
 * that field.
 */
class KnownArgumentNamesOnDirectives extends ValidationRule
{
    /**
     * @param string[] $suggestedArgs
     */
    public static function unknownDirectiveArgMessage($argName, $directiveName, array $suggestedArgs)
    {
        $message = sprintf('Unknown argument "%s" on directive "@%s".', $argName, $directiveName);
        if (isset($suggestedArgs[0])) {
            $message .= sprintf(' Did you mean %s?', Utils::quotedOrList($suggestedArgs));
        }

        return $message;
    }

    public function getSDLVisitor(SDLValidationContext $context)
    {
        return $this->getASTVisitor($context);
    }

    public function getVisitor(ValidationContext $context)
    {
        return $this->getASTVisitor($context);
    }

    public function getASTVisitor(ASTValidationContext $context)
    {
        $directiveArgs     = [];
        $schema            = $context->getSchema();
        $definedDirectives = $schema !== null ? $schema->getDirectives() : Directive::getInternalDirectives();

        foreach ($definedDirectives as $directive) {
            $argNames = [];
            foreach ($directive->args as $arg) {
                $argNames[] = $arg->name;
            }

            $directiveArgs[$directive->name] = $argNames;
        }

        $astDefinitions = $context->getDocument()->definitions;
        foreach ($astDefinitions as $def) {
            if (! ($def instanceof DirectiveDefinitionNode)) {
                continue;
            }

            $name = $def->name->value;
            if ($def->arguments !== null) {
                $argNames = [];
                foreach ($def->arguments as $arg) {
                    $argNames[] = $arg->name->value;
                }

                $directiveArgs[$name] = $argNames;
            } else {
                $directiveArgs[$name] = [];
            }
        }

        return [
            NodeKind::DIRECTIVE => static function (DirectiveNode $directiveNode) use ($directiveArgs, $context): VisitorOperation {
                $directiveName = $directiveNode->name->value;
                $knownArgs     = $directiveArgs[$directiveName] ?? null;

                if ($directiveNode->arguments === null || $knownArgs === null) {
                    return Visitor::skipNode();
                }

                foreach ($directiveNode->arguments as $argNode) {
                    $argName = $argNode->name->value;
                    if (in_array($argName, $knownArgs, true)) {
                        continue;
                    }

                    $suggestions = Utils::suggestionList($argName, $knownArgs);
                    $context->reportError(new Error(
                        static::unknownDirectiveArgMessage($argName, $directiveName, $suggestions),
                        [$argNode]
                    ));
                }

                return Visitor::skipNode();
            },
        ];
    }
}
