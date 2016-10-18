<?php
namespace GraphQL\Utils;
use GraphQL\Utils;

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
    private $scalarStore;

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
     * MixedStore constructor.
     */
    public function __construct()
    {
        $this->scalarStore = [];
        $this->objectStore = new \SplObjectStorage();
        $this->arrayKeys = [];
        $this->arrayValues = [];
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
        if (is_scalar($offset)) {
            return array_key_exists($offset, $this->scalarStore);
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
        if (is_scalar($offset)) {
            return $this->scalarStore[$offset];
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
        if (is_scalar($offset)) {
            $this->scalarStore[$offset] = $value;
        } else if (is_object($offset)) {
            $this->objectStore[$offset] = $value;
        } else if (is_array($offset)) {
            $this->arrayKeys[] = $offset;
            $this->arrayValues[] = $value;
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
        if (is_scalar($offset)) {
            unset($this->scalarStore[$offset]);
        } else if (is_object($offset)) {
            $this->objectStore->offsetUnset($offset);
        } else if (is_array($offset)) {
            $index = array_search($offset, $this->arrayKeys, true);

            if (false !== $index) {
                array_splice($this->arrayKeys, $index, 1);
                array_splice($this->arrayValues, $index, 1);
            }
        }
    }
}
