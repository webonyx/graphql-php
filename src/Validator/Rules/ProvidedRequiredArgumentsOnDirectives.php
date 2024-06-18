<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\Printer;
use GraphQL\Language\Visitor;
use GraphQL\Type\Definition\Argument;
use GraphQL\Type\Definition\Directive;
use GraphQL\Validator\QueryValidationContext;
use GraphQL\Validator\SDLValidationContext;
use GraphQL\Validator\ValidationContext;

/**
 * Provided required arguments on directives.
 *
 * A directive is only valid if all required (non-null without a
 * default value) field arguments have been provided.
 *
 * @phpstan-import-type VisitorArray from Visitor
 */
class ProvidedRequiredArgumentsOnDirectives extends ValidationRule
{
    public static function missingDirectiveArgMessage(string $directiveName, string $argName, string $type): string
    {
        return "Directive \"@{$directiveName}\" argument \"{$argName}\" of type \"{$type}\" is required but not provided.";
    }

    /** @throws \Exception */
    public function getSDLVisitor(SDLValidationContext $context): array
    {
        return $this->getASTVisitor($context);
    }

    /** @throws \Exception */
    public function getVisitor(QueryValidationContext $context): array
    {
        return $this->getASTVisitor($context);
    }

    /**
     * @phpstan-return VisitorArray
     *
     * @throws \Exception
     * @throws \InvalidArgumentException
     * @throws \ReflectionException
     * @throws Error
     * @throws InvariantViolation
     */
    public function getASTVisitor(ValidationContext $context): array
    {
        $requiredArgsMap = [];
        $schema = $context->getSchema();
        $definedDirectives = $schema === null
            ? Directive::getInternalDirectives()
            : $schema->getDirectives();

        foreach ($definedDirectives as $directive) {
            $directiveArgs = [];
            foreach ($directive->args as $arg) {
                if ($arg->isRequired()) {
                    $directiveArgs[$arg->name] = $arg;
                }
            }

            $requiredArgsMap[$directive->name] = $directiveArgs;
        }

        $astDefinition = $context->getDocument()->definitions;
        foreach ($astDefinition as $def) {
            if ($def instanceof DirectiveDefinitionNode) {
                $arguments = $def->arguments;

                $requiredArgs = [];
                foreach ($arguments as $argument) {
                    if ($argument->type instanceof NonNullTypeNode && ! isset($argument->defaultValue)) {
                        $requiredArgs[$argument->name->value] = $argument;
                    }
                }

                $requiredArgsMap[$def->name->value] = $requiredArgs;
            }
        }

        return [
            NodeKind::DIRECTIVE => [
                // Validate on leave to allow for deeper errors to appear first.
                'leave' => static function (DirectiveNode $directiveNode) use ($requiredArgsMap, $context): ?string {
                    $directiveName = $directiveNode->name->value;
                    $requiredArgs = $requiredArgsMap[$directiveName] ?? null;
                    if ($requiredArgs === null || $requiredArgs === []) {
                        return null;
                    }

                    $argNodeMap = [];
                    foreach ($directiveNode->arguments as $arg) {
                        $argNodeMap[$arg->name->value] = $arg;
                    }

                    foreach ($requiredArgs as $argName => $arg) {
                        if (! isset($argNodeMap[$argName])) {
                            $argType = $arg instanceof Argument
                                ? $arg->getType()->toString()
                                : Printer::doPrint($arg->type);

                            $context->reportError(
                                new Error(static::missingDirectiveArgMessage($directiveName, $argName, $argType), [$directiveNode])
                            );
                        }
                    }

                    return null;
                },
            ],
        ];
    }
}
