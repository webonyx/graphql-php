<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

use ArrayAccess;
use Countable;
use GraphQL\Utils\AST;
use IteratorAggregate;
// phpcs:ignore SlevomatCodingStandard.Namespaces.UnusedUses.UnusedUse
use ReturnTypeWillChange;
use Traversable;

use function array_merge;
use function array_splice;
use function count;
use function is_array;

/**
 * @template T of Node
 * @phpstan-implements ArrayAccess<int|string, T>
 * @phpstan-implements IteratorAggregate<T>
 */
class NodeList implements ArrayAccess, IteratorAggregate, Countable
{
    /**
     * @var array<Node|array>
     * @phpstan-var array<T|array<string, mixed>>
     */
    private $nodes;

    /**
     * @param array<Node|array<string, mixed>> $nodes
     * @phpstan-param array<T|array<string, mixed>> $nodes
     *
     * @phpstan-return self<T>
     */
    public static function create(array $nodes): self
    {
        return new static($nodes);
    }

    /**
     * @param array<Node|array> $nodes
     * @phpstan-param array<T|array<string, mixed>> $nodes
     */
    public function __construct(array $nodes)
    {
        $this->nodes = $nodes;
    }

    /**
     * @param int|string $offset
     */
    #[ReturnTypeWillChange]
    public function offsetExists($offset): bool
    {
        return isset($this->nodes[$offset]);
    }

    /**
     * TODO enable strict typing by changing how the Visitor deals with NodeList.
     * Ideally, this function should always return a Node instance.
     * However, the Visitor currently allows mutation of the NodeList
     * and puts arbitrary values in the NodeList, such as strings.
     * We will have to switch to using an array or a less strict
     * type instead so we can enable strict typing in this class.
     *
     * @param int|string $offset
     *
     * @phpstan-return T
     */
    #[ReturnTypeWillChange]
    public function offsetGet($offset)// : Node
    {
        $item = $this->nodes[$offset];

        if (is_array($item)) {
            /** @phpstan-var T $node */
            $node                 = AST::fromArray($item);
            $this->nodes[$offset] = $node;

            return $node;
        }

        return $item;
    }

    /**
     * @param int|string|null           $offset
     * @param Node|array<string, mixed> $value
     * @phpstan-param T|array<string, mixed> $value
     */
    #[ReturnTypeWillChange]
    public function offsetSet($offset, $value): void
    {
        if (is_array($value)) {
            /** @phpstan-var T $value */
            $value = AST::fromArray($value);
        }

        // Happens when a Node is pushed via []=
        if ($offset === null) {
            $this->nodes[] = $value;

            return;
        }

        $this->nodes[$offset] = $value;
    }

    /**
     * @param int|string $offset
     */
    #[ReturnTypeWillChange]
    public function offsetUnset($offset): void
    {
        unset($this->nodes[$offset]);
    }

    /**
     * @param mixed $replacement
     *
     * @phpstan-return NodeList<T>
     */
    public function splice(int $offset, int $length, $replacement = null): NodeList
    {
        /** @var array<T> $nodes */
        $nodes = array_splice($this->nodes, $offset, $length, $replacement);

        return new NodeList($nodes);
    }

    /**
     * @param NodeList|array<Node|array<string, mixed>> $list
     * @phpstan-param NodeList<T>|array<T> $list
     *
     * @phpstan-return NodeList<T>
     */
    public function merge($list): NodeList
    {
        if ($list instanceof self) {
            $list = $list->nodes;
        }

        return new NodeList(array_merge($this->nodes, $list));
    }

    public function getIterator(): Traversable
    {
        foreach ($this->nodes as $key => $_) {
            yield $this->offsetGet($key);
        }
    }

    public function count(): int
    {
        return count($this->nodes);
    }

    /**
     * Returns a clone of this instance and all its children, except Location $loc.
     *
     * @return static<T>
     */
    public function cloneDeep(): self
    {
        $cloned = clone $this;
        foreach ($this->nodes as $key => $node) {
            $cloned[$key] = $node->cloneDeep();
        }

        return $cloned;
    }
}
