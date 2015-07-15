<?php
namespace GraphQL\Utils;


class PairSet
{
    /**
     * @var \SplObjectStorage<any, Set<any>>
     */
    private $_data;

    private $_wrappers = [];

    public function __construct()
    {
        $this->_data = new \SplObjectStorage(); // SplObject hash instead?
    }

    public function has($a, $b)
    {
        $a = $this->_toObj($a);
        $b = $this->_toObj($b);

        /** @var \SplObjectStorage $first */
        $first = isset($this->_data[$a]) ? $this->_data[$a] : null;
        return isset($first, $first[$b]) ? $first[$b] : null;
    }

    public function add($a, $b)
    {
        $this->_pairSetAdd($a, $b);
        $this->_pairSetAdd($b, $a);
    }

    private function _toObj($var)
    {
        // SplObjectStorage expects objects, so wrapping non-objects to objects
        if (is_object($var)) {
            return $var;
        }
        if (!isset($this->_wrappers[$var])) {
            $tmp = new \stdClass();
            $tmp->_internal = $var;
            $this->_wrappers[$var] = $tmp;
        }
        return $this->_wrappers[$var];
    }

    private function _pairSetAdd($a, $b)
    {
        $a = $this->_toObj($a);
        $b = $this->_toObj($b);
        $set = isset($this->_data[$a]) ? $this->_data[$a] : null;

        if (!isset($set)) {
            $set = new \SplObjectStorage();
            $this->_data[$a] = $set;
        }
        $set[$b] = true;
    }
}
