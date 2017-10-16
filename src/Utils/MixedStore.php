<?php
namespace GraphQL\Utils;

/**
 * Similar to PHP array, but allows any type of data to act as key (including arrays, objects, scalars)
 *
 * Note: unfortunately when storing array as key - access and modification is O(N)
 * (yet this should be really rare case and should be avoided when possible)
 *
 * Class MixedStore
 * @package GraphQL\Utils
 */
class MixedStore implements \ArrayAccess
{
    /**
     * @var array
     */
    private $standardStore;

    /**
     * @var array
     */
    private $floatStore;

    /**
     * @var \SplObjectStorage
     */
    private $objectStore;

    /**
     * @var array
     */
    private $arrayKeys;

    /**
     * @var array
     */
    private $arrayValues;

    /**
     * @var array
     */
    private $lastArrayKey;

    /**
     * @var mixed
     */
    private $lastArrayValue;

    /**
     * @var mixed
     */
    private $nullValue;

    /**
     * @var bool
     */
    private $nullValueIsSet;

    /**
     * @var mixed
     */
    private $trueValue;

    /**
     * @var bool
     */
    private $trueValueIsSet;

    /**
     * @var mixed
     */
    private $falseValue;

    /**
     * @var bool
     */
    private $falseValueIsSet;

    /**
     * MixedStore constructor.
     */
    public function __construct()
    {
        $this->standardStore = [];
        $this->floatStore = [];
        $this->objectStore = new \SplObjectStorage();
        $this->arrayKeys = [];
        $this->arrayValues = [];
        $this->nullValueIsSet = false;
        $this->trueValueIsSet = false;
        $this->falseValueIsSet = false;
    }

    /**
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     * @since 5.0.0
     */
    public function offsetExists($offset)
    {
        if (false === $offset) {
            return $this->falseValueIsSet;
        }
        if (true === $offset) {
            return $this->trueValueIsSet;
        }
        if (is_int($offset) || is_string($offset)) {
            return array_key_exists($offset, $this->standardStore);
        }
        if (is_float($offset)) {
            return array_key_exists((string) $offset, $this->floatStore);
        }
        if (is_object($offset)) {
            return $this->objectStore->offsetExists($offset);
        }
        if (is_array($offset)) {
            foreach ($this->arrayKeys as $index => $entry) {
                if ($entry === $offset) {
                    $this->lastArrayKey = $offset;
                    $this->lastArrayValue = $this->arrayValues[$index];
                    return true;
                }
            }
        }
        if (null === $offset) {
            return $this->nullValueIsSet;
        }
        return false;
    }

    /**
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     * @since 5.0.0
     */
    public function offsetGet($offset)
    {
        if (true === $offset) {
            return $this->trueValue;
        }
        if (false === $offset) {
            return $this->falseValue;
        }
        if (is_int($offset) || is_string($offset)) {
            return $this->standardStore[$offset];
        }
        if (is_float($offset)) {
            return $this->floatStore[(string)$offset];
        }
        if (is_object($offset)) {
            return $this->objectStore->offsetGet($offset);
        }
        if (is_array($offset)) {
            // offsetGet is often called directly after offsetExists, so optimize to avoid second loop:
            if ($this->lastArrayKey === $offset) {
                return $this->lastArrayValue;
            }
            foreach ($this->arrayKeys as $index => $entry) {
                if ($entry === $offset) {
                    return $this->arrayValues[$index];
                }
            }
        }
        if (null === $offset) {
            return $this->nullValue;
        }
        return null;
    }

    /**
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetSet($offset, $value)
    {
        if (false === $offset) {
            $this->falseValue = $value;
            $this->falseValueIsSet = true;
        } else if (true === $offset) {
            $this->trueValue = $value;
            $this->trueValueIsSet = true;
        } else if (is_int($offset) || is_string($offset)) {
            $this->standardStore[$offset] = $value;
        } else if (is_float($offset)) {
            $this->floatStore[(string)$offset] = $value;
        } else if (is_object($offset)) {
            $this->objectStore[$offset] = $value;
        } else if (is_array($offset)) {
            $this->arrayKeys[] = $offset;
            $this->arrayValues[] = $value;
        } else if (null === $offset) {
            $this->nullValue = $value;
            $this->nullValueIsSet = true;
        } else {
            throw new \InvalidArgumentException("Unexpected offset type: " . Utils::printSafe($offset));
        }
    }

    /**
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetUnset($offset)
    {
        if (true === $offset) {
            $this->trueValue = null;
            $this->trueValueIsSet = false;
        } else if (false === $offset) {
            $this->falseValue = null;
            $this->falseValueIsSet = false;
        } else if (is_int($offset) || is_string($offset)) {
            unset($this->standardStore[$offset]);
        } else if (is_float($offset)) {
            unset($this->floatStore[(string)$offset]);
        } else if (is_object($offset)) {
            $this->objectStore->offsetUnset($offset);
        } else if (is_array($offset)) {
            $index = array_search($offset, $this->arrayKeys, true);

            if (false !== $index) {
                array_splice($this->arrayKeys, $index, 1);
                array_splice($this->arrayValues, $index, 1);
            }
        } else if (null === $offset) {
            $this->nullValue = null;
            $this->nullValueIsSet = false;
        }
    }
}
