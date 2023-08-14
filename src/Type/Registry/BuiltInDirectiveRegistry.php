<?php declare(strict_types=1);

namespace GraphQL\Type\Registry;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\Directive;

interface BuiltInDirectiveRegistry
{
    /** @throws InvariantViolation */
    public function deprecatedDirective(): Directive;

    /** @throws InvariantViolation */
    public function includeDirective(): Directive;

    /** @throws InvariantViolation */
    public function skipDirective(): Directive;

    /**
     * @throws InvariantViolation
     *
     * @return array<string, Directive>
     */
    public function internalDirectives(): array;
}
