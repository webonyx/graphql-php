<?php declare(strict_types=1);

namespace GraphQL\Executor;

/**
 * When the context implements `ScopedContext` the `clone()` method will be
 * called before passing the context to the subfields. This allows passing
 * information to subfields without affecting the parent fields.
 */
interface ScopedContext
{
    public function clone(): self;
}
