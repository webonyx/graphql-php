<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Type\Definition\Argument;
use GraphQL\Type\Definition\Directive;
use GraphQL\Utils\Utils;
use GraphQL\Validator\QueryValidationContext;
use GraphQL\Validator\SDLValidationContext;
use GraphQL\Validator\ValidationContext;

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
    /** @param array<string> $suggestedArgs */
    public static function unknownDirectiveArgMessage(string $argName, string $directiveName, array $suggestedArgs): string
    {
        $message = "Unknown argument \"{$argName}\" on directive \"@{$directiveName}\".";

        if (isset($suggestedArgs[0])) {
            $suggestions = Utils::quotedOrList($suggestedArgs);
            $message .= " Did you mean {$suggestions}?";
        }

        return $message;
    }

    /** @throws InvariantViolation */
    public function getSDLVisitor(SDLValidationContext $context): array
    {
        return $this->getASTVisitor($context);
    }

    /** @throws InvariantViolation */
    public function getVisitor(QueryValidationContext $context): array
    {
        return $this->getASTVisitor($context);
    }

    /**
     * @phpstan-return VisitorArray
     *
     * @throws InvariantViolation
     */
    public function getASTVisitor(ValidationContext $context): array
    {
        $directiveArgs = [];
        $schema = $context->getSchema();
        $definedDirectives = $schema !== null
            ? $schema->getDirectives()
            : Directive::getInternalDirectives();

        foreach ($definedDirectives as $directive) {
            $directiveArgs[$directive->name] = \array_map(
                static fn (Argument $arg): string => $arg->name,
                $directive->args
            );
        }

        $astDefinitions = $context->getDocument()->definitions;
        foreach ($astDefinitions as $def) {
            if ($def instanceof DirectiveDefinitionNode) {
                $argNames = [];
                foreach ($def->arguments as $arg) {
                    $argNames[] = $arg->name->value;
                }

                $directiveArgs[$def->name->value] = $argNames;
            }
        }

        return [
            NodeKind::DIRECTIVE => static function (DirectiveNode $directiveNode) use ($directiveArgs, $context): VisitorOperation {
                $directiveName = $directiveNode->name->value;

                if (! isset($directiveArgs[$directiveName])) {
                    return Visitor::skipNode();
                }
                $knownArgs = $directiveArgs[$directiveName];

                foreach ($directiveNode->arguments as $argNode) {
                    $argName = $argNode->name->value;
                    if (! \in_array($argName, $knownArgs, true)) {
                        $suggestions = Utils::suggestionList($argName, $knownArgs);
                        $context->reportError(new Error(
                            static::unknownDirectiveArgMessage($argName, $directiveName, $suggestions),
                            [$argNode]
                        ));
                    }
                }

                return Visitor::skipNode();
            },
        ];
    }
}
