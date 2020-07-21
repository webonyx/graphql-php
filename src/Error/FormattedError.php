<?php

declare(strict_types=1);

namespace GraphQL\Error;

use Countable;
use ErrorException;
use GraphQL\Language\AST\Node;
use GraphQL\Language\Source;
use GraphQL\Language\SourceLocation;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\WrappingType;
use GraphQL\Utils\Utils;
use Throwable;
use function addcslashes;
use function array_filter;
use function array_intersect_key;
use function array_map;
use function array_merge;
use function array_shift;
use function count;
use function get_class;
use function gettype;
use function implode;
use function is_array;
use function is_bool;
use function is_object;
use function is_scalar;
use function is_string;
use function mb_strlen;
use function preg_split;
use function sprintf;
use function str_repeat;
use function strlen;

/**
 * This class is used for [default error formatting](error-handling.md).
 * It converts PHP exceptions to [spec-compliant errors](https://facebook.github.io/graphql/#sec-Errors)
 * and provides tools for error debugging.
 */
class FormattedError
{
    /** @var string */
    private static $internalErrorMessage = 'Internal server error';

    /**
     * Set default error message for internal errors formatted using createFormattedError().
     * This value can be overridden by passing 3rd argument to `createFormattedError()`.
     *
     * @param string $msg
     *
     * @api
     */
    public static function setInternalErrorMessage($msg)
    {
        self::$internalErrorMessage = $msg;
    }

    /**
     * Prints a GraphQLError to a string, representing useful location information
     * about the error's position in the source.
     *
     * @return string
     */
    public static function printError(Error $error)
    {
        $printedLocations = [];
        if (count($error->nodes ?? []) !== 0) {
            /** @var Node $node */
            foreach ($error->nodes as $node) {
                if ($node->loc === null) {
                    continue;
                }

                if ($node->loc->source === null) {
                    continue;
                }

                $printedLocations[] = self::highlightSourceAtLocation(
                    $node->loc->source,
                    $node->loc->source->getLocation($node->loc->start)
                );
            }
        } elseif ($error->getSource() !== null && count($error->getLocations()) !== 0) {
            $source = $error->getSource();
            foreach (($error->getLocations() ?? []) as $location) {
                $printedLocations[] = self::highlightSourceAtLocation($source, $location);
            }
        }

        return count($printedLocations) === 0
            ? $error->getMessage()
            : implode("\n\n", array_merge([$error->getMessage()], $printedLocations)) . "\n";
    }

    /**
     * Render a helpful description of the location of the error in the GraphQL
     * Source document.
     *
     * @return string
     */
    private static function highlightSourceAtLocation(Source $source, SourceLocation $location)
    {
        $line          = $location->line;
        $lineOffset    = $source->locationOffset->line - 1;
        $columnOffset  = self::getColumnOffset($source, $location);
        $contextLine   = $line + $lineOffset;
        $contextColumn = $location->column + $columnOffset;
        $prevLineNum   = (string) ($contextLine - 1);
        $lineNum       = (string) $contextLine;
        $nextLineNum   = (string) ($contextLine + 1);
        $padLen        = strlen($nextLineNum);
        $lines         = preg_split('/\r\n|[\n\r]/', $source->body);

        $lines[0] = self::whitespace($source->locationOffset->column - 1) . $lines[0];

        $outputLines = [
            sprintf('%s (%s:%s)', $source->name, $contextLine, $contextColumn),
            $line >= 2 ? (self::lpad($padLen, $prevLineNum) . ': ' . $lines[$line - 2]) : null,
            self::lpad($padLen, $lineNum) . ': ' . $lines[$line - 1],
            self::whitespace(2 + $padLen + $contextColumn - 1) . '^',
            $line < count($lines) ? self::lpad($padLen, $nextLineNum) . ': ' . $lines[$line] : null,
        ];

        return implode("\n", array_filter($outputLines));
    }

    /**
     * @return int
     */
    private static function getColumnOffset(Source $source, SourceLocation $location)
    {
        return $location->line === 1 ? $source->locationOffset->column - 1 : 0;
    }

    /**
     * @param int $len
     *
     * @return string
     */
    private static function whitespace($len)
    {
        return str_repeat(' ', $len);
    }

    /**
     * @param int $len
     *
     * @return string
     */
    private static function lpad($len, $str)
    {
        return self::whitespace($len - mb_strlen($str)) . $str;
    }

    /**
     * Standard GraphQL error formatter. Converts any exception to array
     * conforming to GraphQL spec.
     *
     * This method only exposes exception message when exception implements ClientAware interface
     * (or when debug flags are passed).
     *
     * For a list of available debug flags @see \GraphQL\Error\DebugFlag constants.
     *
     * @param string $internalErrorMessage
     *
     * @return mixed[]
     *
     * @throws Throwable
     *
     * @api
     */
    public static function createFromException(Throwable $exception, int $debug = DebugFlag::NONE, $internalErrorMessage = null) : array
    {
        $internalErrorMessage = $internalErrorMessage ?? self::$internalErrorMessage;

        if ($exception instanceof ClientAware) {
            $formattedError = [
                'message'  => $exception->isClientSafe() ? $exception->getMessage() : $internalErrorMessage,
                'extensions' => [
                    'category' => $exception->getCategory(),
                ],
            ];
        } else {
            $formattedError = [
                'message'  => $internalErrorMessage,
                'extensions' => [
                    'category' => Error::CATEGORY_INTERNAL,
                ],
            ];
        }

        if ($exception instanceof Error) {
            $locations = Utils::map(
                $exception->getLocations(),
                static function (SourceLocation $loc) : array {
                    return $loc->toSerializableArray();
                }
            );
            if (count($locations) > 0) {
                $formattedError['locations'] = $locations;
            }

            if (count($exception->path ?? []) > 0) {
                $formattedError['path'] = $exception->path;
            }
            if (count($exception->getExtensions() ?? []) > 0) {
                $formattedError['extensions'] = $exception->getExtensions() + $formattedError['extensions'];
            }
        }

        if ($debug !== DebugFlag::NONE) {
            $formattedError = self::addDebugEntries($formattedError, $exception, $debug);
        }

        return $formattedError;
    }

