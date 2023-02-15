<?php declare(strict_types=1);

namespace GraphQL\Language\AST;

use GraphQL\Utils\AST;

/**
 * @template T of Node
 *
 * @phpstan-implements \IteratorAggregate<int, T>
 */
class NodeList implements \IteratorAggregate, \Countable
{
    /**
     * @var array<Node>
     *
     * @phpstan-var list<T>
     */
    private array $nodes = [];

    /**
     * @param iterable<Node|array> $nodes
     *
     * @phpstan-param iterable<T|array<string, mixed>> $nodes
     */
    public function __construct(iterable $nodes)
    {
        foreach ($nodes as $node) {
            $this->nodes[] = $this->process($node);
        }
    }

    public function has(int $offset): bool
    {
        return isset($this->nodes[$offset]);
    }

    /**
     * @phpstan-return T
     */
    public function get(int $offset): Node
    {
        return $this->nodes[$offset];
    }

    /**
     * @phpstan-param T $value
     */
    public function set(int $offset, Node $value): void
    {
        $this->nodes[$offset] = $value;

        assert($this->nodes === \array_values($this->nodes));
    }

    /**
     * @phpstan-param T $value
     */
    public function add(Node $value): void
    {
        $this->nodes[] = $value;
    }

    /**
     * @param Node|array<string, mixed> $value
     *
     * @phpstan-param T|array<string, mixed> $value
     *
     * @phpstan-return T
     */
    private function process($value): Node
    {
        if (\is_array($value)) {
            /** @phpstan-var T $value */
            $value = AST::fromArray($value);
        }

        return $value;
    }

    public function remove(Node $node): void
    {
        $foundKey = \array_search($node, $this->nodes, true);
        if ($foundKey === false) {
            throw new \InvalidArgumentException('Node not found in NodeList');
        }

        unset($this->nodes[$foundKey]);
        $this->nodes = \array_values($this->nodes);
    }

    public function getIterator(): \Traversable
    {
        yield from $this->nodes;
    }

    public function count(): int
    {
        return \count($this->nodes);
    }

    /**
     * Remove a portion of the NodeList and replace it with something else.
     *
     * @param T|array<T>|null $replacement
     *
     * @phpstan-return NodeList<T> the NodeList with the extracted elements
     */
    public function splice(int $offset, int $length, $replacement = null): NodeList
    {
        return new NodeList(
            \array_splice($this->nodes, $offset, $length, $replacement)
        );
    }

    /**
     * @phpstan-param iterable<int, T> $list
     *
     * @phpstan-return NodeList<T>
     */
    public function merge(iterable $list): NodeList
    {
        if (! \is_array($list)) {
            $list = \iterator_to_array($list);
        }

        return new NodeList(\array_merge($this->nodes, $list));
    }

    /**
     * Returns a clone of this instance and all its children, except Location $loc.
     *
     * @return static<T>
     */
    public function cloneDeep(): self
    {
        /** @var static<T> $cloned */
        $cloned = new static([]);
        foreach ($this->getIterator() as $node) {
            $cloned->add($node->cloneDeep());
        }

        return $cloned;
    }
}
