<?php
namespace GraphQL\Type\Definition;

use GraphQL\Utils;

class Config
{
    const BOOLEAN = 1;
    const STRING = 2;
    const INT = 4;
    const FLOAT = 8;
    const NUMERIC = 16;
    const SCALAR = 32;
    const CALLBACK = 64;
    const ANY = 128;

    const OUTPUT_TYPE = 2048;
    const INPUT_TYPE = 4096;
    const INTERFACE_TYPE = 8192;
    const OBJECT_TYPE = 16384;

    const REQUIRED = 65536;
    const KEY_AS_NAME = 131072;

    private static $enableValidation = false;

    public static function disableValidation()
    {
        self::$enableValidation = false;
    }

    /**
     * Enable deep config validation (disabled by default because it creates significant performance overhead).
     * Useful only at development to catch type definition errors quickly.
     */
    public static function enableValidation()
    {
        self::$enableValidation = true;
    }

    public static function validate(array $config, array $definition)
    {
        if (self::$enableValidation) {
            $name = isset($config['name']) ? $config['name'] : 'UnnamedType';
            self::_validateMap($name, $config, $definition);
        }
    }

    /**
     * @param $definition
     * @param int $flags
     * @return \stdClass
     */
    public static function map(array $definition, $flags = 0)
    {
        $tmp = new \stdClass();
        $tmp->isMap = true;
        $tmp->definition = $definition;
        $tmp->flags = $flags;
        return $tmp;
    }

    /**
     * @param array|int $definition
     * @param int $flags
     * @return \stdClass
     */
    public static function arrayOf($definition, $flags = 0)
    {
        $tmp = new \stdClass();
        $tmp->isArray = true;
        $tmp->definition = $definition;
        $tmp->flags = (int) $flags;
        return $tmp;
    }

    private static function _validateMap($typeName, array $map, array $definitions, $pathStr = null)
    {
        $suffix = $pathStr ? " at $pathStr" : '';

        // Make sure there are no unexpected keys in map
        $unexpectedKeys = array_keys(array_diff_key($map, $definitions));
        if (!empty($unexpectedKeys)) {
            trigger_error(sprintf('"%s" type definition: Unexpected keys "%s" ' . $suffix, $typeName, implode(', ', $unexpectedKeys)));
            $map = array_intersect_key($map, $definitions);
        }

        // Make sure that all required keys are present in map
        $requiredKeys = array_filter($definitions, function($def) {return (self::_getFlags($def) & self::REQUIRED) > 0;});
        $missingKeys = array_keys(array_diff_key($requiredKeys, $map));
        Utils::invariant(empty($missingKeys), 'Error in "'.$typeName.'" type definition: Required keys missing: "%s"' . $suffix, implode(', ', $missingKeys));

        // Make sure that every map value is valid given the definition
        foreach ($map as $key => $value) {
            self::_validateEntry($typeName, $key, $value, $definitions[$key], $pathStr ? "$pathStr:$key" : $key);
        }
    }

    private static function _validateEntry($typeName, $key, $value, $def, $pathStr)
    {
        $type = Utils::getVariableType($value);
        $err = 'Error in "'.$typeName.'" type definition: expecting %s at "' . $pathStr . '", but got "' . $type . '"';

        if ($def instanceof \stdClass) {
            if ($def->flags & self::REQUIRED === 0 && $value === null) {
                return ;
            }
            Utils::invariant(is_array($value), $err, 'array');

            if (!empty($def->isMap)) {
                if ($def->flags & self::KEY_AS_NAME) {
                    $value += ['name' => $key];
                }
                self::_validateMap($typeName, $value, $def->definition, $pathStr);
            } else if (!empty($def->isArray)) {

                if ($def->flags & self::REQUIRED) {
                    Utils::invariant(!empty($value), 'Error in "'.$typeName.'" type definition: ' . "Value at '$pathStr' cannot be empty array");
                }

                $err = 'Error in "'.$typeName.'" type definition:' . "Each entry at '$pathStr' must be an array, but '%s' is '%s'";

                foreach ($value as $arrKey => $arrValue) {
                    if (is_array($def->definition)) {
                        Utils::invariant(is_array($arrValue), $err, $arrKey, Utils::getVariableType($arrValue));

                        if ($def->flags & self::KEY_AS_NAME) {
                            $arrValue += ['name' => $arrKey];
                        }
                        self::_validateMap($typeName, $arrValue, $def->definition, "$pathStr:$arrKey");
                    } else {
                        self::_validateEntry($typeName, $arrKey, $arrValue, $def->definition, "$pathStr:$arrKey");
                    }
                }
            } else {
                throw new \Exception('Error in "'.$typeName.'" type definition:' . "unexpected definition: " . print_r($def, true));
            }
        } else {
            Utils::invariant(is_int($def), 'Error in "'.$typeName.'" type definition:' . "Definition for '$pathStr' is expected to be single integer value");

            if ($def & self::REQUIRED) {
                Utils::invariant($value !== null, 'Value at "%s" can not be null', $pathStr);
            }

            if (null === $value) {
                return ; // Allow nulls for non-required fields
            }

            switch (true) {
                case $def & self::ANY:
                    break;
                case $def & self::BOOLEAN:
                    Utils::invariant(is_bool($value), $err, 'boolean');
                    break;
                case $def & self::STRING:
                    Utils::invariant(is_string($value), $err, 'string');
                    break;
                case $def & self::NUMERIC:
                    Utils::invariant(is_numeric($value), $err, 'numeric');
                    break;
                case $def & self::FLOAT:
                    Utils::invariant(is_float($value), $err, 'float');
                    break;
                case $def & self::INT:
                    Utils::invariant(is_int($value), $err, 'int');
                    break;
                case $def & self::CALLBACK:
                    Utils::invariant(is_callable($value), $err, 'callable');
                    break;
                case $def & self::SCALAR:
                    Utils::invariant(is_scalar($value), $err, 'scalar');
                    break;
                case $def & self::INPUT_TYPE:
                    Utils::invariant(
                        is_callable($value) || $value instanceof InputType,
                        $err,
                        'callable or instance of GraphQL\Type\Definition\InputType'
                    );
                    break;
                case $def & self::OUTPUT_TYPE:
                    Utils::invariant(
                        is_callable($value) || $value instanceof OutputType,
                        $err,
                        'callable or instance of GraphQL\Type\Definition\OutputType'
                    );
                    break;
                case $def & self::INTERFACE_TYPE:
                    Utils::invariant(
                        is_callable($value) || $value instanceof InterfaceType,
                        $err,
                        'callable or instance of GraphQL\Type\Definition\InterfaceType'
                    );
                    break;
                case $def & self::OBJECT_TYPE:
                    Utils::invariant(
                        is_callable($value) || $value instanceof ObjectType,
                        $err,
                        'callable or instance of GraphQL\Type\Definition\ObjectType'
                    );
                    break;
                default:
                    throw new \Exception("Unexpected validation rule: " . $def);
            }
        }
    }

    private static function _getFlags($def)
    {
        return is_object($def) ? $def->flags : $def;
    }
}