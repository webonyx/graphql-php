<?php declare(strict_types=1);

namespace GraphQL\Language\AST;

use GraphQL\Error\InvariantViolation;
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
     * @phpstan-var list<T|array<string, mixed>>
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

    /** @phpstan-return T */
    public function get(int $offset): Node
    {
        $node = $this->nodes[$offset];

        if (is_array($node)) {
            // @phpstan-ignore-next-line not really possible to express the correctness of this in PHP
            return $this->nodes[$offset] = AST::fromArray($node);
        }

        return $node;
    }

    /** @phpstan-param T $value */
    public function set(int $offset, Node $value): void
    {
        // @phpstan-ignore assign.propertyType (the assertion below guards against gaps)
        $this->nodes[$offset] = $value;

        assert($this->nodes === array_values($this->nodes));
    }

    /** @phpstan-param T $value */
    public function add(Node $value): void
    {
        $this->nodes[] = $value;
    }

    public function unset(int $offset): void
    {
        array_splice($this->nodes, $offset, 1);
    }

    public function getIterator(): \Traversable
    {
        foreach ($this->nodes as $key => $_) {
            yield $key => $this->get($key);
        }
    }

    public function count(): int
    {
        return count($this->nodes);
    }

    /**
     * Remove a portion of the NodeList and replace it with something else.
     *
     * @param T|iterable<T>|null $replacement
     *
     * @phpstan-return NodeList<T> the NodeList with the extracted elements
     */
    public function splice(int $offset, int $length, $replacement = null): NodeList
    {
        if (is_iterable($replacement) && ! is_array($replacement)) {
            $replacement = iterator_to_array($replacement);
        }

        return new NodeList(
            array_splice($this->nodes, $offset, $length, $replacement)
        );
    }

    /**
     * @phpstan-param iterable<int, T> $list
     *
     * @phpstan-return NodeList<T>
     */
    public function merge(iterable $list): NodeList
    {
        if (! is_array($list)) {
            $list = iterator_to_array($list);
        }

        return new NodeList(array_merge($this->nodes, $list));
    }

    /**
     * Returns a clone of this instance and all its children, except Location $loc.
     *
     * @throws \JsonException
     * @throws InvariantViolation
     *
     * @return static<T>
     */
    public function cloneDeep(): self
    {
        /** @var array<T> $empty */
        $empty = [];
        $cloned = new static($empty);
        foreach ($this->getIterator() as $node) {
            $cloned->add($node->cloneDeep());
        }

        return $cloned;
    }
}
