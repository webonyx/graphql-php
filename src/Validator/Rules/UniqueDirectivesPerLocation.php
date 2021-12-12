<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\Visitor;
use GraphQL\Type\Definition\Directive;
use GraphQL\Validator\ASTValidationContext;
use GraphQL\Validator\SDLValidationContext;
use GraphQL\Validator\ValidationContext;

use function sprintf;

/**
 * Unique directive names per location
 *
 * A GraphQL document is only valid if all non-repeatable directives at
 * a given location are uniquely named.
 *
 * @phpstan-import-type VisitorArray from Visitor
 */
class UniqueDirectivesPerLocation extends ValidationRule
{
    public function getVisitor(ValidationContext $context): array
    {
        return $this->getASTVisitor($context);
    }

    public function getSDLVisitor(SDLValidationContext $context): array
    {
        return $this->getASTVisitor($context);
    }

    /**
     * @phpstan-return VisitorArray
     */
    public function getASTVisitor(ASTValidationContext $context): array
    {
        $uniqueDirectiveMap = [];

        $schema            = $context->getSchema();
        $definedDirectives = $schema !== null
            ? $schema->getDirectives()
            : Directive::getInternalDirectives();
        foreach ($definedDirectives as $directive) {
            $uniqueDirectiveMap[$directive->name] = ! $directive->isRepeatable;
        }

        $astDefinitions = $context->getDocument()->definitions;
        foreach ($astDefinitions as $definition) {
            if (! ($definition instanceof DirectiveDefinitionNode)) {
                continue;
            }

            $uniqueDirectiveMap[$definition->name->value] = $definition->repeatable;
        }

        return [
            'enter' => static function (Node $node) use ($uniqueDirectiveMap, $context): void {
                if (! isset($node->directives)) {
                    return;
                }

                $knownDirectives = [];

                /** @var DirectiveNode $directive */
                foreach ($node->directives as $directive) {
                    $directiveName = $directive->name->value;

                    if (! isset($uniqueDirectiveMap[$directiveName])) {
                        continue;
                    }

                    if (isset($knownDirectives[$directiveName])) {
                        $context->reportError(new Error(
                            static::duplicateDirectiveMessage($directiveName),
                            [$knownDirectives[$directiveName], $directive]
                        ));
                    } else {
                        $knownDirectives[$directiveName] = $directive;
                    }
                }
            },
        ];
    }

    public static function duplicateDirectiveMessage(string $directiveName): string
    {
        return sprintf('The directive "%s" can only be used once at this location.', $directiveName);
    }
}
