<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor\TestClasses;

/**
 * Exposes attributes through array access, like Eloquent models or Laravel collections.
 *
 * @phpstan-implements \ArrayAccess<string, mixed>
 */
class ArrayAccessAttributes implements \ArrayAccess
{
    /** @var array<string, mixed> */
    private array $attributes;

    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }

    /** @param mixed $offset */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    /**
     * @param mixed $offset
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->attributes[$offset] ?? null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value): void
    {
        $this->attributes[$offset] = $value;
    }

    /** @param mixed $offset */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset): void
    {
        unset($this->attributes[$offset]);
    }
}
