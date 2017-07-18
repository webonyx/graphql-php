<?php
namespace GraphQL\Error;

use GraphQL\Language\SourceLocation;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\WrappingType;
use GraphQL\Utils\Utils;

/**
 * Class FormattedError
 * 
 * @package GraphQL\Error
 */
class FormattedError
{
    const INCLUDE_DEBUG_MESSAGE = 1;
    const INCLUDE_TRACE = 2;

    private static $internalErrorMessage = 'Internal server error';

    public static function setInternalErrorMessage($msg)
    {
        self::$internalErrorMessage = $msg;
    }

    /**
     * @param \Throwable $e
     * @param bool|int $debug
     * @param string $internalErrorMessage
     *
     * @return array
     */
    public static function createFromException($e, $debug = false, $internalErrorMessage = null)
    {
        Utils::invariant(
            $e instanceof \Exception || $e instanceof \Throwable,
            "Expected exception, got %s",
            Utils::getVariableType($e)
        );

        $debug = (int) $debug;
        $internalErrorMessage = $internalErrorMessage ?: self::$internalErrorMessage;

        if ($e instanceof ClientAware) {
            if ($e->isClientSafe()) {
                $result = [
                    'message' => $e->getMessage()
                ];
            } else {
                $result = [
                    'message' => $internalErrorMessage,
                    'isInternalError' => true
                ];
            }
        } else {
            $result = [
                'message' => $internalErrorMessage,
                'isInternalError' => true
            ];
        }
        if (($debug & self::INCLUDE_DEBUG_MESSAGE > 0) && $result['message'] === $internalErrorMessage) {
            $result['debugMessage'] = $e->getMessage();
        }

        if ($e instanceof Error) {
            $locations = Utils::map($e->getLocations(), function(SourceLocation $loc) {
                return $loc->toSerializableArray();
            });

            if (!empty($locations)) {
                $result['locations'] = $locations;
            }
            if (!empty($e->path)) {
                $result['path'] = $e->path;
            }
        } else if ($e instanceof \ErrorException) {
            if ($debug) {
                $result += [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'severity' => $e->getSeverity()
                ];
            }
        } else if ($e instanceof \Error) {
            if ($debug) {
                $result += [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ];
            }
        }

        if ($debug & self::INCLUDE_TRACE > 0) {
            $debugging = $e->getPrevious() ?: $e;
            $result['trace'] = static::toSafeTrace($debugging->getTrace());
        }

        return $result;
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
            ('GraphQL\Utils\Utils::invariant' === $trace[0]['class'].'::'.$trace[0]['function'])) {
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

    /**
     * @deprecated as of v0.8.0
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
     * @deprecated as of v0.10.0, use general purpose method createFromException() instead
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
}