    /**
     * Decorates spec-compliant $formattedError with debug entries according to $debug flags
     * (@see \GraphQL\Error\DebugFlag for available flags)
     *
     * @param mixed[] $formattedError
     *
     * @return mixed[]
     *
     * @throws Throwable
     */
    public static function addDebugEntries(array $formattedError, Throwable $e, int $debugFlag) : array
    {
        if ($debugFlag === DebugFlag::NONE) {
            return $formattedError;
        }

        if (( $debugFlag & DebugFlag::RETHROW_INTERNAL_EXCEPTIONS) !== 0) {
            if (! $e instanceof Error) {
                throw $e;
            }

            if ($e->getPrevious() !== null) {
                throw $e->getPrevious();
            }
        }

        $isUnsafe = ! $e instanceof ClientAware || ! $e->isClientSafe();

        if (($debugFlag & DebugFlag::RETHROW_UNSAFE_EXCEPTIONS) !== 0 && $isUnsafe && $e->getPrevious() !== null) {
            throw $e->getPrevious();
        }

        if (($debugFlag & DebugFlag::INCLUDE_DEBUG_MESSAGE) !== 0 && $isUnsafe) {
            // Displaying debugMessage as a first entry:
            $formattedError = ['debugMessage' => $e->getMessage()] + $formattedError;
        }

        if (($debugFlag & DebugFlag::INCLUDE_TRACE) !== 0) {
            if ($e instanceof ErrorException || $e instanceof \Error) {
                $formattedError += [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ];
            }

            $isTrivial = $e instanceof Error && $e->getPrevious() === null;

            if (! $isTrivial) {
                $debugging               = $e->getPrevious() ?? $e;
                $formattedError['trace'] = static::toSafeTrace($debugging);
            }
        }

        return $formattedError;
    }

    /**
     * Prepares final error formatter taking in account $debug flags.
     * If initial formatter is not set, FormattedError::createFromException is used
     */
    public static function prepareFormatter(?callable $formatter = null, int $debug) : callable
    {
        $formatter = $formatter ?? static function ($e) : array {
            return FormattedError::createFromException($e);
        };
        if ($debug !== DebugFlag::NONE) {
            $formatter = static function ($e) use ($formatter, $debug) : array {
                return FormattedError::addDebugEntries($formatter($e), $e, $debug);
            };
        }

        return $formatter;
    }

    /**
     * Returns error trace as serializable array
     *
     * @param Throwable $error
     *
     * @return mixed[]
     *
     * @api
     */
    public static function toSafeTrace($error)
    {
        $trace = $error->getTrace();

        if (isset($trace[0]['function']) && isset($trace[0]['class']) &&
            // Remove invariant entries as they don't provide much value:
            ($trace[0]['class'] . '::' . $trace[0]['function'] === 'GraphQL\Utils\Utils::invariant')) {
            array_shift($trace);
        } elseif (! isset($trace[0]['file'])) {
            // Remove root call as it's likely error handler trace:
            array_shift($trace);
        }

        return array_map(
            static function ($err) : array {
                $safeErr = array_intersect_key($err, ['file' => true, 'line' => true]);

                if (isset($err['function'])) {
                    $func    = $err['function'];
                    $args    = array_map([self::class, 'printVar'], $err['args'] ?? []);
                    $funcStr = $func . '(' . implode(', ', $args) . ')';

                    if (isset($err['class'])) {
                        $safeErr['call'] = $err['class'] . '::' . $funcStr;
                    } else {
                        $safeErr['function'] = $funcStr;
                    }
                }

                return $safeErr;
            },
            $trace
        );
    }

    /**
     * @param mixed $var
     *
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
            return 'instance of ' . get_class($var) . ($var instanceof Countable ? '(' . count($var) . ')' : '');
        }
        if (is_array($var)) {
            return 'array(' . count($var) . ')';
        }
        if ($var === '') {
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
        if ($var === null) {
            return 'null';
        }

        return gettype($var);
    }

    /**
     * @deprecated as of v0.8.0
     *
     * @param string           $error
     * @param SourceLocation[] $locations
     *
     * @return mixed[]
     */
    public static function create($error, array $locations = [])
    {
        $formatted = ['message' => $error];

        if (count($locations) > 0) {
            $formatted['locations'] = array_map(
                static function ($loc) : array {
                    return $loc->toArray();
                },
                $locations
            );
        }

        return $formatted;
    }

    /**
     * @deprecated as of v0.10.0, use general purpose method createFromException() instead
     *
     * @return mixed[]
     *
     * @codeCoverageIgnore
     */
    public static function createFromPHPError(ErrorException $e)
    {
        return [
            'message'  => $e->getMessage(),
            'severity' => $e->getSeverity(),
            'trace'    => self::toSafeTrace($e),
        ];
    }
}
