<?php declare(strict_types=1);

namespace GraphQL\Utils;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\DirectiveNode;

/**
 * @internal
 */
final class AppliedDirectives
{
    /**
     * @param iterable<mixed>|null $directives
     *
     * @throws InvariantViolation
     *
     * @return array<DirectiveNode>
     */
    public static function normalize(?iterable $directives): array
    {
        if ($directives === null) {
            return [];
        }

        $normalized = [];
        foreach ($directives as $directive) {
            if (! $directive instanceof DirectiveNode) {
                $notDirective = Utils::printSafe($directive);
                throw new InvariantViolation("Expected applied directive to be DirectiveNode, got: {$notDirective}.");
            }

            $normalized[] = $directive;
        }

        return $normalized;
    }
}
