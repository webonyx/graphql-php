<?php
namespace GraphQL\Language\AST;

use GraphQL\Utils\AST;

/**
 * Class NodeList
 *
 * @package GraphQL\Utils
 */
class NodeList implements \ArrayAccess, \IteratorAggregate, \Countable
{
    /**
     * @var array
     */
    private $nodes;

    /**
     * @param array $nodes
     * @return static
     */
    public static function create(array $nodes)
    {
        return new static($nodes);
    }

    /**
     * NodeList constructor.
     * @param array $nodes
     */
    public function __construct(array $nodes)
    {
        $this->nodes = $nodes;
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->nodes[$offset]);
    }

    /**
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        $item = $this->nodes[$offset];

        if (is_array($item) && isset($item['kind'])) {
            $this->nodes[$offset] = $item = AST::fromArray($item);
        }

        return $item;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        if (is_array($value) && isset($value['kind'])) {
            $value = AST::fromArray($value);
        }
        $this->nodes[$offset] = $value;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->nodes[$offset]);
    }

    /**
     * @param int $offset
     * @param int $length
     * @param mixed $replacement
     * @return NodeList
     */
    public function splice($offset, $length, $replacement = null)
    {
        return new NodeList(array_splice($this->nodes, $offset, $length, $replacement));
    }

    /**
     * @param $list
     * @return NodeList
     */
    public function merge($list)
    {
        if ($list instanceof NodeList) {
            $list = $list->nodes;
        }
        return new NodeList(array_merge($this->nodes, $list));
    }

    /**
     * @return \Generator
     */
    public function getIterator()
    {
        $count = count($this->nodes);
        for ($i = 0; $i < $count; $i++) {
            yield $this->offsetGet($i);
        }
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->nodes);
    }
}
