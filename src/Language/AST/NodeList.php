<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

use ArrayAccess;
use Countable;
use GraphQL\Utils\AST;
use InvalidArgumentException;
use IteratorAggregate;
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
     * @var Node[]
     * @phpstan-var array<T>
     */
    private $nodes;

    /**
     * @param Node[] $nodes
     *
     * @phpstan-param array<T> $nodes
     * @phpstan-return self<T>
     */
    public static function create(array $nodes) : self
    {
        return new static($nodes);
    }

    /**
     * @param Node[] $nodes
     *
     * @phpstan-param array<T> $nodes
     */
    public function __construct(array $nodes)
    {
        $this->nodes = $nodes;
    }

    /**
     * @param int|string $offset
     */
    public function offsetExists($offset) : bool
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
    public function offsetGet($offset)// : Node
    {
        $item = $this->nodes[$offset];

        if (is_array($item) && isset($item['kind'])) {
            /** @phpstan-var T $node */
            $node                 = AST::fromArray($item);
            $this->nodes[$offset] = $node;
        }

        return $this->nodes[$offset];
    }

    /**
     * @param int|string|null $offset
     * @param Node|mixed[]    $value
     *
     * @phpstan-param T|mixed[] $value
     */
    public function offsetSet($offset, $value) : void
    {
        if (is_array($value)) {
            if (isset($value['kind'])) {
                /** @phpstan-var T $node */
                $node                 = AST::fromArray($value);
                $this->nodes[$offset] = $node;

                return;
            }

            throw new InvalidArgumentException(
                'Expected array value to be valid node data structure, missing key "kind"'
            );
        }

        $this->nodes[$offset] = $value;
    }

    /**
     * @param int|string $offset
     */
    public function offsetUnset($offset) : void
    {
        unset($this->nodes[$offset]);
    }

    /**
     * @param mixed $replacement
     *
     * @phpstan-return NodeList<T>
     */
    public function splice(int $offset, int $length, $replacement = null) : NodeList
    {
        return new NodeList(array_splice($this->nodes, $offset, $length, $replacement));
    }

    /**
     * @param NodeList|Node[] $list
     *
     * @phpstan-param NodeList<T>|array<T> $list
     * @phpstan-return NodeList<T>
     */
    public function merge($list) : NodeList
    {
        if ($list instanceof self) {
            $list = $list->nodes;
        }

        return new NodeList(array_merge($this->nodes, $list));
    }

    public function getIterator() : Traversable
    {
        foreach ($this->nodes as $key => $_) {
            yield $this->offsetGet($key);
        }
    }

    public function count() : int
    {
        return count($this->nodes);
    }
}
