<?php
namespace GraphQL;

class Utils
{
    /**
     * @param $obj
     * @param array $vars
     * @return mixed
     */
    public static function assign($obj, array $vars, array $requiredKeys = array())
    {
        foreach ($requiredKeys as $key) {
            if (!isset($key, $vars)) {
                throw new \InvalidArgumentException("Key {$key} is expected to be set and not to be null");
            }
        }

        foreach ($vars as $key => $value) {
            if (!property_exists($obj, $key)) {
                $cls = get_class($obj);
                trigger_error("Trying to set non-existing property '$key' on class '$cls'");
            }
            $obj->{$key} = $value;
        }
        return $obj;
    }

    /**
     * @param array $list
     * @param $predicate
     * @return null
     */
    public static function find(array $list, $predicate)
    {
        for ($i = 0; $i < count($list); $i++) {
            if ($predicate($list[$i])) {
                return $list[$i];
            }
        }
        return null;
    }

    /**
     * @param $test
     * @param string $message
     * @param mixed $sprintfParam1
     * @param mixed $sprintfParam2 ...
     * @throws \Exception
     */
    public static function invariant($test, $message = '')
    {
        if (!$test) {
            if (func_num_args() > 2) {
                $args = func_get_args();
                array_shift($args);
                $message = call_user_func_array('sprintf', $args);
            }
            throw new \Exception($message);
        }
    }

    /**
     * @param $var
     * @return string
     */
    public static function getVariableType($var)
    {
        return is_object($var) ? get_class($var) : gettype($var);
    }

    public static function chr($ord, $encoding = 'UTF-8')
    {
        if ($ord <= 255) {
            return chr($ord);
        }
        if ($encoding === 'UCS-4BE') {
            return pack("N", $ord);
        } else {
            return mb_convert_encoding(self::chr($ord, 'UCS-4BE'), $encoding, 'UCS-4BE');
        }
    }

    /**
     * UTF-8 compatible ord()
     *
     * @param $char
     * @param string $encoding
     * @return mixed
     */
    public static function ord($char, $encoding = 'UTF-8')
    {
        if (!isset($char[1])) {
            return ord($char);
        }
        if ($encoding === 'UCS-4BE') {
            list(, $ord) = (strlen($char) === 4) ? @unpack('N', $char) : @unpack('n', $char);
            return $ord;
        } else {
            return self::ord(mb_convert_encoding($char, 'UCS-4BE', $encoding), 'UCS-4BE');
        }
    }

    public static function charCodeAt($string, $position)
    {
        $char = mb_substr($string, $position, 1, 'UTF-8');
        return self::ord($char);
    }

    /**
     *
     */
    public static function keyMap(array $list, callable $keyFn)
    {
        $map = [];
        foreach ($list as $value) {
            $map[$keyFn($value)] = $value;
        }
        return $map;
    }
}
