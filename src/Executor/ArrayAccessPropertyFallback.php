<?php declare(strict_types=1);

namespace GraphQL\Executor;

/**
 * When a value implementing this is resolved by the default field resolver,
 * a key that is not set by array access is read from the property of the same name.
 * Plain `\ArrayAccess` values only use array access, so their properties stay hidden.
 *
 * @template TKey
 * @template TValue
 *
 * @extends \ArrayAccess<TKey, TValue>
 */
interface ArrayAccessPropertyFallback extends \ArrayAccess {}
