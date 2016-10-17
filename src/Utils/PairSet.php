<?php
namespace GraphQL\Utils;

/**
 * Class PairSet
 * @package GraphQL\Utils
 */
class PairSet
{
    /**
     * @var \SplObjectStorage<any, Set<any>>
     */
    private $data;

    /**
     * @var array
     */
    private $wrappers = [];

    /**
     * PairSet constructor.
     */
    public function __construct()
    {
        $this->data = new \SplObjectStorage(); // SplObject hash instead?
    }

    /**
     * @param $a
     * @param $b
     * @return null|object
     */
    public function has($a, $b)
    {
        $a = $this->toObj($a);
        $b = $this->toObj($b);

        /** @var \SplObjectStorage $first */
        $first = isset($this->data[$a]) ? $this->data[$a] : null;
        return isset($first, $first[$b]) ? $first[$b] : null;
    }

    /**
     * @param $a
     * @param $b
     */
    public function add($a, $b)
    {
        $this->pairSetAdd($a, $b);
        $this->pairSetAdd($b, $a);
    }

    /**
     * @param $var
     * @return mixed
     */
    private function toObj($var)
    {
        // SplObjectStorage expects objects, so wrapping non-objects to objects
        if (is_object($var)) {
            return $var;
        }
        if (!isset($this->wrappers[$var])) {
            $tmp = new \stdClass();
            $tmp->_internal = $var;
            $this->wrappers[$var] = $tmp;
        }
        return $this->wrappers[$var];
    }

    /**
     * @param $a
     * @param $b
     */
    private function pairSetAdd($a, $b)
    {
        $a = $this->toObj($a);
        $b = $this->toObj($b);
        $set = isset($this->data[$a]) ? $this->data[$a] : null;

        if (!isset($set)) {
            $set = new \SplObjectStorage();
            $this->data[$a] = $set;
        }
        $set[$b] = true;
    }
}
