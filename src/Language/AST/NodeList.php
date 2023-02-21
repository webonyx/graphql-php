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
     * @var list<Node|array<string, mixed>>
     *
     * @phpstan-var list<T|array<string, mixed>> $nodes
     */
    protected array $nodes;

    /**
     * @param list<Node|array<string, mixed>> $nodes
     *
     * @phpstan-param list<T|array<string, mixed>> $nodes
     */
    public function __construct(array $nodes)
    {
        $this->nodes = $nodes;
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
        $node = $this->nodes[$offset];

        // @phpstan-ignore-next-line not really possible to express the correctness of this in PHP
        return \is_array($node)
            ? ($this->nodes[$offset] = AST::fromArray($node))
            : $node;
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

    public function unset(int $offset): void
    {
        unset($this->nodes[$offset]);
        $this->nodes = \array_values($this->nodes);
    }

    public function getIterator(): \Traversable
    {
        foreach ($this->nodes as $key => $_) {
            yield $key => $this->get($key);
        }
    }

    public function count(): int
    {
        return \count($this->nodes);
    }

    /**
     * Remove a portion of the NodeList and replace it with something else.
     *
     * @param Node|array<Node>|null $replacement
     *
     * @phpstan-param T|array<T>|null $replacement
     *
     * @phpstan-return NodeList<T> the NodeList with the extracted elements
     */
    public function splice(int $offset, int $length, $replacement = null): NodeList
    {
        $spliced = \array_splice($this->nodes, $offset, $length, $replacement);

        // @phpstan-ignore-next-line generic type mismatch
        return new NodeList($spliced);
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

        $merged = \array_merge($this->nodes, $list);

        // @phpstan-ignore-next-line generic type mismatch
        return new NodeList($merged);
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
