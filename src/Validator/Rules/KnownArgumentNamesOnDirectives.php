<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use function array_map;
use GraphQL\Error\Error;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Type\Definition\Argument;
use GraphQL\Type\Definition\Directive;
use GraphQL\Utils\Utils;
use GraphQL\Validator\ASTValidationContext;
use GraphQL\Validator\SDLValidationContext;
use GraphQL\Validator\ValidationContext;
use function in_array;

/**
 * Known argument names on directives.
 *
 * A GraphQL directive is only valid if all supplied arguments are defined by
 * that field.
 *
 * @phpstan-import-type VisitorArray from Visitor
 */
class KnownArgumentNamesOnDirectives extends ValidationRule
{
    /**
     * @param array<string> $suggestedArgs
     */
    public static function unknownDirectiveArgMessage(string $argName, string $directiveName, array $suggestedArgs): string
    {
        $message = "Unknown argument \"{$argName}\" on directive \"@{$directiveName}\".";

        if (isset($suggestedArgs[0])) {
            $suggestions = Utils::quotedOrList($suggestedArgs);
            $message .= " Did you mean {$suggestions}?";
        }

        return $message;
    }

    public function getSDLVisitor(SDLValidationContext $context): array
    {
        return $this->getASTVisitor($context);
    }

    public function getVisitor(ValidationContext $context): array
    {
        return $this->getASTVisitor($context);
    }

    /**
     * @phpstan-return VisitorArray
     */
    public function getASTVisitor(ASTValidationContext $context): array
    {
        $directiveArgs = [];
        $schema = $context->getSchema();
        $definedDirectives = null !== $schema
            ? $schema->getDirectives()
            : Directive::getInternalDirectives();

        foreach ($definedDirectives as $directive) {
            $directiveArgs[$directive->name] = array_map(
                static fn (Argument $arg): string => $arg->name,
                $directive->args
            );
        }

        $astDefinitions = $context->getDocument()->definitions;
        foreach ($astDefinitions as $def) {
            if (! ($def instanceof DirectiveDefinitionNode)) {
                continue;
            }

            $name = $def->name->value;
            if (null !== $def->arguments) {
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
                $knownArgs = $directiveArgs[$directiveName] ?? null;

                if (null === $directiveNode->arguments || null === $knownArgs) {
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
