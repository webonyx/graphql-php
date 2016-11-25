<?php
namespace GraphQL\Error;

use GraphQL\Language\SourceLocation;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\WrappingType;
use GraphQL\Utils;

/**
 * Class FormattedError
 * @todo move this class to Utils/ErrorUtils
 * @package GraphQL\Error
 */
class FormattedError
{
    /**
     * @deprecated as of 8.0
     * @param $error
     * @param SourceLocation[] $locations
     * @return array
     */
    public static function create($error, array $locations = [])
    {
        $formatted = [
            'message' => $error
        ];

        if (!empty($locations)) {
            $formatted['locations'] = array_map(function($loc) { return $loc->toArray();}, $locations);
        }

        return $formatted;
    }

    /**
     * @param \ErrorException $e
     * @return array
     */
    public static function createFromPHPError(\ErrorException $e)
    {
        return [
            'message' => $e->getMessage(),
            'severity' => $e->getSeverity(),
            'trace' => self::toSafeTrace($e->getTrace())
        ];
    }

    /**
     * @param \Exception $e
     * @return array
     */
    public static function createFromException(\Exception $e)
    {
        return [
            'message' => $e->getMessage(),
            'trace' => self::toSafeTrace($e->getTrace())
        ];
    }

    /**
     * Converts error trace to serializable array
     *
     * @param array $trace
     * @return array
     */
    private static function toSafeTrace(array $trace)
    {
        // Remove invariant entries as they don't provide much value:
        if (
            isset($trace[0]['function']) && isset($trace[0]['class']) &&
            ('GraphQL\Utils::invariant' === $trace[0]['class'].'::'.$trace[0]['function'])) {
            array_shift($trace);
        }

        // Remove root call as it's likely error handler trace:
        else if (!isset($trace[0]['file'])) {
            array_shift($trace);
        }

        return array_map(function($err) {
            $safeErr = array_intersect_key($err, ['file' => true, 'line' => true]);

            if (isset($err['function'])) {
                $func = $err['function'];
                $args = !empty($err['args']) ? array_map([__CLASS__, 'printVar'], $err['args']) : [];
                $funcStr = $func . '(' . implode(", ", $args) . ')';

                if (isset($err['class'])) {
                    $safeErr['call'] = $err['class'] . '::' . $funcStr;
                } else {
                    $safeErr['function'] = $funcStr;
                }
            }

            return $safeErr;
        }, $trace);
    }

    /**
     * @param $var
     * @return string
     */
    public static function printVar($var)
    {
        if ($var instanceof Type) {
            // FIXME: Replace with schema printer call
            if ($var instanceof WrappingType) {
                $var = $var->getWrappedType(true);
            }
            return 'GraphQLType: ' . $var->name;
        }

        if (is_object($var)) {
            return 'instance of ' . get_class($var) . ($var instanceof \Countable ? '(' . count($var) . ')' : '');
        }
        if (is_array($var)) {
            return 'array(' . count($var) . ')';
        }
        if ('' === $var) {
            return '(empty string)';
        }
        if (is_string($var)) {
            return "'" . addcslashes($var, "'") . "'";
        }
        if (is_bool($var)) {
            return $var ? 'true' : 'false';
        }
        if (is_scalar($var)) {
            return $var;
        }
        if (null === $var) {
            return 'null';
        }
        return gettype($var);
    }
}
